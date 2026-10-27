import { reactive } from 'vue';
import { apiFetch } from '@/lib/http';
import { normalizeDocument } from './migrate';
import { slotAccepts } from './schema';
import { applyLayout, cloneWithNewIds, createElement, locate } from './tree';
import type { Breakpoint, MotionConfig, ResolvedMedia, StudioDocument, StudioNode, StudioResolved, StyleValue } from './types';

/**
 * Estado del editor de Studio.
 *
 * - Toda mutación pasa por `commit()` → historial deshacer/rehacer
 *   (con agrupación de cambios continuos, p. ej. arrastrar un slider).
 * - Autoguardado con debounce; la `revision` del servidor evita que dos
 *   sesiones se sobrescriban (409 → estado `conflict`).
 * - Los datos dinámicos (publicaciones, páginas, medios) se vuelven a
 *   resolver en el servidor cuando cambia su configuración.
 */

export type SaveStatus = 'saved' | 'dirty' | 'saving' | 'error' | 'conflict';

export interface StudioStoreOptions {
    document: StudioDocument;
    resolved: StudioResolved;
    revision: number;
    saveUrl: string;
    resolveUrl: string;
}

const HISTORY_LIMIT = 120;
const AUTOSAVE_MS = 1200;

export function createStudioStore(options: StudioStoreOptions) {
    const state = reactive({
        doc: normalizeDocument(options.document),
        resolved: options.resolved as StudioResolved,
        selectedId: null as string | null,
        bp: 'base' as Breakpoint,
        past: [] as string[],
        future: [] as string[],
        revision: options.revision,
        status: 'saved' as SaveStatus,
        lastSavedAt: null as string | null,
        errors: {} as Record<string, string>,
        changeCount: 0,
    });

    let lastCoalesce: { key: string; at: number } | null = null;
    let saveTimer: ReturnType<typeof setTimeout> | undefined;
    let resolveTimer: ReturnType<typeof setTimeout> | undefined;
    let saving: Promise<void> | null = null;
    let lastResolveKey = resolveKey(options.document);

    function snapshot(): string {
        return JSON.stringify(state.doc);
    }

    /** Registra el estado actual en el historial (antes de mutar). */
    function pushHistory(coalesce?: string): void {
        const now = Date.now();
        if (coalesce && lastCoalesce && lastCoalesce.key === coalesce && now - lastCoalesce.at < 700) {
            lastCoalesce.at = now;

            return;
        }
        lastCoalesce = coalesce ? { key: coalesce, at: now } : null;
        state.past.push(snapshot());
        if (state.past.length > HISTORY_LIMIT) state.past.shift();
        state.future = [];
    }

    function changed(): void {
        state.changeCount++;
        state.status = state.status === 'conflict' ? 'conflict' : 'dirty';
        scheduleSave();
        scheduleResolve();
    }

    function commit(mutate: (doc: StudioDocument) => void, coalesce?: string): void {
        pushHistory(coalesce);
        mutate(state.doc);
        changed();
    }

    /** Para operaciones externas (drag & drop) que mutan el árbol directamente. */
    function beginExternal(): void {
        pushHistory();
    }
    function endExternal(): void {
        changed();
    }

    function undo(): void {
        const prev = state.past.pop();
        if (!prev) return;
        state.future.push(snapshot());
        state.doc = JSON.parse(prev);
        lastCoalesce = null;
        if (state.selectedId && !locate(state.doc, state.selectedId)) state.selectedId = null;
        changed();
    }

    function redo(): void {
        const next = state.future.pop();
        if (!next) return;
        state.past.push(snapshot());
        state.doc = JSON.parse(next);
        lastCoalesce = null;
        changed();
    }

    // ------------------------------------------------------------------
    // Guardado
    // ------------------------------------------------------------------
    function scheduleSave(): void {
        clearTimeout(saveTimer);
        if (state.status === 'conflict') return;
        saveTimer = setTimeout(() => void save(), AUTOSAVE_MS);
    }

    async function save(force = false): Promise<void> {
        clearTimeout(saveTimer);
        if (saving) {
            await saving;
            if (state.status !== 'dirty' && !force) return;
        }
        if (state.status === 'saved' && !force) return;

        const sentChangeCount = state.changeCount;
        state.status = 'saving';
        saving = (async () => {
            try {
                const response = await apiFetch(options.saveUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ document: state.doc, revision: state.revision, force }),
                });
                const payload = await response.json().catch(() => ({}));

                if (response.status === 409) {
                    state.status = 'conflict';
                    return;
                }
                if (response.status === 422) {
                    state.status = 'error';
                    state.errors = payload.errors ?? {};
                    return;
                }
                if (!response.ok) {
                    state.status = 'error';
                    state.errors = { general: payload.message ?? 'No se pudo guardar el borrador.' };
                    return;
                }

                state.revision = payload.revision;
                state.lastSavedAt = payload.savedAt;
                state.errors = {};
                state.status = state.changeCount === sentChangeCount ? 'saved' : 'dirty';
                if (state.status === 'dirty') scheduleSave();
            } catch {
                state.status = 'error';
                state.errors = { general: 'Sin conexión. Se reintentará al hacer el próximo cambio.' };
            } finally {
                saving = null;
            }
        })();

        await saving;
    }

    /** Sustituye el documento (restaurar versión, descartar, recargar del servidor). */
    function replaceDocument(doc: StudioDocument, revision: number): void {
        pushHistory();
        state.doc = normalizeDocument(doc);
        state.revision = revision;
        state.status = 'saved';
        state.errors = {};
        if (state.selectedId && !locate(doc, state.selectedId)) state.selectedId = null;
        scheduleResolve(true);
    }

    // ------------------------------------------------------------------
    // Datos dinámicos
    // ------------------------------------------------------------------
    function scheduleResolve(force = false): void {
        const key = resolveKey(state.doc);
        if (!force && key === lastResolveKey) return;
        lastResolveKey = key;
        clearTimeout(resolveTimer);
        resolveTimer = setTimeout(async () => {
            const response = await apiFetch(options.resolveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ document: state.doc }),
            });
            if (response.ok) {
                const resolved = (await response.json()) as StudioResolved;
                // Conserva medios elegidos que el servidor aún esté procesando.
                state.resolved = { ...resolved, media: { ...state.resolved.media, ...resolved.media } };
            }
        }, 350);
    }

    function registerMedia(media: ResolvedMedia): void {
        state.resolved = { ...state.resolved, media: { ...state.resolved.media, [String(media.id)]: media } };
    }

    // ------------------------------------------------------------------
    // Operaciones sobre nodos
    // ------------------------------------------------------------------
    function select(id: string | null): void {
        state.selectedId = id;
    }

    function selected(): StudioNode | null {
        return state.selectedId ? (locate(state.doc, state.selectedId)?.node ?? null) : null;
    }

    function setProp(id: string, key: string, value: unknown, coalesce = false): void {
        commit((doc) => {
            const loc = locate(doc, id);
            if (!loc) return;
            if (key === 'layout' && loc.node.slots) applyLayout(loc.node, String(value));
            else loc.node.props[key] = value;
        }, coalesce ? `prop:${id}:${key}` : undefined);
    }

    function setStyle(id: string, key: string, value: StyleValue | undefined, bp: Breakpoint = state.bp, coalesce = true): void {
        commit((doc) => {
            const node = locate(doc, id)?.node;
            if (!node) return;
            node.style ??= {};
            const map = { ...(node.style[bp] ?? {}) };
            if (value === undefined) delete map[key];
            else map[key] = value;
            if (Object.keys(map).length) node.style[bp] = map;
            else delete node.style[bp];
        }, coalesce ? `style:${id}:${key}:${bp}` : undefined);
    }

    function resetStyles(id: string, bp?: Breakpoint): void {
        commit((doc) => {
            const node = locate(doc, id)?.node;
            if (!node) return;
            if (bp) delete node.style[bp];
            else node.style = {};
        });
    }

    function setMotion(id: string, motion: MotionConfig | null, coalesce = true): void {
        commit((doc) => {
            const node = locate(doc, id)?.node;
            if (node) node.motion = motion;
        }, coalesce ? `motion:${id}` : undefined);
    }

    function toggleHidden(id: string): void {
        commit((doc) => {
            const node = locate(doc, id)?.node;
            if (node) node.hidden = !node.hidden;
        });
    }

    function remove(id: string): void {
        commit((doc) => {
            const loc = locate(doc, id);
            if (!loc) return;
            loc.list.splice(loc.index, 1);
        });
        if (state.selectedId === id) state.selectedId = null;
    }

    function duplicate(id: string): void {
        let newId: string | null = null;
        commit((doc) => {
            const loc = locate(doc, id);
            if (!loc) return;
            const copy = cloneWithNewIds(loc.node);
            newId = copy.id;
            loc.list.splice(loc.index + 1, 0, copy);
        });
        if (newId) state.selectedId = newId;
    }

    /** Mueve arriba/abajo dentro de su lista; si llega al borde de un slot, salta a la sección vecina compatible. */
    function move(id: string, dir: -1 | 1): void {
        const loc = locate(state.doc, id);
        if (!loc) return;
        const target = loc.index + dir;
        if (target >= 0 && target < loc.list.length) {
            commit((doc) => {
                const l = locate(doc, id)!;
                const [node] = l.list.splice(l.index, 1);
                l.list.splice(target, 0, node);
            });
        }
    }

    function insertSection(section: StudioNode, index?: number): void {
        commit((doc) => {
            const at = index ?? doc.sections.length;
            doc.sections.splice(at, 0, section);
        });
        state.selectedId = section.id;
    }

    /** Inserta un elemento en un slot (por defecto, tras la selección actual). */
    function insertElement(type: string, sectionId: string, slot: string, index?: number, props: Record<string, unknown> = {}): StudioNode | null {
        const loc = locate(state.doc, sectionId);
        if (!loc?.node.slots || !slotAccepts(loc.node.type, slot).includes(type)) return null;
        const element = createElement(type, props);
        commit((doc) => {
            const section = locate(doc, sectionId)!.node;
            const list = (section.slots![slot] ??= []);
            list.splice(index ?? list.length, 0, element);
        });
        state.selectedId = element.id;

        return element;
    }

    function moveToSlot(id: string, sectionId: string, slot: string, index: number): void {
        commit((doc) => {
            const loc = locate(doc, id);
            const target = locate(doc, sectionId)?.node;
            if (!loc || !target?.slots || !slotAccepts(target.type, slot).includes(loc.node.type)) return;
            const [node] = loc.list.splice(loc.index, 1);
            target.slots[slot].splice(index, 0, node);
        });
    }

    return {
        state,
        commit,
        beginExternal,
        endExternal,
        undo,
        redo,
        save,
        replaceDocument,
        registerMedia,
        select,
        selected,
        setProp,
        setStyle,
        resetStyles,
        setMotion,
        toggleHidden,
        remove,
        duplicate,
        move,
        insertSection,
        insertElement,
        moveToSlot,
    };
}

export type StudioStore = ReturnType<typeof createStudioStore>;

/**
 * Clave de lo que requiere resolución en el servidor: secciones dinámicas,
 * medios y vínculos a páginas. Si no cambia, no hay petición.
 */
function resolveKey(doc: StudioDocument): string {
    const parts: unknown[] = [];
    for (const section of doc.sections) {
        if (['posts', 'categories', 'timeline', 'social', 'gallery'].includes(section.type)) {
            parts.push(section.id, section.type, section.props);
        }
        for (const children of Object.values(section.slots ?? {})) {
            for (const child of children) {
                const p = child.props;
                parts.push(child.type === 'social-links' ? 'social' : null, p.media ?? null, p.poster ?? null, p.captions ?? null, p.bind ?? null, p.bindPage ?? null, p.page ?? null);
            }
        }
    }

    return JSON.stringify(parts);
}
