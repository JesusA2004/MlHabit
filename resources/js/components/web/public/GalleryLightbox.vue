<script setup lang="ts">
import { ChevronLeft, ChevronRight, Expand, X } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { ICON_STROKE } from '@/components/web/visual/icons';
import type { GalleryImage } from '@/types/web';
import OriginBadge from './OriginBadge.vue';
import ResponsiveImage from './ResponsiveImage.vue';

/**
 * Galería con visor a pantalla completa (Dialog accesible): mosaico con
 * entrada escalonada, visor con deslizamiento táctil (swipe), flechas del
 * teclado, transición direccional entre imágenes, tira de miniaturas y
 * etiqueta de origen en cada imagen.
 */
const props = defineProps<{ images: GalleryImage[]; title: string }>();

const open = ref(false);
const index = ref(0);
const direction = ref<1 | -1>(1);
const current = computed(() => props.images[index.value] ?? null);
const thumbs = ref<HTMLElement | null>(null);

function show(i: number) {
    direction.value = 1;
    index.value = i;
    open.value = true;
}

function move(delta: 1 | -1) {
    const total = props.images.length;

    if (total < 2) return;

    direction.value = delta;
    index.value = (index.value + delta + total) % total;
}

function jump(i: number) {
    direction.value = i >= index.value ? 1 : -1;
    index.value = i;
}

function onKey(event: KeyboardEvent) {
    if (event.key === 'ArrowRight') move(1);
    if (event.key === 'ArrowLeft') move(-1);
    if (event.key === 'Home') jump(0);
    if (event.key === 'End') jump(props.images.length - 1);
}

/* ── Deslizamiento táctil ─────────────────────────────────────── */
const dragX = ref(0);
let startX = 0;
let startY = 0;
let dragging = false;

function onPointerDown(event: PointerEvent) {
    if (event.pointerType === 'mouse') return;

    dragging = true;
    startX = event.clientX;
    startY = event.clientY;
}

function onPointerMove(event: PointerEvent) {
    if (!dragging) return;

    const dx = event.clientX - startX;
    const dy = event.clientY - startY;

    if (Math.abs(dx) > Math.abs(dy)) {
        dragX.value = dx;
    }
}

function onPointerUp() {
    if (!dragging) return;

    dragging = false;

    if (Math.abs(dragX.value) > 60) {
        move(dragX.value < 0 ? 1 : -1);
    }

    dragX.value = 0;
}

watch(index, async () => {
    await nextTick();
    thumbs.value
        ?.querySelector<HTMLElement>('[aria-current="true"]')
        ?.scrollIntoView({
            block: 'nearest',
            inline: 'center',
            behavior: 'smooth',
        });
});

/** Mosaico sin huecos para 1, 2 o más imágenes. */
function tileClass(i: number, n: number): string {
    if (n === 1) return 'col-span-2 aspect-[16/9] md:col-span-6';
    if (n === 2) return 'col-span-2 aspect-[4/3] md:col-span-3';
    if (i === 0)
        return 'col-span-2 aspect-[16/10] md:col-span-4 md:row-span-2 md:aspect-auto';
    const mobileWide = (n - 1) % 2 === 1 && i === n - 1;
    // Escritorio: tras el bloque 4×2 + 2, filas de tres; la última se estira.
    const tail = n - 3;
    const lastRow = tail % 3;
    let md = 'md:col-span-2 md:aspect-square';

    if (i >= 3 && lastRow === 1 && i === n - 1) {
        md = 'md:col-span-6 md:aspect-[21/9]';
    } else if (i >= 3 && lastRow === 2 && i >= n - 2) {
        md = 'md:col-span-3 md:aspect-[4/3]';
    }

    return mobileWide
        ? `col-span-2 aspect-[16/10] ${md}`
        : `aspect-square ${md}`;
}

watch(
    () => props.images,
    () => (index.value = 0),
);
</script>

