<script setup lang="ts">
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { GalleryImage } from '@/types/web';
import OriginBadge from './OriginBadge.vue';
import ResponsiveImage from './ResponsiveImage.vue';

/**
 * Galería con visor a pantalla completa (Dialog accesible): miniaturas,
 * navegación con flechas del teclado y etiqueta de origen en cada imagen.
 */
const props = defineProps<{ images: GalleryImage[]; title: string }>();

const open = ref(false);
const index = ref(0);
const current = computed(() => props.images[index.value] ?? null);

function show(i: number) {
    index.value = i;
    open.value = true;
}

function move(delta: number) {
    const total = props.images.length;
    index.value = (index.value + delta + total) % total;
}

function onKey(event: KeyboardEvent) {
    if (event.key === 'ArrowRight') move(1);
    if (event.key === 'ArrowLeft') move(-1);
}

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
                img-class="transition-transform duration-500 group-hover:scale-[1.03]"
            />
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
                        class="text-xs text-espresso-foreground/70"
                    >
                        Imagen {{ index + 1 }} de {{ props.images.length }}
                    </DialogDescription>
                </div>
                <DialogClose
                    class="grid size-10 place-items-center rounded-full bg-white/10 hover:bg-white/20"
                    aria-label="Cerrar galería"
                >
                    <X class="size-5" />
                </DialogClose>
            </div>
            <div
                v-if="current"
                class="relative flex min-h-0 flex-1 items-center justify-center px-2 pb-4 sm:px-16"
            >
                <ResponsiveImage
                    :key="current.id"
                    :image="current"
                    sizes="96vw"
                    class="h-full w-full"
                    img-class="object-contain"
                />
                <OriginBadge
                    :origin="current.origin"
                    :label="current.originLabel"
                    class="absolute top-2 left-4 sm:left-16"
                />
                <template v-if="props.images.length > 1">
                    <button
                        type="button"
                        class="absolute top-1/2 left-2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 hover:bg-white/20 sm:left-4"
                        aria-label="Imagen anterior"
                        @click="move(-1)"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="absolute top-1/2 right-2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 hover:bg-white/20 sm:right-4"
                        aria-label="Imagen siguiente"
                        @click="move(1)"
                    >
                        <ChevronRight class="size-5" />
                    </button>
                </template>
            </div>
            <p
                v-if="current?.caption"
                class="px-6 pb-5 text-center text-sm text-espresso-foreground/80"
            >
                {{ current.caption }}
            </p>
        </DialogContent>
    </Dialog>
</template>
