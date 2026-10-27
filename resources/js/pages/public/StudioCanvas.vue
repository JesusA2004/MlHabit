<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpLeft, Copy, EyeOff, ImagePlus, Plus, Trash2 } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, shallowRef } from 'vue';
import PageRenderer from '@/components/blocks/PageRenderer.vue';
import { replayReveals } from '@/lib/reveal';
import type { CanvasAction, FromCanvas, ToCanvas } from '@/studio/bridge';
import { isStudioMessage } from '@/studio/bridge';
import { nodeDef } from '@/studio/schema';
import type { StudioDocument, StudioPayload, StudioResolved } from '@/studio/types';
import type { SocialLinkSummary } from '@/types';

/**
 * Canvas de Studio (dentro de un iframe aislado). Renderiza la página con
 * los mismos componentes del sitio público; el documento llega del editor
 * por postMessage. Bloquea toda navegación y traduce clics en selección.
 */
const props = defineProps<{ studio: StudioPayload; social: SocialLinkSummary[] }>();

const doc = shallowRef<StudioDocument>(props.studio.document);
const resolved = shallowRef<StudioResolved>(props.studio.resolved);
const interactive = ref(true);
const label = ref<string | null>(null);
const interact = ref(false);
const showSpacing = ref(true);
const selectedId = ref<string | null>(null);
const hoverId = ref<string | null>(null);
const editingId = ref<string | null>(null);

interface Box {
    top: number;
    left: number;
    width: number;
    height: number;
}
const boxes = reactive<{ selected: Box | null; hover: Box | null; padTop: number; padBottom: number }>({
    selected: null,
    hover: null,
    padTop: 0,
    padBottom: 0,
});

const selectedType = computed(() => {
    const el = selectedId.value ? findEl(selectedId.value) : null;

    return el?.dataset.type ?? '';
});
const selectedLabel = computed(() => nodeDef(selectedType.value)?.label ?? selectedType.value);
const selectedIsSection = computed(() => !!selectedId.value && findEl(selectedId.value)?.tagName === 'SECTION');
const selectedIsMedia = computed(() => ['image', 'video'].includes(selectedType.value));

function send(message: FromCanvas) {
    window.parent.postMessage(message, window.location.origin);
}

function findEl(id: string): HTMLElement | null {
    return document.querySelector<HTMLElement>(`[data-n="${CSS.escape(id)}"]`);
}

function rect(el: HTMLElement): Box {
    const r = el.getBoundingClientRect();

    return { top: r.top, left: r.left, width: r.width, height: r.height };
}

let raf = 0;
function measure() {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => {
        const sel = selectedId.value ? findEl(selectedId.value) : null;
        boxes.selected = sel ? rect(sel) : null;
        if (sel && sel.tagName === 'SECTION') {
            const cs = getComputedStyle(sel);
            boxes.padTop = parseFloat(cs.paddingTop) || 0;
            boxes.padBottom = parseFloat(cs.paddingBottom) || 0;
        } else {
            boxes.padTop = boxes.padBottom = 0;
        }
        const hov = hoverId.value && hoverId.value !== selectedId.value ? findEl(hoverId.value) : null;
        boxes.hover = hov ? rect(hov) : null;
    });
}

function scrollIntoView(id: string) {
    const el = findEl(id);
    if (!el) return;
    const r = el.getBoundingClientRect();
    if (r.top < 60 || r.top > window.innerHeight - 80) {
        el.scrollIntoView({ block: r.height > window.innerHeight ? 'start' : 'center', behavior: 'smooth' });
    }
}

// ---------------------------------------------------------------------
// Mensajes del editor
// ---------------------------------------------------------------------
function onMessage(event: MessageEvent) {
    if (!isStudioMessage<ToCanvas>(event)) return;
    const data = event.data as ToCanvas;

    switch (data.type) {
        case 'studio:render':
            doc.value = data.document;
            resolved.value = data.resolved;
            interactive.value = data.interactive;
            label.value = data.label;
            if (!data.interactive) selectedId.value = null;
            void nextTick(measure);
            break;
        case 'studio:select':
            selectedId.value = data.id;
            if (data.id && data.scroll) void nextTick(() => scrollIntoView(data.id!));
            void nextTick(measure);
            break;
        case 'studio:replay': {
            const root = data.id ? findEl(data.id) : document;
            if (root) replayReveals(root);
            break;
        }
        case 'studio:mode':
            interact.value = data.interact;
            showSpacing.value = data.spacing;
            break;
    }
}

// ---------------------------------------------------------------------
// Interacción en el canvas
// ---------------------------------------------------------------------
function nodeFrom(target: EventTarget | null): HTMLElement | null {
    return target instanceof Element ? target.closest<HTMLElement>('[data-n]') : null;
}