<template>
    <div
        v-if="props.images.length"
        class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-6"
        data-motion-stagger="70"
        data-motion="zoom-in"
    >
        <button
            v-for="(image, i) in props.images"
            :key="image.id"
            type="button"
            :class="[
                'group relative overflow-hidden rounded-xl bg-stone outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                tileClass(i, props.images.length),
            ]"
            :aria-label="`Ver imagen ${i + 1} de ${props.images.length}${image.alt ? ': ' + image.alt : ''}`"
            @click="show(i)"
        >
            <ResponsiveImage
                :image="image"
                :sizes="
                    i === 0
                        ? '(min-width: 768px) 66vw, 100vw'
                        : '(min-width: 768px) 33vw, 50vw'
                "
                class="absolute inset-0"
                img-class="transition-[scale,filter] duration-700 ease-out group-hover:scale-[1.06] group-focus-visible:scale-[1.06]"
            />
            <span
                class="absolute inset-0 bg-espresso/0 transition-colors duration-500 group-hover:bg-espresso/25"
                aria-hidden="true"
            />
            <span
                class="absolute right-3 bottom-3 grid size-10 scale-75 place-items-center rounded-full bg-white/95 text-espresso opacity-0 shadow-sm transition-[scale,opacity] duration-300 ease-out group-hover:scale-100 group-hover:opacity-100 group-focus-visible:scale-100 group-focus-visible:opacity-100"
                aria-hidden="true"
            >
                <Expand class="size-4" :stroke-width="ICON_STROKE" />
            </span>
            <OriginBadge
                :origin="image.origin"
                :label="image.originLabel"
                class="absolute top-2.5 left-2.5"
            />
        </button>
    </div>

    <Dialog v-model:open="open">
        <DialogContent
            class="flex h-[100svh] max-w-none flex-col gap-0 rounded-none border-0 bg-espresso p-0 text-espresso-foreground sm:h-[92svh] sm:max-w-[min(1400px,96vw)] sm:rounded-2xl [&>button:last-child]:hidden"
            @keydown="onKey"
        >
            <div
                class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6"
            >
                <div class="min-w-0">
                    <DialogTitle
                        class="truncate font-display text-base font-semibold"
                        >{{ props.title }}</DialogTitle
                    >
                    <DialogDescription
                        class="text-xs text-espresso-foreground/70 tabular-nums"
                    >
                        Imagen {{ index + 1 }} de {{ props.images.length }}
                    </DialogDescription>
                </div>
                <DialogClose
                    class="grid size-10 place-items-center rounded-full bg-white/10 transition-[background-color,rotate] duration-300 hover:rotate-90 hover:bg-white/20"
                    aria-label="Cerrar galería"
                >
                    <X class="size-5" :stroke-width="ICON_STROKE" />
                </DialogClose>
            </div>
            <div
                v-if="current"
                class="relative flex min-h-0 flex-1 touch-pan-y items-center justify-center overflow-hidden px-2 pb-4 sm:px-16"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
            >
                <Transition
                    mode="out-in"
                    :enter-from-class="
                        direction === 1
                            ? 'opacity-0 translate-x-8'
                            : 'opacity-0 -translate-x-8'
                    "
                    enter-active-class="transition duration-400 ease-out"
                    leave-active-class="transition duration-200 ease-in"
                    :leave-to-class="
                        direction === 1
                            ? 'opacity-0 -translate-x-8'
                            : 'opacity-0 translate-x-8'
                    "
                >
                    <div
                        :key="current.id"
                        class="relative h-full w-full"
                        :style="
                            dragX
                                ? { transform: `translate3d(${dragX}px,0,0)` }
                                : undefined
                        "
                    >
                        <ResponsiveImage
                            :image="current"
                            sizes="96vw"
                            class="h-full w-full"
                            img-class="object-contain select-none"
                        />
                        <OriginBadge
                            :origin="current.origin"
                            :label="current.originLabel"
                            class="absolute top-2 left-2"
                        />
                    </div>
                </Transition>
                <template v-if="props.images.length > 1">
                    <button
                        type="button"
                        class="group absolute top-1/2 left-2 hidden size-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 transition-colors hover:bg-white/20 sm:left-4 sm:grid"
                        aria-label="Imagen anterior"
                        @click="move(-1)"
                    >
                        <ChevronLeft
                            class="size-5 transition-transform group-hover:-translate-x-0.5"
                            :stroke-width="ICON_STROKE"
                        />
                    </button>
                    <button
                        type="button"
                        class="group absolute top-1/2 right-2 hidden size-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 transition-colors hover:bg-white/20 sm:right-4 sm:grid"
                        aria-label="Imagen siguiente"
                        @click="move(1)"
                    >
                        <ChevronRight
                            class="size-5 transition-transform group-hover:translate-x-0.5"
                            :stroke-width="ICON_STROKE"
                        />
                    </button>
                </template>
            </div>
            <p
                v-if="current?.caption"
                class="px-6 pb-3 text-center text-sm text-espresso-foreground/80"
            >
                {{ current.caption }}
            </p>
            <div
                v-if="props.images.length > 1"
                class="flex items-center gap-3 border-t border-white/10 px-4 py-3 sm:px-6"
            >
                <button
                    type="button"
                    class="grid size-11 shrink-0 place-items-center rounded-full bg-white/10 sm:hidden"
                    aria-label="Imagen anterior"
                    @click="move(-1)"
                >
                    <ChevronLeft class="size-5" :stroke-width="ICON_STROKE" />
                </button>
                <div
                    ref="thumbs"
                    class="scrollbar-none flex min-w-0 flex-1 gap-2 overflow-x-auto py-1"
                    role="group"
                    aria-label="Miniaturas"
                >
                    <button
                        v-for="(image, i) in props.images"
                        :key="image.id"
                        type="button"
                        :aria-current="i === index ? 'true' : undefined"
                        :aria-label="`Ir a la imagen ${i + 1}`"
                        :class="[
                            'relative h-14 w-20 shrink-0 overflow-hidden rounded-lg ring-2 transition-[opacity,box-shadow] duration-300',
                            i === index
                                ? 'opacity-100 ring-oak'
                                : 'opacity-55 ring-transparent hover:opacity-90',
                        ]"
                        @click="jump(i)"
                    >
                        <ResponsiveImage
                            :image="image"
                            sizes="80px"
                            class="absolute inset-0"
                        />
                    </button>
                </div>
                <button
                    type="button"
                    class="grid size-11 shrink-0 place-items-center rounded-full bg-white/10 sm:hidden"
                    aria-label="Imagen siguiente"
                    @click="move(1)"
                >
                    <ChevronRight class="size-5" :stroke-width="ICON_STROKE" />
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
