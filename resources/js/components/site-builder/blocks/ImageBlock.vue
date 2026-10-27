<script setup lang="ts">
import { Check, ImagePlus, Move } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import OriginBadge from '@/components/web/public/OriginBadge.vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import SbSlider from '../editor/SbSlider.vue';
import { clamp } from '../grid';
import { radiusCss, shadowCss } from '../tokens';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Imagen dentro de un marco: recorte por punto focal (object-position) y
 * acercamiento (scale) dentro del marco. En el editor, doble clic entra al
 * modo recorte: arrastra la foto para encuadrar y usa la rueda o el
 * deslizador para acercar.
 */
const props = defineProps<{ block: Block }>();

const { section, bridge, str, num, bool } = useBlock(() => props.block);

const mediaId = computed(() => {
    const v = props.block.props.mediaId;

    return typeof v === 'number' ? v : null;
});
const image = computed(() =>
    mediaId.value !== null
        ? (section?.value.images[String(mediaId.value)] ?? null)
        : null,
);
/** En el editor, una imagen recién subida puede no tener variantes aún. */
const fallback = computed(() =>
    mediaId.value !== null && !image.value
        ? (bridge?.library.value[String(mediaId.value)] ?? null)
        : null,
);

const focalX = computed(() => num('focalX', 50));
const focalY = computed(() => num('focalY', 50));
const zoom = computed(() => num('zoom', 1));
const cropping = computed(() => bridge?.cropping.value === props.block.id);
const dropping = computed(() => bridge?.dropTarget.value === props.block.id);

const frameStyle = computed(() => ({
    borderRadius: radiusCss(props.block.style.radius),
    boxShadow: shadowCss(props.block.style.shadow),
    '--sb-fx': `${focalX.value}%`,
    '--sb-fy': `${focalY.value}%`,
    '--sb-zoom': String(zoom.value),
    '--sb-fit': str('fit', 'cover'),
}));

const alt = computed(() => str('alt') || image.value?.alt || '');

// ----- Modo recorte ---------------------------------------------------
const frame = ref<HTMLElement | null>(null);
let drag: { x: number; y: number; fx: number; fy: number } | null = null;
let wheelTimer: number | undefined;

function update(
    patch: { focalX?: number; focalY?: number; zoom?: number },
    record: boolean,
) {
    if (!bridge || !section) return;
    bridge.updateCrop(section.value.key, props.block.id, patch, record);
}

function onCropDown(event: PointerEvent) {
    if (!cropping.value) return;
    event.stopPropagation();
    event.preventDefault();
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    drag = {
        x: event.clientX,
        y: event.clientY,
        fx: focalX.value,
        fy: focalY.value,
    };
}

function onCropMove(event: PointerEvent) {
    if (!drag || !frame.value) return;
    const rect = frame.value.getBoundingClientRect();
    // Arrastrar la foto hacia la derecha muestra más de su lado izquierdo.
    const fx = clamp(
        drag.fx - ((event.clientX - drag.x) / rect.width) * (100 / zoom.value),
        0,
        100,
    );
    const fy = clamp(
        drag.fy - ((event.clientY - drag.y) / rect.height) * (100 / zoom.value),
        0,
        100,
    );
    update({ focalX: Math.round(fx), focalY: Math.round(fy) }, false);
}

function onCropUp() {
    if (!drag) return;
    drag = null;
    update({}, true);
}

function onWheel(event: WheelEvent) {
    if (!cropping.value) return;
    event.preventDefault();
    event.stopPropagation();
    const next = clamp(
        Math.round((zoom.value - event.deltaY * 0.0015) * 100) / 100,
        1,
        3,
    );
    update({ zoom: next }, false);
    window.clearTimeout(wheelTimer);
    wheelTimer = window.setTimeout(() => update({}, true), 350);
}

