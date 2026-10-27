/**
 * Estado del editor visual: borrador de la página, selección, historial
 * (deshacer/rehacer), autoguardado, publicación y las interacciones de
 * arrastrar y redimensionar con Pointer Events (ratón, táctil y lápiz).
 *
 * Arrastrar/redimensionar es suave: mientras el puntero se mueve solo se
 * aplica un `transform` temporal (en un requestAnimationFrame) y una guía
 * de dónde caerá el bloque; la posición en la retícula se confirma al
 * soltar, en un solo paso del historial.
 */
import { computed, reactive, ref, shallowRef, watch } from 'vue';
import { apiFetch } from '@/lib/http';
import { clamp, COLUMNS, freeSpot, MAX_HEIGHT, MAX_ROW } from '../grid';
import { BLOCKS } from '../registry';
import type {
    Block,
    BlockType,
    DraftMeta,
    Device,
    DynamicDescriptor,
    LibraryMap,
    RenderSection,
    SectionSettings,
    SectionTemplate,
    VersionRow,
} from '../types';

export type EditorInit = {
    sections: RenderSection[];
    draft: DraftMeta;
    library: LibraryMap;
    versions: VersionRow[];
    dynamic: Record<string, DynamicDescriptor>;
    urls: {
        save: string;
        publish: string;
        discard: string;
        restore: (id: number) => string;
    };
};

export type SaveState = 'saved' | 'dirty' | 'saving' | 'error' | 'conflict';

type Interaction =
    | {
          kind: 'drag';
          sectionKey: string;
          ids: string[];
          dx: number;
          dy: number;
          ux: number;
          uy: number;
      }
    | {
          kind: 'resize';
          sectionKey: string;
          id: string;
          dx: number;
          dy: number;
          width: number;
          height: number;
          rect: { x: number; y: number; w: number; h: number };
      };

export type Ghost = {
    sectionKey: string;
    rects: { x: number; y: number; w: number; h: number }[];
};

export type Guides = { sectionKey: string; x: number[]; y: number[] };

function uid(): string {
    return `b${Math.random().toString(36).slice(2, 11).padEnd(9, '0')}`;
}

function uuid(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;

        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}

function isTyping(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) return false;

    return (
        target.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) ||
        target.closest(
            '[role="dialog"], [role="alertdialog"], [role="listbox"], [role="menu"]',
        ) !== null
    );
}

