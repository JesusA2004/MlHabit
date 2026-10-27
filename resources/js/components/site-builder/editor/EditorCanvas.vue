<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Eye,
    EyeOff,
    MousePointerClick,
    Plus,
    Trash2,
} from '@lucide/vue';
import { useResizeObserver } from '@vueuse/core';
import { computed, inject, ref, watchEffect } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import { COLUMNS, rowsOf } from '../grid';
import { BLOCKS } from '../registry';
import SectionRenderer from '../SectionRenderer.vue';
import type { BlockType, Device, RenderSection } from '../types';
import { editorKey } from './keys';

/**
 * Lienzo del editor: la página real (mismo renderizador que el sitio) en un
 * marco del ancho del dispositivo elegido, con zoom, retícula, guías
 * inteligentes, sombra de destino al arrastrar y zonas para soltar
 * elementos, imágenes de la biblioteca o archivos del escritorio.
 */
const editor = inject(editorKey)!;

const emit = defineEmits<{
    addSection: [index: number];
    dropFiles: [
        files: File[],
        sectionKey: string,
        at: { x: number; y: number },
        targetBlock: string | null,
    ];
}>();

const WIDTHS: Record<Device, number> = {
    desktop: 1280,
    tablet: 834,
    mobile: 390,
};

const viewport = ref<HTMLElement | null>(null);
const frameWidth = computed(() => WIDTHS[editor.device.value]);

const viewportWidth = ref(1280);
useResizeObserver(viewport, (entries) => {
    viewportWidth.value = entries[0]?.contentRect.width ?? 1280;
});
// Ajustar al ancho disponible (también al cambiar de dispositivo).
watchEffect(() => {
    editor.fitZoom.value = Math.min(
        1,
        Math.max(0.2, (viewportWidth.value - 96) / frameWidth.value),
    );
});

const hovered = ref<string | null>(null);
const deleting = ref<RenderSection | null>(null);
const deleteOpen = ref(false);

function extraRows(section: RenderSection): number {
    const ghost = editor.ghost.value;
    const base = editor.canInteract.value ? 6 : 0;
    if (ghost?.sectionKey !== section.key) return base;
    const needed = rowsOf(ghost.rects) - rowsOf(section.layout.blocks);

    return Math.max(base, needed + 4);
}

function onSectionPointerDown(event: PointerEvent, section: RenderSection) {
    const target = event.target as HTMLElement;
    if (target.closest('[data-block-id], [data-sb-chrome]')) return;
    editor.selectSection(section.key);
}

function guideLeft(x: number): string {
    const inner = x > 0 && x < COLUMNS ? ' - 1cqi' : '';

    return `calc(${x} * (100% + 2cqi) / ${COLUMNS}${inner})`;
}

// ----- Soltar elementos, imágenes y archivos -------------------------
type DragPayload =
    | { kind: 'block'; type: BlockType }
    | { kind: 'media'; id: number }
    | null;

const payload = inject<{ value: DragPayload }>('sb-drag-payload', {
    value: null,
});

function gridPoint(event: DragEvent, sectionEl: HTMLElement) {
    const grid = sectionEl.querySelector('.sb-grid');
    if (!(grid instanceof HTMLElement)) return null;
    const m = editor.metrics(grid);
    const rect = grid.getBoundingClientRect();
    const col = m?.col ?? rect.width / COLUMNS;
    const row = m?.row ?? rect.width * 0.02;

    return {
        x: Math.floor((event.clientX - rect.left) / col),
        y: Math.max(0, Math.floor((event.clientY - rect.top) / row)),
    };
}

function onDragOver(event: DragEvent, section: RenderSection) {
    if (!editor.canInteract.value) return;
    const isFile = event.dataTransfer?.types.includes('Files');
    if (!payload.value && !isFile) return;
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';

    const el = event.currentTarget as HTMLElement;
    const imageEl = (event.target as HTMLElement).closest(
        '[data-sb-kind="image"]',
    );
    const wantsImage = isFile || payload.value?.kind === 'media';
    editor.dropTarget.value =
        wantsImage && imageEl instanceof HTMLElement
            ? (imageEl.dataset.blockId ?? null)
            : null;

    const point = gridPoint(event, el);
    if (!point || editor.dropTarget.value) {
        editor.ghost.value = null;

        return;
    }
    const [w, h] =
        payload.value?.kind === 'block'
            ? BLOCKS[payload.value.type].size
            : BLOCKS.image.size;
    const x = Math.min(
        Math.max(0, point.x - Math.floor(w / 2)),
        COLUMNS - Math.min(w, COLUMNS),
    );
    editor.ghost.value = {
        sectionKey: section.key,
        rects: [{ x, y: Math.max(0, point.y - 1), w: Math.min(w, COLUMNS), h }],
    };
}

