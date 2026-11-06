<script setup lang="ts">
import { ChevronLeft, ChevronRight, Expand, ImagePlus, X } from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, ref } from 'vue';
import OriginBadge from '@/components/web/public/OriginBadge.vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import type { ResponsiveImageData } from '@/types/web';
import { radiusCss, shadowCss } from '../tokens';
import type { Block, GalleryItem } from '../types';
import { useBlock } from './useBlock';

/**
 * Galería de fotos públicas: cuadrícula, mosaico editorial (la primera foto
 * ocupa el doble) o carrusel deslizable con flechas. En el sitio, tocar una
 * foto abre el visor a pantalla completa (teclado y flechas). Las columnas
 * se reducen solas según el ancho real del bloque (container queries).
 */
const props = defineProps<{ block: Block }>();

const { section, bridge, str, num, bool } = useBlock(() => props.block);

const items = computed<GalleryItem[]>(() =>
    Array.isArray(props.block.props.items)
        ? (props.block.props.items as GalleryItem[]).filter(
              (i) => typeof i?.mediaId === 'number',
          )
        : [],
);

type Tile = {
    item: GalleryItem;
    image: ResponsiveImageData | null;
    fallback: string | null;
    alt: string;
};

const tiles = computed<Tile[]>(() =>
    items.value.map((item) => {
        const image = section?.value.images[String(item.mediaId)] ?? null;
        const ref_ = bridge?.library.value[String(item.mediaId)] ?? null;

        return {
            item,
            image,
            fallback: image ? null : (ref_?.thumbnailUrl ?? ref_?.url ?? null),
            alt: item.alt || image?.alt || ref_?.altText || '',
        };
    }),
);

const layout = computed(() => str('layout', 'grid'));
const columns = computed(() => Math.min(4, Math.max(1, num('columns', 3))));
const gap = computed(
    () =>
        ({ sm: '0.5rem', md: '1rem', lg: '1.5rem' })[str('gap', 'md')] ??
        '1rem',
);
const ratio = computed(() => {
    const [w, h] = str('aspect', '4:3').split(':').map(Number);

    return w && h ? `${w} / ${h}` : '4 / 3';
});
const radius = computed(() => radiusCss(props.block.style.radius));
const shadow = computed(() => shadowCss(props.block.style.shadow));
const canZoom = computed(() => !bridge && bool('lightbox', true));

/* ── Carrusel ─────────────────────────────────────────────── */
const track = ref<HTMLElement | null>(null);
function scrollBy(direction: 1 | -1) {
    const el = track.value;
    if (!el) return;
    const reduce = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    el.scrollBy({
        left: direction * el.clientWidth * 0.85,
        behavior: reduce ? 'auto' : 'smooth',
    });
}

/* ── Visor ────────────────────────────────────────────────── */
const open = ref(false);
const index = ref(0);
const current = computed(() => tiles.value[index.value] ?? null);

function show(i: number) {
    if (!canZoom.value) return;
    index.value = i;
    open.value = true;
}
function move(delta: 1 | -1) {
    const total = tiles.value.length;
    if (total < 2) return;
    index.value = (index.value + delta + total) % total;
}
function onKey(event: KeyboardEvent) {
    if (event.key === 'ArrowRight') move(1);
    if (event.key === 'ArrowLeft') move(-1);
}
</script>