function onClickCapture(event: MouseEvent) {
    const target = event.target as Element | null;
    if (target?.closest('[data-canvas-ui]')) return;
    if (editingId.value && target?.closest('[contenteditable]')) return;

    // Nunca navegar desde el canvas.
    const anchor = target?.closest('a');
    if (anchor) event.preventDefault();

    if (interact.value && !anchor) return; // modo interacción: botones/carruseles funcionan
    event.preventDefault();
    event.stopPropagation();
    if (!interactive.value) return;

    const add = target?.closest<HTMLElement>('[data-slot-add]');
    if (add?.dataset.section && add.dataset.slot) {
        send({ type: 'studio:add', sectionId: add.dataset.section, slot: add.dataset.slot });

        return;
    }

    let node = nodeFrom(target);
    if (node && event.altKey && node.tagName !== 'SECTION') node = node.closest('section[data-n]');
    const id = node?.dataset.n ?? null;
    selectedId.value = id;
    send({ type: 'studio:select', id });
    measure();

    if (id && target?.closest('[data-media-empty]')) {
        send({ type: 'studio:action', action: 'media', id });
    }
}

function onDblClick(event: MouseEvent) {
    if (!interactive.value || interact.value) return;
    const node = nodeFrom(event.target);
    const editable = node?.querySelector<HTMLElement>('[data-inline]');
    if (!node || !editable || node.tagName === 'SECTION') return;
    startInline(node.dataset.n!, editable);
}

function startInline(id: string, el: HTMLElement) {
    editingId.value = id;
    const original = el.innerText;
    try {
        el.contentEditable = 'plaintext-only';
    } catch {
        el.contentEditable = 'true';
    }
    if (el.contentEditable !== 'plaintext-only') el.contentEditable = 'true';
    el.focus();
    const range = document.createRange();
    range.selectNodeContents(el);
    const sel = window.getSelection();
    sel?.removeAllRanges();
    sel?.addRange(range);

    const multiline = el.hasAttribute('data-inline-multiline') || el.tagName.match(/^H[1-4]$/) !== null;

    const finish = (commit: boolean) => {
        el.removeEventListener('blur', onBlur);
        el.removeEventListener('keydown', onKey);
        el.removeAttribute('contenteditable');
        editingId.value = null;
        const value = el.innerText.replace(/ /g, ' ').replace(/\n{3,}/g, '\n\n').trim();
        if (commit && value !== original.trim()) {
            send({ type: 'studio:inline', id, value });
        } else {
            el.innerText = original;
        }
    };
    const onBlur = () => finish(true);
    const onKey = (e: KeyboardEvent) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            finish(false);
        } else if (e.key === 'Enter' && (!multiline || e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            el.blur();
        }
    };
    el.addEventListener('blur', onBlur);
    el.addEventListener('keydown', onKey);
}

function onMouseMove(event: MouseEvent) {
    if (!interactive.value) return;
    const id = nodeFrom(event.target)?.dataset.n ?? null;
    if (id !== hoverId.value) {
        hoverId.value = id;
        measure();
    }
}