function onDragLeave(event: DragEvent) {
    const related = event.relatedTarget as Node | null;
    if (related && (event.currentTarget as HTMLElement).contains(related))
        return;
    editor.ghost.value = null;
    editor.dropTarget.value = null;
}

function onDrop(event: DragEvent, section: RenderSection) {
    if (!editor.canInteract.value) return;
    event.preventDefault();
    const ghost = editor.ghost.value?.rects[0];
    const at = ghost
        ? { x: ghost.x, y: ghost.y }
        : { x: 0, y: rowsOf(section.layout.blocks) };
    const target = editor.dropTarget.value;
    editor.ghost.value = null;
    editor.dropTarget.value = null;

    const files = [...(event.dataTransfer?.files ?? [])].filter((f) =>
        f.type.startsWith('image/'),
    );
    if (files.length) {
        emit('dropFiles', files, section.key, at, target);

        return;
    }

    const data = payload.value;
    payload.value = null;
    if (!data) return;

    if (data.kind === 'media') {
        if (target) {
            editor.updateProps(section.key, target, {
                mediaId: data.id,
                focalX: 50,
                focalY: 50,
                zoom: 1,
            });
            editor.selectBlock(section.key, target);
        } else {
            editor.addBlock(section.key, 'image', at, { mediaId: data.id });
        }
    } else {
        editor.addBlock(section.key, data.type, at);
    }
}

function confirmDelete(section: RenderSection) {
    deleting.value = section;
    deleteOpen.value = true;
}

function sectionLabel(section: RenderSection): string {
    return section.layout.settings.name || 'Sección';
}

const showGrid = computed(
    () => editor.interaction.value !== null || editor.ghost.value !== null,
);
</script>