<template>
    <div
        class="sb-gallery relative flex min-h-0 flex-1 flex-col"
        :data-layout="layout"
        :style="{
            '--g-cols': columns,
            '--g-gap': gap,
            '--g-ratio': ratio,
            '--g-radius': radius,
            '--g-shadow': shadow,
        }"
    >
        <div
            v-if="!tiles.length && bridge"
            class="grid flex-1 place-items-center rounded-2xl border-2 border-dashed border-border bg-muted/50 p-6 text-center text-sm text-muted-foreground"
        >
            <span class="flex flex-col items-center gap-2">
                <ImagePlus class="size-6" aria-hidden="true" />
                Agrega fotos a la galería en el panel derecho
            </span>
        </div>

        <!-- Carrusel -->
        <div v-else-if="layout === 'carousel'" class="relative">
            <ul
                ref="track"
                class="sb-gallery__track"
                role="list"
                aria-label="Galería de fotos"
            >
                <li
                    v-for="(tile, i) in tiles"
                    :key="tile.item.mediaId"
                    class="sb-gallery__slide"
                >
                    <component
                        :is="canZoom ? 'button' : 'div'"
                        :type="canZoom ? 'button' : undefined"
                        class="sb-gallery__tile group"
                        :aria-label="
                            canZoom
                                ? `Ver foto ${i + 1} de ${tiles.length}${tile.alt ? `: ${tile.alt}` : ''}`
                                : undefined
                        "
                        @click="show(i)"
                    >
                        <ResponsiveImage
                            v-if="tile.image"
                            :image="tile.image"
                            :alt="tile.alt"
                            class="h-full w-full"
                            img-class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.03]"
                        />
                        <img
                            v-else-if="tile.fallback"
                            :src="tile.fallback"
                            :alt="tile.alt"
                            class="h-full w-full object-cover"
                        />
                        <OriginBadge
                            v-if="tile.image && bool('showOrigin', true)"
                            :origin="tile.image.origin"
                            :label="tile.image.originLabel"
                            class="absolute bottom-3 left-3"
                        />
                    </component>
                    <p
                        v-if="tile.item.caption"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        {{ tile.item.caption }}
                    </p>
                </li>
            </ul>
            <div v-if="tiles.length > 1" class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-full border border-border bg-card text-foreground transition-colors hover:border-primary hover:text-primary"
                    aria-label="Fotos anteriores"
                    @click="scrollBy(-1)"
                >
                    <ChevronLeft class="size-5" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-full border border-border bg-card text-foreground transition-colors hover:border-primary hover:text-primary"
                    aria-label="Fotos siguientes"
                    @click="scrollBy(1)"
                >
                    <ChevronRight class="size-5" aria-hidden="true" />
                </button>
            </div>
        </div>

        <!-- Cuadrícula / mosaico -->
        <ul v-else class="sb-gallery__grid" role="list">
            <li
                v-for="(tile, i) in tiles"
                :key="tile.item.mediaId"
                class="sb-gallery__cell"
                :data-first="i === 0 ? '' : undefined"
            >
                <component
                    :is="canZoom ? 'button' : 'div'"
                    :type="canZoom ? 'button' : undefined"
                    class="sb-gallery__tile group"
                    :aria-label="
                        canZoom
                            ? `Ver foto ${i + 1} de ${tiles.length}${tile.alt ? `: ${tile.alt}` : ''}`
                            : undefined
                    "
                    @click="show(i)"
                >
                    <ResponsiveImage
                        v-if="tile.image"
                        :image="tile.image"
                        :alt="tile.alt"
                        class="h-full w-full"
                        img-class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.03]"
                    />
                    <img
                        v-else-if="tile.fallback"
                        :src="tile.fallback"
                        :alt="tile.alt"
                        class="h-full w-full object-cover"
                    />
                    <OriginBadge
                        v-if="tile.image && bool('showOrigin', true)"
                        :origin="tile.image.origin"
                        :label="tile.image.originLabel"
                        class="absolute bottom-3 left-3"
                    />
                    <span
                        v-if="canZoom"
                        class="absolute top-3 right-3 grid size-9 place-items-center rounded-full bg-black/45 text-white opacity-0 backdrop-blur-sm transition-opacity duration-300 group-hover:opacity-100 group-focus-visible:opacity-100"
                        aria-hidden="true"
                    >
                        <Expand class="size-4" />
                    </span>
                </component>
                <p
                    v-if="tile.item.caption"
                    class="mt-2 text-sm text-muted-foreground"
                >
                    {{ tile.item.caption }}
                </p>
            </li>
        </ul>

        <DialogRoot v-if="canZoom" v-model:open="open">
            <DialogPortal>
                <DialogOverlay
                    class="sb-viewer-overlay fixed inset-0 z-50 bg-espresso/92 backdrop-blur-sm"
                />
                <DialogContent
                    class="sb-viewer fixed inset-0 z-50 flex flex-col items-center justify-center p-4 outline-none md:p-10"
                    @keydown="onKey"
                >
                    <DialogTitle class="sr-only">Galería de fotos</DialogTitle>
                    <DialogDescription class="sr-only"
                        >Foto {{ index + 1 }} de {{ tiles.length }}. Usa las
                        flechas para cambiar de foto.</DialogDescription
                    >
                    <DialogClose
                        class="absolute top-4 right-4 grid size-11 place-items-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
                        aria-label="Cerrar galería"
                    >
                        <X class="size-5" />
                    </DialogClose>
                    <Transition name="sb-viewer-fade" mode="out-in">
                        <figure
                            v-if="current"
                            :key="current.item.mediaId"
                            class="flex max-h-full max-w-6xl flex-col items-center gap-3"
                        >
                            <ResponsiveImage
                                v-if="current.image"
                                :image="current.image"
                                :alt="current.alt"
                                sizes="100vw"
                                img-class="max-h-[78svh] w-auto rounded-xl object-contain"
                            />
                            <figcaption
                                v-if="current.item.caption || current.alt"
                                class="text-center text-sm text-white/75"
                            >
                                {{ current.item.caption || current.alt }}
                            </figcaption>
                        </figure>
                    </Transition>
                    <div
                        v-if="tiles.length > 1"
                        class="absolute inset-x-4 top-1/2 flex -translate-y-1/2 justify-between"
                    >
                        <button
                            type="button"
                            class="grid size-12 place-items-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
                            aria-label="Foto anterior"
                            @click="move(-1)"
                        >
                            <ChevronLeft class="size-6" />
                        </button>
                        <button
                            type="button"
                            class="grid size-12 place-items-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
                            aria-label="Foto siguiente"
                            @click="move(1)"
                        >
                            <ChevronRight class="size-6" />
                        </button>
                    </div>
                    <p
                        class="absolute bottom-4 left-1/2 -translate-x-1/2 text-sm text-white/70 tabular-nums"
                    >
                        {{ index + 1 }} / {{ tiles.length }}
                    </p>
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
    </div>