function onKeyDown(event: KeyboardEvent) {
    if (editingId.value) return;
    const target = event.target as HTMLElement;
    if (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return;
    const ctrl = event.ctrlKey || event.metaKey;
    const relevant = ctrl || event.key === 'Delete' || event.key === 'Backspace' || event.key === 'Escape' || (event.altKey && event.key.startsWith('Arrow'));
    if (!relevant) return;
    event.preventDefault();
    send({ type: 'studio:key', key: event.key, ctrl, shift: event.shiftKey, alt: event.altKey });
}

function action(name: CanvasAction) {
    if (selectedId.value) send({ type: 'studio:action', action: name, id: selectedId.value });
}

let resizeObserver: ResizeObserver | null = null;
onMounted(() => {
    window.addEventListener('message', onMessage);
    document.addEventListener('click', onClickCapture, true);
    document.addEventListener('dblclick', onDblClick, true);
    document.addEventListener('mousemove', onMouseMove, { passive: true });
    document.addEventListener('keydown', onKeyDown);
    document.addEventListener('submit', (e) => e.preventDefault(), true);
    window.addEventListener('scroll', measure, { passive: true });
    window.addEventListener('resize', measure);
    resizeObserver = new ResizeObserver(measure);
    resizeObserver.observe(document.body);
    send({ type: 'studio:ready' });
});

onBeforeUnmount(() => {
    window.removeEventListener('message', onMessage);
    document.removeEventListener('click', onClickCapture, true);
    document.removeEventListener('dblclick', onDblClick, true);
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('keydown', onKeyDown);
    window.removeEventListener('scroll', measure);
    window.removeEventListener('resize', measure);
    resizeObserver?.disconnect();
});

const toolbarTop = computed(() => {
    const b = boxes.selected;
    if (!b) return 0;

    return b.top > 44 ? b.top - 38 : Math.max(b.top + 6, 6);
});
</script>

<template>
    <PageRenderer
        :document="doc"
        :resolved="resolved"
        :social="social"
        :editing="interactive"
    />

    <!-- Capa de edición -->
    <div data-canvas-ui class="studio-overlay" aria-hidden="false">
        <div v-if="label" class="studio-banner">{{ label }}</div>

        <template v-if="interactive">
            <div
                v-if="boxes.hover"
                class="studio-box studio-box--hover"
                :style="{ top: `${boxes.hover.top}px`, left: `${boxes.hover.left}px`, width: `${boxes.hover.width}px`, height: `${boxes.hover.height}px` }"
            />
            <template v-if="boxes.selected">
                <div
                    class="studio-box studio-box--selected"
                    :style="{ top: `${boxes.selected.top}px`, left: `${boxes.selected.left}px`, width: `${boxes.selected.width}px`, height: `${boxes.selected.height}px` }"
                />
                <template v-if="showSpacing && selectedIsSection">
                    <div
                        v-if="boxes.padTop > 0"
                        class="studio-pad"
                        :style="{ top: `${boxes.selected.top}px`, left: `${boxes.selected.left}px`, width: `${boxes.selected.width}px`, height: `${boxes.padTop}px` }"
                    >
                        <span>{{ Math.round(boxes.padTop) }} px</span>
                    </div>
                    <div
                        v-if="boxes.padBottom > 0"
                        class="studio-pad"
                        :style="{ top: `${boxes.selected.top + boxes.selected.height - boxes.padBottom}px`, left: `${boxes.selected.left}px`, width: `${boxes.selected.width}px`, height: `${boxes.padBottom}px` }"
                    >
                        <span>{{ Math.round(boxes.padBottom) }} px</span>
                    </div>
                </template>

                <div
                    v-if="!editingId"
                    class="studio-toolbar"
                    :style="{ top: `${toolbarTop}px`, left: `${Math.max(boxes.selected.left, 6)}px` }"
                    role="toolbar"
                    :aria-label="`Acciones de ${selectedLabel}`"
                >
                    <span class="studio-toolbar-label">{{ selectedLabel }}</span>
                    <button v-if="!selectedIsSection" type="button" title="Seleccionar sección (Alt+clic)" @click="action('select-parent')"><ArrowUpLeft /></button>
                    <button v-if="selectedIsMedia" type="button" title="Cambiar multimedia" @click="action('media')"><ImagePlus /></button>
                    <button type="button" title="Subir (Alt+↑)" @click="action('up')"><ArrowUp /></button>
                    <button type="button" title="Bajar (Alt+↓)" @click="action('down')"><ArrowDown /></button>
                    <button type="button" title="Insertar después" @click="action('add-after')"><Plus /></button>
                    <button type="button" title="Duplicar (Ctrl+D)" @click="action('duplicate')"><Copy /></button>
                    <button type="button" title="Ocultar" @click="action('hide')"><EyeOff /></button>
                    <button type="button" title="Eliminar (Supr)" class="is-danger" @click="action('delete')"><Trash2 /></button>
                </div>
            </template>
        </template>
    </div>
</template>

<style>
.studio-overlay {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 2147483000;
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
}
.studio-box {
    position: fixed;
    pointer-events: none;
    border-radius: 2px;
}
.studio-box--hover {
    outline: 1px dashed rgb(199 170 125 / 0.9);
    outline-offset: -1px;
}
.studio-box--selected {
    outline: 2px solid #c7aa7d;
    outline-offset: -2px;
    box-shadow: 0 0 0 9999px rgb(0 0 0 / 0.04);
}
.studio-pad {
    position: fixed;
    pointer-events: none;
    background: repeating-linear-gradient(135deg, rgb(199 170 125 / 0.18) 0 6px, rgb(199 170 125 / 0.06) 6px 12px);
    display: flex;
    align-items: center;
    justify-content: center;
}
.studio-pad span {
    font-size: 11px;
    font-weight: 600;
    color: #0d0e10;
    background: #c7aa7d;
    padding: 1px 6px;
    border-radius: 4px;
}
.studio-toolbar {
    position: fixed;
    pointer-events: auto;
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 3px;
    border-radius: 8px;
    background: #0d0e10;
    color: #f4f1eb;
    box-shadow: 0 8px 24px -8px rgb(0 0 0 / 0.6);
    border: 1px solid rgb(199 170 125 / 0.5);
}
.studio-toolbar-label {
    padding: 0 8px 0 6px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #c7aa7d;
    white-space: nowrap;
}
.studio-toolbar button {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 6px;
}
.studio-toolbar button svg {
    width: 15px;
    height: 15px;
}
.studio-toolbar button:hover {
    background: rgb(244 241 235 / 0.12);
}
.studio-toolbar button.is-danger:hover {
    background: rgb(234 88 12 / 0.3);
}
.studio-banner {
    position: fixed;
    top: 12px;
    left: 50%;
    translate: -50% 0;
    padding: 6px 14px;
    border-radius: 999px;
    background: #c7aa7d;
    color: #0d0e10;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    box-shadow: 0 6px 20px -6px rgb(0 0 0 / 0.5);
}
[contenteditable] {
    outline: 2px dashed #c7aa7d !important;
    outline-offset: 4px;
    cursor: text;
}
</style>