<template>
    <div
        ref="viewport"
        class="sb-editor-viewport relative min-h-0 flex-1 overflow-auto"
        @pointerdown.self="editor.clearSelection()"
    >
        <div
            class="mx-auto py-10 transition-[width] duration-300"
            :style="{ width: `${frameWidth * editor.zoom.value}px` }"
            @pointerdown.self="editor.clearSelection()"
        >
            <div
                class="sb-editor-frame origin-top-left overflow-hidden bg-background shadow-[0_1px_2px_rgb(35_30_26/0.06),0_24px_64px_-24px_rgb(35_30_26/0.28)]"
                :class="
                    editor.device.value !== 'desktop' &&
                    'rounded-[28px] ring-8 ring-espresso/90'
                "
                :style="{
                    width: `${frameWidth}px`,
                    zoom: editor.zoom.value,
                    '--sb-inv-zoom': String(1 / editor.zoom.value),
                }"
            >
                <div class="sb-page">
                    <div
                        v-if="!editor.sections.value.length"
                        class="grid min-h-[60vh] place-items-center p-16 text-center"
                    >
                        <div>
                            <p class="font-display text-2xl font-semibold">
                                Tu página está vacía
                            </p>
                            <p class="mt-2 text-muted-foreground">
                                Agrega la primera sección para empezar a
                                diseñar.
                            </p>
                            <button
                                type="button"
                                class="mt-6 inline-flex h-11 items-center gap-2 rounded-full bg-primary px-6 font-medium text-primary-foreground hover:bg-primary-hover"
                                @click="emit('addSection', 0)"
                            >
                                <Plus class="size-4" /> Agregar sección
                            </button>
                        </div>
                    </div>

                    <div
                        v-for="(section, index) in editor.sections.value"
                        :key="section.key"
                        class="sb-editor-section relative"
                        :class="[
                            !section.isVisible && 'sb-editor-section--hidden',
                            editor.selection.section === section.key &&
                                !editor.selection.blocks.length &&
                                'sb-editor-section--selected',
                        ]"
                        @pointerdown="onSectionPointerDown($event, section)"
                        @pointerenter="hovered = section.key"
                        @pointerleave="hovered = null"
                        @dragover="onDragOver($event, section)"
                        @dragleave="onDragLeave"
                        @drop="onDrop($event, section)"
                    >
                        <SectionRenderer
                            :section="section"
                            :index="index"
                            :extra-rows="extraRows(section)"
                        >
                            <template #grid-start="{ rows }">
                                <div
                                    v-if="
                                        showGrid &&
                                        editor.ghost.value?.sectionKey ===
                                            section.key
                                    "
                                    class="sb-grid-overlay"
                                    :style="{ gridRow: `1 / span ${rows}` }"
                                    aria-hidden="true"
                                >
                                    <span v-for="c in 12" :key="c" />
                                </div>
                                <div
                                    v-if="
                                        !section.layout.blocks.length &&
                                        editor.canInteract.value
                                    "
                                    class="sb-empty-hint"
                                    :style="{
                                        gridRow: `1 / span ${Math.max(6, rows)}`,
                                    }"
                                >
                                    <MousePointerClick
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                    <span>Arrastra un elemento aquí</span>
                                    <span class="text-xs opacity-70"
                                        >o elige uno en la pestaña
                                        Elementos</span
                                    >
                                </div>
                            </template>
                            <template #grid-end>
                                <template
                                    v-if="
                                        editor.ghost.value?.sectionKey ===
                                        section.key
                                    "
                                >
                                    <div
                                        v-for="(r, i) in editor.ghost.value
                                            .rects"
                                        :key="`g${i}`"
                                        class="sb-ghost"
                                        :style="{
                                            gridArea: `${r.y + 1} / ${r.x + 1} / span ${r.h} / span ${r.w}`,
                                        }"
                                        aria-hidden="true"
                                    />
                                </template>
                                <template
                                    v-if="
                                        editor.guides.value?.sectionKey ===
                                        section.key
                                    "
                                >
                                    <span
                                        v-for="x in editor.guides.value.x"
                                        :key="`x${x}`"
                                        class="sb-guide sb-guide--v"
                                        :style="{ left: guideLeft(x) }"
                                        aria-hidden="true"
                                    />
                                    <span
                                        v-for="y in editor.guides.value.y"
                                        :key="`y${y}`"
                                        class="sb-guide sb-guide--h"
                                        :style="{ top: `calc(${y} * 2cqi)` }"
                                        aria-hidden="true"
                                    />
                                </template>
                            </template>
                        </SectionRenderer>

                        <div
                            v-if="
                                index === 0 &&
                                section.layout.settings.overlayHeader
                            "
                            class="pointer-events-none absolute inset-x-0 top-0 flex h-[72px] items-center justify-center border-b border-dashed border-white/25 text-[11px] font-medium tracking-wide text-white/60"
                            aria-hidden="true"
                        >
                            Menú del sitio (transparente sobre esta sección)
                        </div>

                        <!-- Barra de la sección -->
                        <div
                            v-show="
                                hovered === section.key ||
                                editor.selection.section === section.key
                            "
                            data-sb-chrome
                            class="sb-section-bar"
                            :class="
                                index === 0 &&
                                section.layout.settings.overlayHeader &&
                                'top-[80px]!'
                            "
                        >
                            <button
                                type="button"
                                class="sb-section-bar__name"
                                @click="editor.selectSection(section.key)"
                            >
                                <EyeOff
                                    v-if="!section.isVisible"
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                {{ sectionLabel(section) }}
                            </button>
                            <span
                                class="sb-section-bar__sep"
                                aria-hidden="true"
                            />
                            <button
                                type="button"
                                :disabled="index === 0"
                                :aria-label="`Subir ${sectionLabel(section)}`"
                                title="Subir"
                                @click="editor.moveSection(index, index - 1)"
                            >
                                <ArrowUp class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                :disabled="
                                    index === editor.sections.value.length - 1
                                "
                                :aria-label="`Bajar ${sectionLabel(section)}`"
                                title="Bajar"
                                @click="editor.moveSection(index, index + 1)"
                            >
                                <ArrowDown class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                :aria-label="`Duplicar ${sectionLabel(section)}`"
                                title="Duplicar"
                                @click="editor.duplicateSection(section.key)"
                            >
                                <Copy class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                :aria-label="
                                    section.isVisible
                                        ? `Ocultar ${sectionLabel(section)}`
                                        : `Mostrar ${sectionLabel(section)}`
                                "
                                :title="
                                    section.isVisible ? 'Ocultar' : 'Mostrar'
                                "
                                @click="
                                    editor.toggleSectionVisible(section.key)
                                "
                            >
                                <Eye
                                    v-if="!section.isVisible"
                                    class="size-3.5"
                                />
                                <EyeOff v-else class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                class="hover:text-danger!"
                                :aria-label="`Eliminar ${sectionLabel(section)}`"
                                title="Eliminar"
                                @click="confirmDelete(section)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>

                        <button
                            type="button"
                            data-sb-chrome
                            class="sb-add-between"
                            :class="hovered === section.key && 'opacity-100'"
                            :aria-label="`Agregar sección después de ${sectionLabel(section)}`"
                            @click="emit('addSection', index + 1)"
                        >
                            <Plus class="size-3.5" aria-hidden="true" /> Agregar
                            sección
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="`¿Eliminar «${deleting ? sectionLabel(deleting) : ''}»?`"
            description="La sección se quita del borrador. Puedes deshacerlo con Ctrl+Z o restaurar una versión publicada desde el historial."
            confirm-label="Eliminar sección"
            tone="destructive"
            :confirm="() => deleting && editor.deleteSection(deleting.key)"
        />
    </div>
</template>