</template>

<style scoped>
.sb-gallery {
    container-type: inline-size;
}

.sb-gallery__grid {
    display: grid;
    grid-template-columns: repeat(var(--g-cols), minmax(0, 1fr));
    gap: var(--g-gap);
}

.sb-gallery__tile {
    position: relative;
    display: block;
    width: 100%;
    aspect-ratio: var(--g-ratio);
    overflow: hidden;
    border-radius: var(--g-radius);
    box-shadow: var(--g-shadow);
    background: var(--color-muted, #efebe5);
    text-align: left;
}

button.sb-gallery__tile {
    cursor: zoom-in;
}

button.sb-gallery__tile:focus-visible {
    outline: 3px solid var(--color-ring, #2f5a4c);
    outline-offset: 3px;
}

/* Mosaico: la primera foto ocupa dos columnas y dos filas. */
[data-layout='mosaic'] .sb-gallery__grid {
    grid-auto-flow: dense;
}

[data-layout='mosaic'] .sb-gallery__cell[data-first] {
    grid-column: span 2;
    grid-row: span 2;
}

[data-layout='mosaic'] .sb-gallery__cell[data-first] .sb-gallery__tile {
    height: 100%;
    aspect-ratio: auto;
    min-height: 100%;
}

/* Carrusel */
.sb-gallery__track {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: calc(
        (100% - var(--g-gap) * (min(var(--g-cols), 3) - 1)) /
            min(var(--g-cols), 3) * 0.92
    );
    gap: var(--g-gap);
    overflow-x: auto;
    overscroll-behavior-x: contain;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    padding-bottom: 2px;
}

.sb-gallery__track::-webkit-scrollbar {
    display: none;
}

.sb-gallery__slide {
    scroll-snap-align: start;
}

/* Columnas según el ancho real del bloque. */
@container (max-width: 620px) {
    .sb-gallery__grid {
        grid-template-columns: repeat(min(var(--g-cols), 2), minmax(0, 1fr));
    }

    .sb-gallery__track {
        grid-auto-columns: 82%;
    }
}

@container (max-width: 360px) {
    .sb-gallery__grid {
        grid-template-columns: minmax(0, 1fr);
    }

    [data-layout='mosaic'] .sb-gallery__cell[data-first] {
        grid-column: auto;
        grid-row: auto;
    }

    [data-layout='mosaic'] .sb-gallery__cell[data-first] .sb-gallery__tile {
        aspect-ratio: var(--g-ratio);
        height: auto;
    }
}

.sb-viewer[data-state='open'],
.sb-viewer-overlay[data-state='open'] {
    animation: sb-viewer-in 260ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.sb-viewer[data-state='closed'],
.sb-viewer-overlay[data-state='closed'] {
    animation: sb-viewer-in 180ms ease-in reverse both;
}

@keyframes sb-viewer-in {
    from {
        opacity: 0;
    }
}

.sb-viewer-fade-enter-active,
.sb-viewer-fade-leave-active {
    transition:
        opacity 200ms ease,
        transform 200ms ease;
}

.sb-viewer-fade-enter-from,
.sb-viewer-fade-leave-to {
    opacity: 0;
    transform: scale(0.98);
}

@media (prefers-reduced-motion: reduce) {
    .sb-viewer,
    .sb-viewer-overlay {
        animation: none !important;
    }

    .sb-viewer-fade-enter-active,
    .sb-viewer-fade-leave-active {
        transition: none;
    }

    .sb-gallery__tile :deep(img) {
        transition: none !important;
    }
}
</style>