export function createEditor(init: EditorInit) {
    const sections = ref<RenderSection[]>(clone(init.sections));
    const library = ref<LibraryMap>({ ...init.library });
    const draft = reactive<DraftMeta>({ ...init.draft });
    const versions = ref<VersionRow[]>(init.versions);

    const selection = reactive<{ section: string | null; blocks: string[] }>({
        section: sections.value[0]?.key ?? null,
        blocks: [],
    });
    const device = ref<Device>('desktop');
    const zoomMode = ref<'fit' | number>('fit');
    const fitZoom = ref(1);
    const zoom = computed(() =>
        zoomMode.value === 'fit' ? fitZoom.value : zoomMode.value,
    );
    const textEditing = ref<string | null>(null);
    const cropping = ref<string | null>(null);
    const interaction = shallowRef<Interaction | null>(null);
    const ghost = ref<Ghost | null>(null);
    const guides = ref<Guides | null>(null);
    const dropTarget = ref<string | null>(null);
    const uploads = reactive<Record<string, number>>({});

    const saveState = ref<SaveState>('saved');
    const savedAt = ref<Date | null>(
        draft.savedAt ? new Date(draft.savedAt) : null,
    );
    const errors = ref<Record<string, string[]>>({});
    const conflictMessage = ref<string | null>(null);

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    function section(key: string | null): RenderSection | undefined {
        return sections.value.find((s) => s.key === key);
    }

    function block(sectionKey: string, id: string): Block | undefined {
        return section(sectionKey)?.layout.blocks.find((b) => b.id === id);
    }

    const currentSection = computed(() => section(selection.section));
    const selectedBlocks = computed(() => {
        const s = currentSection.value;
        if (!s) return [];

        return s.layout.blocks.filter((b) => selection.blocks.includes(b.id));
    });
    const primaryBlock = computed(() => {
        const id = selection.blocks[selection.blocks.length - 1];
        if (!id || !selection.section) return undefined;

        return block(selection.section, id);
    });

    const h1Count = computed(
        () =>
            sections.value
                .filter((s) => s.isVisible)
                .flatMap((s) => s.layout.blocks)
                .filter(
                    (b) =>
                        b.type === 'heading' &&
                        b.props.level === 'h1' &&
                        b.hideOn.length < 2,
                ).length,
    );

    const canInteract = computed(() => device.value === 'desktop');

    // ------------------------------------------------------------------
    // Historial
    // ------------------------------------------------------------------

    const past = ref<string[]>([]);
    const future = ref<string[]>([]);
    const changeTick = ref(0);

    function serialize(): string {
        return JSON.stringify(
            sections.value.map((s) => ({
                key: s.key,
                type: s.type,
                isVisible: s.isVisible,
                content: s.content,
                layout: s.layout,
            })),
        );
    }

    function restore(json: string) {
        const parsed = JSON.parse(json) as Pick<
            RenderSection,
            'key' | 'type' | 'isVisible' | 'content' | 'layout'
        >[];
        sections.value = parsed.map((s) => {
            const existing = section(s.key);

            return {
                id: existing?.id ?? null,
                images: existing?.images ?? {},
                videos: existing?.videos ?? {},
                data: existing?.data ?? {},
                ...s,
            };
        });
        // La selección debe seguir apuntando a algo que exista.
        if (!section(selection.section)) {
            selection.section = sections.value[0]?.key ?? null;
            selection.blocks = [];
        } else {
            const s = section(selection.section)!;
            selection.blocks = selection.blocks.filter((id) =>
                s.layout.blocks.some((b) => b.id === id),
            );
        }
    }

    let liveBefore: string | null = null;

    /** Aplica un cambio como un paso del historial. */
    function commit(mutate: () => void) {
        const before = liveBefore ?? serialize();
        liveBefore = null;
        mutate();
        if (serialize() === before) return;
        past.value = [...past.value.slice(-99), before];
        future.value = [];
        changed();
    }

    /** Cambio continuo (deslizador, encuadre): un solo paso al terminar. */
    function live(mutate: () => void, record: boolean) {
        if (liveBefore === null) liveBefore = serialize();
        if (record) {
            commit(mutate);
        } else {
            mutate();
        }
    }

    function undo() {
        const previous = past.value[past.value.length - 1];
        if (previous === undefined) return;
        future.value = [...future.value, serialize()];
        past.value = past.value.slice(0, -1);
        restore(previous);
        changed();
    }

    function redo() {
        const next = future.value[future.value.length - 1];
        if (next === undefined) return;
        past.value = [...past.value, serialize()];
        future.value = future.value.slice(0, -1);
        restore(next);
        changed();
    }

    // ------------------------------------------------------------------
    // Autoguardado
    // ------------------------------------------------------------------

    let saveTimer: number | undefined;
    let saving: Promise<void> | null = null;
    let pending = false;

    function changed() {
        changeTick.value++;
        saveState.value = 'dirty';
        draft.hasChanges = true;
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => void save(), 1200);
    }

    function payload() {
        return sections.value.map((s) => ({
            key: s.key,
            type: s.type,
            isVisible: s.isVisible,
            content: s.content,
            layout: s.layout,
        }));
    }

    async function save(): Promise<void> {
        if (saveState.value === 'conflict') return;
        if (saving) {
            pending = true;

            return saving;
        }
        window.clearTimeout(saveTimer);
        saveState.value = 'saving';

        saving = (async () => {
            try {
                const response = await apiFetch(init.urls.save, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        revision: draft.revision,
                        sections: payload(),
                    }),
                });
                const json = await response.json().catch(() => ({}));

                if (response.status === 409) {
                    saveState.value = 'conflict';
                    conflictMessage.value = json.message ?? null;

                    return;
                }
                if (response.status === 422) {
                    errors.value = json.errors ?? {};
                    saveState.value = 'error';

                    return;
                }
                if (!response.ok) {
                    throw new Error(json.message ?? 'No se pudo guardar.');
                }

                Object.assign(draft, {
                    revision: json.revision,
                    savedAt: json.savedAt,
                    savedBy: json.savedBy,
                    hasChanges: json.hasChanges,
                });
                savedAt.value = new Date();
                errors.value = {};
                applyResolved(json.resolved ?? {});
                saveState.value = pending ? 'dirty' : 'saved';
            } catch {
                saveState.value = 'error';
            } finally {
                saving = null;
                if (pending) {
                    pending = false;
                    await save();
                }
            }
        })();

        return saving;
    }

    /** Fuerza el guardado pendiente (antes de publicar o salir). */
    async function flush() {
        window.clearTimeout(saveTimer);
        if (saving) await saving;
        if (saveState.value === 'dirty' || saveState.value === 'error') {
            await save();
        }
    }

    function applyResolved(
        resolved: Record<
            string,
            Pick<RenderSection, 'images' | 'videos' | 'data'>
        >,
    ) {
        for (const s of sections.value) {
            const extra = resolved[s.key];
            if (extra) {
                s.images = extra.images ?? {};
                s.videos = extra.videos ?? {};
                s.data = extra.data ?? {};
            }
        }
    }

    function replaceAll(next: RenderSection[], nextLibrary?: LibraryMap) {
        const before = serialize();
        sections.value = clone(next);
        if (nextLibrary) library.value = { ...library.value, ...nextLibrary };
        past.value = [...past.value.slice(-99), before];
        future.value = [];
        selection.blocks = [];
        if (!section(selection.section)) {
            selection.section = sections.value[0]?.key ?? null;
        }
    }

    async function publish(note: string): Promise<string> {
        await flush();
        if (saveState.value === 'error') {
            throw new Error(
                'Hay cambios que no se pudieron guardar. Revisa los avisos del panel derecho.',
            );
        }
        const response = await apiFetch(init.urls.publish, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ note }),
        });
        const json = await response.json().catch(() => ({}));
        if (response.status === 422) {
            errors.value = json.errors ?? {};
            const first = Object.values(json.errors ?? {})[0] as
                | string[]
                | undefined;
            throw new Error(
                first?.[0] ?? 'Revisa los avisos antes de publicar.',
            );
        }
        if (!response.ok) {
            throw new Error(json.message ?? 'No se pudo publicar.');
        }
        Object.assign(draft, json.draft);
        versions.value = json.versions;

        return json.message as string;
    }

    async function restoreVersion(id: number): Promise<string> {
        await flush();
        const response = await apiFetch(init.urls.restore(id), {
            method: 'POST',
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(json.message ?? 'No se pudo restaurar la versión.');
        }
        replaceAll(json.sections, json.library);
        Object.assign(draft, json.draft);
        saveState.value = 'saved';
        savedAt.value = new Date();

        return json.message as string;
    }

    async function discard(): Promise<string> {
        window.clearTimeout(saveTimer);
        if (saving) await saving;
        const response = await apiFetch(init.urls.discard, { method: 'POST' });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(json.message ?? 'No se pudo descartar.');
        }
        replaceAll(json.sections, json.library);
        Object.assign(draft, json.draft);
        saveState.value = 'saved';
        conflictMessage.value = null;

        return json.message as string;
    }

    // ------------------------------------------------------------------
    // Selección
    // ------------------------------------------------------------------

    function selectSection(key: string) {
        selection.section = key;
        selection.blocks = [];
        textEditing.value = null;
        cropping.value = null;
    }

    function selectBlock(sectionKey: string, id: string, additive = false) {
        if (selection.section !== sectionKey || !additive) {
            selection.section = sectionKey;
            selection.blocks = [id];
        } else if (selection.blocks.includes(id)) {
            selection.blocks = selection.blocks.filter((b) => b !== id);
        } else {
            selection.blocks = [...selection.blocks, id];
        }
        if (textEditing.value && textEditing.value !== id) {
            textEditing.value = null;
        }
        if (cropping.value && cropping.value !== id) cropping.value = null;
    }

    function clearSelection() {
        selection.blocks = [];
        textEditing.value = null;
        cropping.value = null;
    }

    // ------------------------------------------------------------------
    // Bloques
    // ------------------------------------------------------------------

    function mutateBlock(
        sectionKey: string,
        id: string,
        fn: (b: Block) => void,
    ) {
        const b = block(sectionKey, id);
        if (b) fn(b);
    }

    function updateBlock(
        sectionKey: string,
        id: string,
        patch: Partial<Block>,
    ) {
        commit(() =>
            mutateBlock(sectionKey, id, (b) => Object.assign(b, patch)),
        );
    }

    function updateProps(
        sectionKey: string,
        id: string,
        patch: Record<string, unknown>,
    ) {
        commit(() =>
            mutateBlock(sectionKey, id, (b) => {
                b.props = { ...b.props, ...patch };
            }),
        );
    }

    function updateStyle(
        sectionKey: string,
        id: string,
        patch: Partial<Block['style']>,
        record = true,
    ) {
        live(
            () =>
                mutateBlock(sectionKey, id, (b) => {
                    b.style = { ...b.style, ...patch };
                }),
            record,
        );
    }

    function updateMotion(
        sectionKey: string,
        id: string,
        patch: Partial<Block['motion']>,
        record = true,
    ) {
        live(
            () =>
                mutateBlock(sectionKey, id, (b) => {
                    b.motion = { ...b.motion, ...patch };
                }),
            record,
        );
    }

    function newBlock(
        type: BlockType,
        x: number,
        y: number,
        props: Record<string, unknown> = {},
    ): Block {
        const def = BLOCKS[type];
        const [w, h] = def.size;
        const dynamicDefaults = init.dynamic[type]?.defaults ?? {};

        return {
            id: uid(),
            type,
            x: clamp(x, 0, COLUMNS - Math.min(w, COLUMNS)),
            y: Math.max(0, y),
            w: Math.min(w, COLUMNS),
            h,
            z: 1,
            props: { ...clone(dynamicDefaults), ...clone(def.props), ...props },
            style: {
                align: 'start',
                radius: type === 'image' || type === 'video' ? '2xl' : 'none',
                shadow: 'none',
                opacity: 100,
                rotate: 0,
            },
            motion: {
                preset: type === 'image' ? 'zoom-in' : 'fade-up',
                delay: 0,
            },
            hideOn: [],
            locked: false,
        };
    }

    /** Agrega un bloque (en la posición dada o en el primer hueco libre). */
    function addBlock(
        sectionKey: string,
        type: BlockType,
        at?: { x: number; y: number },
        props: Record<string, unknown> = {},
    ): string | null {
        const s = section(sectionKey);
        if (!s) return null;
        const [w, h] = BLOCKS[type].size;
        const spot = at ?? freeSpot(s.layout.blocks, Math.min(w, COLUMNS), h);
        const b = newBlock(type, spot.x, spot.y, props);
        b.z = Math.max(0, ...s.layout.blocks.map((x) => x.z)) + 1;
        commit(() => s.layout.blocks.push(b));
        selectBlock(sectionKey, b.id);

        return b.id;
    }

    function deleteSelected() {
        const s = currentSection.value;
        if (!s || !selection.blocks.length) return;
        const ids = new Set(selection.blocks);
        commit(() => {
            s.layout.blocks = s.layout.blocks.filter(
                (b) => !ids.has(b.id) || b.locked,
            );
        });
        selection.blocks = [];
    }

    function duplicateSelected() {
        const s = currentSection.value;
        if (!s || !selection.blocks.length) return;
        const copies: Block[] = [];
        const maxZ = Math.max(0, ...s.layout.blocks.map((b) => b.z));
        selectedBlocks.value.forEach((b, i) => {
            const copy = clone(b);
            copy.id = uid();
            copy.locked = false;
            copy.z = maxZ + 1 + i;
            const below = b.y + b.h + 1;
            copy.y = Math.min(MAX_ROW, below);
            copies.push(copy);
        });
        commit(() => s.layout.blocks.push(...copies));
        selection.blocks = copies.map((b) => b.id);
    }

    function nudge(dx: number, dy: number) {
        const s = currentSection.value;
        if (!s) return;
        const movable = selectedBlocks.value.filter((b) => !b.locked);
        if (!movable.length) return;
        const minX = Math.min(...movable.map((b) => b.x));
        const maxRight = Math.max(...movable.map((b) => b.x + b.w));
        const minY = Math.min(...movable.map((b) => b.y));
        const ux = clamp(dx, -minX, COLUMNS - maxRight);
        const uy = Math.max(dy, -minY);
        if (!ux && !uy) return;
        commit(() =>
            movable.forEach((b) => {
                b.x += ux;
                b.y += uy;
            }),
        );
    }

    function resizeBy(dw: number, dh: number) {
        const b = primaryBlock.value;
        if (!b || b.locked || !selection.section) return;
        const w = clamp(b.w + dw, 1, COLUMNS - b.x);
        const h = clamp(b.h + dh, 1, MAX_HEIGHT);
        updateBlock(selection.section, b.id, { w, h });
    }

    function arrange(direction: 'forward' | 'backward' | 'front' | 'back') {
        const s = currentSection.value;
        if (!s || !selectedBlocks.value.length) return;
        const zs = s.layout.blocks.map((b) => b.z);
        commit(() =>
            selectedBlocks.value.forEach((b) => {
                switch (direction) {
                    case 'forward':
                        b.z = Math.min(999, b.z + 1);
                        break;
                    case 'backward':
                        b.z = Math.max(0, b.z - 1);
                        break;
                    case 'front':
                        b.z = Math.min(999, Math.max(...zs) + 1);
                        break;
                    case 'back':
                        b.z = Math.max(0, Math.min(...zs) - 1);
                        break;
                }
            }),
        );
    }

    function toggleLock() {
        const s = currentSection.value;
        if (!s || !selectedBlocks.value.length) return;
        const lock = !selectedBlocks.value.every((b) => b.locked);
        commit(() => selectedBlocks.value.forEach((b) => (b.locked = lock)));
    }

    function commitText(
        sectionKey: string,
        id: string,
        prop: string,
        value: string,
    ) {
        const b = block(sectionKey, id);
        if (!b || b.props[prop] === value) return;
        updateProps(sectionKey, id, { [prop]: value });
    }

    // ------------------------------------------------------------------
    // Secciones
    // ------------------------------------------------------------------

    function freshIds(
        layout: RenderSection['layout'],
    ): RenderSection['layout'] {
        const copy = clone(layout);
        copy.blocks = copy.blocks.map((b) => ({ ...b, id: uid() }));

        return copy;
    }

    function addSection(template: SectionTemplate, index?: number): string {
        const s: RenderSection = {
            id: null,
            key: uuid(),
            type: template.type,
            isVisible: true,
            content: clone(template.content),
            layout: freshIds(template.layout),
            images: {},
            videos: {},
            data: {},
        };
        // No repetir anclas al agregar la misma plantilla dos veces.
        if (
            s.layout.settings.anchor &&
            sections.value.some(
                (x) => x.layout.settings.anchor === s.layout.settings.anchor,
            )
        ) {
            s.layout.settings.anchor = '';
        }
        // Una portada nueva no compite con el H1 existente.
        if (h1Count.value > 0) {
            s.layout.blocks.forEach((b) => {
                if (b.type === 'heading' && b.props.level === 'h1') {
                    b.props.level = 'h2';
                }
            });
        }
        const at = index ?? sections.value.length;
        commit(() => sections.value.splice(at, 0, s));
        selectSection(s.key);
        void save();

        return s.key;
    }

    function duplicateSection(key: string) {
        const index = sections.value.findIndex((s) => s.key === key);
        const original = sections.value[index];
        if (!original) return;
        const copy: RenderSection = {
            ...clone(original),
            id: null,
            key: uuid(),
            layout: freshIds(original.layout),
        };
        copy.layout.settings.anchor = '';
        copy.layout.settings.name = original.layout.settings.name
            ? `${original.layout.settings.name} (copia)`
            : '';
        copy.layout.blocks.forEach((b) => {
            if (b.type === 'heading' && b.props.level === 'h1')
                b.props.level = 'h2';
        });
        // Los datos resueltos se conservan por id de bloque nuevo.
        const map = new Map(
            original.layout.blocks.map((b, i) => [
                b.id,
                copy.layout.blocks[i].id,
            ]),
        );
        copy.data = Object.fromEntries(
            Object.entries(original.data).map(([id, d]) => [
                map.get(id) ?? id,
                d,
            ]),
        );
        commit(() => sections.value.splice(index + 1, 0, copy));
        selectSection(copy.key);
    }

    function deleteSection(key: string) {
        commit(() => {
            sections.value = sections.value.filter((s) => s.key !== key);
        });
        if (selection.section === key) {
            selection.section = sections.value[0]?.key ?? null;
            selection.blocks = [];
        }
    }

    function toggleSectionVisible(key: string) {
        const s = section(key);
        if (!s) return;
        commit(() => (s.isVisible = !s.isVisible));
    }

    function moveSection(from: number, to: number) {
        if (from === to || to < 0 || to >= sections.value.length) return;
        commit(() => {
            const list = [...sections.value];
            const [moved] = list.splice(from, 1);
            list.splice(to, 0, moved);
            sections.value = list;
        });
    }

    function setSectionOrder(keys: string[]) {
        commit(() => {
            sections.value = keys
                .map((k) => section(k))
                .filter((s): s is RenderSection => s !== undefined);
        });
    }

    function updateSettings(
        key: string,
        patch: Partial<SectionSettings>,
        record = true,
    ) {
        const s = section(key);
        if (!s) return;
        live(() => {
            s.layout.settings = { ...s.layout.settings, ...patch };
        }, record);
    }

    // ------------------------------------------------------------------
    // Arrastrar y redimensionar (Pointer Events)
    // ------------------------------------------------------------------

    type Metrics = { col: number; row: number; gap: number; grid: DOMRect };

    function metrics(el: HTMLElement): Metrics | null {
        const grid = el.closest('.sb-grid');
        if (!(grid instanceof HTMLElement)) return null;
        const rect = grid.getBoundingClientRect();
        const gap = rect.width * 0.02;

        return { col: (rect.width + gap) / COLUMNS, row: gap, gap, grid: rect };
    }

    let frame = 0;
    function schedule(fn: () => void) {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(fn);
    }

    function computeGuides(
        sectionKey: string,
        moving: { x: number; y: number; w: number; h: number },
        ignore: string[],
    ) {
        const s = section(sectionKey);
        if (!s) return null;
        const others = s.layout.blocks.filter((b) => !ignore.includes(b.id));
        const xs = new Set<number>([0, COLUMNS / 2, COLUMNS]);
        const ys = new Set<number>();
        others.forEach((b) => {
            xs.add(b.x);
            xs.add(b.x + b.w);
            xs.add(b.x + b.w / 2);
            ys.add(b.y);
            ys.add(b.y + b.h);
            ys.add(b.y + b.h / 2);
        });
        const mx = [moving.x, moving.x + moving.w, moving.x + moving.w / 2];
        const my = [moving.y, moving.y + moving.h, moving.y + moving.h / 2];

        return {
            sectionKey,
            x: [...xs].filter(
                (v) => mx.includes(v) && v !== 0 && v !== COLUMNS,
            ),
            y: [...ys].filter((v) => my.includes(v)),
        };
    }

    function pointerDown(
        event: PointerEvent,
        sectionKey: string,
        b: Block,
        el: HTMLElement,
    ) {
        if (event.button !== 0 && event.pointerType === 'mouse') return;
        if (textEditing.value === b.id || cropping.value === b.id) return;
        if (textEditing.value) textEditing.value = null;

        const additive = event.shiftKey;
        const alreadySelected =
            selection.section === sectionKey && selection.blocks.includes(b.id);
        if (!alreadySelected || additive) {
            selectBlock(sectionKey, b.id, additive);
        }
        if (!canInteract.value || b.locked || additive) return;

        const m = metrics(el);
        if (!m) return;
        event.preventDefault();
        el.focus({ preventScroll: true });

        const ids = selection.blocks.filter(
            (id) => !block(sectionKey, id)?.locked,
        );
        const start = { x: event.clientX, y: event.clientY };
        const startBlocks = ids.map((id) => ({ ...block(sectionKey, id)! }));
        const primary =
            startBlocks.find((x) => x.id === b.id) ?? startBlocks[0];
        let dragging = false;
        let last = { ux: 0, uy: 0 };

        const move = (e: PointerEvent) => {
            const dx = e.clientX - start.x;
            const dy = e.clientY - start.y;
            if (!dragging && Math.hypot(dx, dy) < 4) return;
            dragging = true;
            schedule(() => {
                const minX = Math.min(...startBlocks.map((x) => x.x));
                const maxRight = Math.max(...startBlocks.map((x) => x.x + x.w));
                const minY = Math.min(...startBlocks.map((x) => x.y));
                const ux = clamp(
                    Math.round(dx / m.col),
                    -minX,
                    COLUMNS - maxRight,
                );
                const uy = Math.max(Math.round(dy / m.row), -minY);
                last = { ux, uy };
                interaction.value = {
                    kind: 'drag',
                    sectionKey,
                    ids,
                    dx: dx / zoom.value,
                    dy: dy / zoom.value,
                    ux,
                    uy,
                };
                ghost.value = {
                    sectionKey,
                    rects: startBlocks.map((x) => ({
                        x: x.x + ux,
                        y: x.y + uy,
                        w: x.w,
                        h: x.h,
                    })),
                };
                guides.value = computeGuides(
                    sectionKey,
                    {
                        x: primary.x + ux,
                        y: primary.y + uy,
                        w: primary.w,
                        h: primary.h,
                    },
                    ids,
                );
            });
        };

        const end = (e: PointerEvent) => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', end);
            window.removeEventListener('pointercancel', end);
            window.removeEventListener('keydown', cancel);
            cancelAnimationFrame(frame);
            if (dragging && e.type === 'pointerup' && (last.ux || last.uy)) {
                commit(() =>
                    ids.forEach((id) => {
                        const target = block(sectionKey, id);
                        const origin = startBlocks.find((x) => x.id === id);
                        if (target && origin) {
                            target.x = origin.x + last.ux;
                            target.y = Math.min(MAX_ROW, origin.y + last.uy);
                        }
                    }),
                );
            }
            interaction.value = null;
            ghost.value = null;
            guides.value = null;
        };

        const cancel = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                last = { ux: 0, uy: 0 };
                end(new PointerEvent('pointercancel'));
            }
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', end);
        window.addEventListener('pointercancel', end);
        window.addEventListener('keydown', cancel);
    }

    function resizeStart(
        event: PointerEvent,
        sectionKey: string,
        b: Block,
        handle: string,
        el: HTMLElement,
    ) {
        if (!canInteract.value || b.locked) return;
        const m = metrics(el);
        if (!m) return;
        event.preventDefault();
        (event.target as HTMLElement).setPointerCapture?.(event.pointerId);

        const start = { x: event.clientX, y: event.clientY };
        const rect = el.getBoundingClientRect();
        const origin = { ...b };
        const ratio = rect.width / Math.max(1, rect.height);
        let target = { x: b.x, y: b.y, w: b.w, h: b.h };

        const move = (e: PointerEvent) => {
            schedule(() => {
                let dl = 0;
                let dr = 0;
                let dt = 0;
                let db = 0;
                const dx = e.clientX - start.x;
                const dy = e.clientY - start.y;
                if (handle.includes('e')) dr = dx;
                if (handle.includes('w')) dl = dx;
                if (handle.includes('s')) db = dy;
                if (handle.includes('n')) dt = dy;

                // Mayúsculas en una esquina: conserva la proporción.
                if (e.shiftKey && handle.length === 2) {
                    const width = rect.width + dr - dl;
                    const height = width / ratio;
                    if (handle.includes('s')) db = height - rect.height;
                    else dt = rect.height - height;
                }

                const minW = m.col - m.gap;
                const width = Math.max(minW, rect.width + dr - dl);
                const height = Math.max(m.row, rect.height + db - dt);
                const left = handle.includes('w') ? rect.width - width : 0;
                const top = handle.includes('n') ? rect.height - height : 0;

                let x =
                    origin.x +
                    (handle.includes('w') ? Math.round(dl / m.col) : 0);
                let right =
                    origin.x +
                    origin.w +
                    (handle.includes('e') ? Math.round(dr / m.col) : 0);
                let y =
                    origin.y +
                    (handle.includes('n') ? Math.round(dt / m.row) : 0);
                let bottom =
                    origin.y +
                    origin.h +
                    (handle.includes('s') ? Math.round(db / m.row) : 0);
                x = clamp(x, 0, origin.x + origin.w - 1);
                right = clamp(right, x + 1, COLUMNS);
                y = clamp(y, 0, origin.y + origin.h - 1);
                bottom = clamp(bottom, y + 1, y + MAX_HEIGHT);
                target = { x, y, w: right - x, h: bottom - y };

                interaction.value = {
                    kind: 'resize',
                    sectionKey,
                    id: b.id,
                    dx: left / zoom.value,
                    dy: top / zoom.value,
                    width: width / zoom.value,
                    height: height / zoom.value,
                    rect: target,
                };
                ghost.value = { sectionKey, rects: [target] };
                guides.value = computeGuides(sectionKey, target, [b.id]);
            });
        };

        const end = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', end);
            window.removeEventListener('pointercancel', end);
            cancelAnimationFrame(frame);
            if (
                target.x !== origin.x ||
                target.y !== origin.y ||
                target.w !== origin.w ||
                target.h !== origin.h
            ) {
                updateBlock(sectionKey, b.id, target);
            }
            interaction.value = null;
            ghost.value = null;
            guides.value = null;
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', end);
        window.addEventListener('pointercancel', end);
    }

    function transient(id: string) {
        const i = interaction.value;
        if (!i) return null;
        if (i.kind === 'drag' && i.ids.includes(id)) {
            return { dx: i.dx, dy: i.dy, width: null, height: null };
        }
        if (i.kind === 'resize' && i.id === id) {
            return { dx: i.dx, dy: i.dy, width: i.width, height: i.height };
        }

        return null;
    }

    function doubleClick(sectionKey: string, b: Block) {
        selectBlock(sectionKey, b.id);
        if (b.locked) return;
        if (BLOCKS[b.type]?.textProp) {
            textEditing.value = b.id;
        } else if (
            b.type === 'image' &&
            typeof b.props.mediaId === 'number' &&
            canInteract.value
        ) {
            cropping.value = b.id;
        }
    }

    function updateCrop(
        sectionKey: string,
        id: string,
        patch: { focalX?: number; focalY?: number; zoom?: number },
        record: boolean,
    ) {
        live(
            () =>
                mutateBlock(sectionKey, id, (b) => {
                    b.props = { ...b.props, ...patch };
                }),
            record,
        );
    }

    // ------------------------------------------------------------------
    // Teclado
    // ------------------------------------------------------------------

    function onKeydown(event: KeyboardEvent) {
        const mod = event.ctrlKey || event.metaKey;
        const key = event.key.toLowerCase();

        if (mod && key === 's') {
            event.preventDefault();
            void save();

            return;
        }
        if (isTyping(event.target)) return;

        if (mod && key === 'z') {
            event.preventDefault();
            if (event.shiftKey) redo();
            else undo();

            return;
        }
        if (mod && key === 'y') {
            event.preventDefault();
            redo();

            return;
        }
        if (!selection.section) return;

        if (event.key === 'Escape') {
            if (cropping.value) cropping.value = null;
            else clearSelection();

            return;
        }
        if (!selection.blocks.length) return;

        if (mod && key === 'd') {
            event.preventDefault();
            duplicateSelected();
        } else if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            deleteSelected();
        } else if (mod && event.key === ']') {
            event.preventDefault();
            arrange(event.shiftKey ? 'front' : 'forward');
        } else if (mod && event.key === '[') {
            event.preventDefault();
            arrange(event.shiftKey ? 'back' : 'backward');
        } else if (event.key.startsWith('Arrow') && canInteract.value) {
            event.preventDefault();
            const step = event.shiftKey ? 4 : 1;
            const dir = {
                ArrowLeft: [-1, 0],
                ArrowRight: [1, 0],
                ArrowUp: [0, -1],
                ArrowDown: [0, 1],
            }[event.key] ?? [0, 0];
            if (event.altKey) {
                resizeBy(dir[0] * step, dir[1] * step);
            } else {
                nudge(dir[0] * step, dir[1] * step);
            }
        } else if (event.key === 'Enter' && primaryBlock.value) {
            event.preventDefault();
            doubleClick(selection.section, primaryBlock.value);
        }
    }

    // El guardado pendiente no se pierde al cerrar la pestaña.
    function onBeforeUnload(event: BeforeUnloadEvent) {
        if (saveState.value === 'dirty' || saveState.value === 'saving') {
            void save();
            event.preventDefault();
        }
    }

    watch(device, () => {
        cropping.value = null;
        textEditing.value = null;
    });

    return {
        sections,
        library,
        draft,
        versions,
        selection,
        device,
        zoomMode,
        fitZoom,
        zoom,
        textEditing,
        cropping,
        interaction,
        ghost,
        guides,
        dropTarget,
        uploads,
        saveState,
        savedAt,
        errors,
        conflictMessage,
        past,
        future,
        changeTick,
        currentSection,
        selectedBlocks,
        primaryBlock,
        h1Count,
        canInteract,
        dynamic: init.dynamic,
        section,
        block,
        commit,
        live,
        undo,
        redo,
        save,
        flush,
        publish,
        restoreVersion,
        discard,
        selectSection,
        selectBlock,
        clearSelection,
        updateBlock,
        updateProps,
        updateStyle,
        updateMotion,
        addBlock,
        newBlock,
        deleteSelected,
        duplicateSelected,
        nudge,
        resizeBy,
        arrange,
        toggleLock,
        commitText,
        addSection,
        duplicateSection,
        deleteSection,
        toggleSectionVisible,
        moveSection,
        setSectionOrder,
        updateSettings,
        pointerDown,
        resizeStart,
        transient,
        doubleClick,
        updateCrop,
        onKeydown,
        onBeforeUnload,
        metrics,
    };
}

export type Editor = ReturnType<typeof createEditor>;