function onCropKey(event: KeyboardEvent) {
    if (!cropping.value) return;
    const step = event.shiftKey ? 10 : 2;
    const moves: Record<string, { focalX?: number; focalY?: number }> = {
        ArrowLeft: { focalX: clamp(focalX.value - step, 0, 100) },
        ArrowRight: { focalX: clamp(focalX.value + step, 0, 100) },
        ArrowUp: { focalY: clamp(focalY.value - step, 0, 100) },
        ArrowDown: { focalY: clamp(focalY.value + step, 0, 100) },
    };
    if (moves[event.key]) {
        event.preventDefault();
        event.stopPropagation();
        update(moves[event.key], true);
    } else if (event.key === 'Enter' || event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        bridge?.endCrop();
    }
}

onBeforeUnmount(() => window.clearTimeout(wheelTimer));
</script>

<template>
    <div
        ref="frame"
        class="sb-img relative flex-1 overflow-hidden"
        :class="[
            !image && !fallback && 'bg-stone',
            cropping &&
                'cursor-move ring-2 ring-oak ring-offset-2 ring-offset-transparent',
            dropping && 'ring-4 ring-primary/60',
        ]"
        :style="frameStyle"
        :tabindex="cropping ? 0 : undefined"
        :aria-label="
            cropping
                ? 'Encuadre de la imagen: usa las flechas para mover y Enter para terminar'
                : undefined
        "
        @pointerdown="onCropDown"
        @pointermove="onCropMove"
        @pointerup="onCropUp"
        @pointercancel="onCropUp"
        @wheel="onWheel"
        @keydown="onCropKey"
    >
        <ResponsiveImage
            v-if="image"
            :image="image"
            :alt="alt"
            :priority="section?.priority ?? false"
            :sizes="image.sizes"
            class="absolute inset-0"
        />
        <img
            v-else-if="fallback && fallback.url"
            :src="fallback.url"
            :alt="alt"
            class="absolute inset-0 h-full w-full"
            draggable="false"
        />
        <div
            v-else
            class="absolute inset-0 grid place-items-center bg-gradient-to-br from-stone to-linen"
        >
            <FinishSwatches class="w-2/3 max-w-72" :labels="false" />
            <span
                v-if="bridge"
                class="absolute bottom-4 left-1/2 inline-flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium whitespace-nowrap text-foreground shadow-sm"
            >
                <ImagePlus class="size-3.5" aria-hidden="true" />
                Arrastra una imagen aquí
            </span>
        </div>

        <OriginBadge
            v-if="image && bool('showOrigin', true) && !cropping"
            :origin="image.origin"
            :label="image.originLabel"
            class="absolute bottom-4 left-4"
        />

        <template v-if="cropping">
            <div
                class="pointer-events-none absolute size-7 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgb(0_0_0/0.25)]"
                :style="{ left: `${focalX}%`, top: `${focalY}%` }"
                aria-hidden="true"
            >
                <span class="absolute inset-[9px] rounded-full bg-white" />
            </div>
            <div
                class="pointer-events-none absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-black/60 px-2.5 py-1 text-xs text-white backdrop-blur-sm"
            >
                <Move class="size-3.5" aria-hidden="true" /> Arrastra para
                encuadrar
            </div>
        </template>
    </div>

    <div
        v-if="cropping"
        class="absolute top-full left-1/2 z-[60] mt-3 flex w-72 -translate-x-1/2 items-center gap-3 rounded-full border border-border bg-popover px-4 py-2 text-popover-foreground shadow-lg"
        :style="{
            scale: bridge ? String(1 / bridge.zoom.value) : undefined,
            transformOrigin: 'top center',
        }"
        @pointerdown.stop
    >
        <span class="text-xs text-muted-foreground tabular-nums"
            >{{ zoom.toFixed(1) }}×</span
        >
        <SbSlider
            :model-value="zoom"
            :min="1"
            :max="3"
            :step="0.05"
            label="Acercamiento de la imagen"
            @update:model-value="(v) => update({ zoom: v }, false)"
            @commit="() => update({}, true)"
        />
        <button
            type="button"
            class="inline-flex h-8 items-center gap-1 rounded-full bg-primary px-3 text-xs font-medium text-primary-foreground hover:bg-primary-hover"
            @click="bridge?.endCrop()"
        >
            <Check class="size-3.5" aria-hidden="true" /> Listo
        </button>
    </div>
</template>
