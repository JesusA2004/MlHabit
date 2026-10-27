<script setup lang="ts">
import { MoveHorizontal } from '@lucide/vue';
import { ref } from 'vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import type { ResponsiveImageData } from '@/types/web';

/**
 * Comparador antes/después. Solo se usa cuando el proyecto tiene dos
 * imágenes marcadas como "Antes" y "Después". El control es un `range`
 * nativo: funciona con teclado, lector de pantalla, ratón y tacto.
 */
const props = defineProps<{
    before: ResponsiveImageData;
    after: ResponsiveImageData;
    title: string;
}>();

const position = ref(50);
</script>

<template>
    <figure class="select-none">
        <div
            class="relative aspect-[16/10] overflow-hidden rounded-[1.5rem] bg-stone"
            :style="{ '--pos': `${position}%` }"
        >
            <ResponsiveImage
                :image="props.after"
                class="absolute inset-0"
                sizes="(min-width: 1200px) 1200px, 100vw"
            />
            <div
                class="absolute inset-0"
                :style="{ clipPath: `inset(0 calc(100% - var(--pos)) 0 0)` }"
            >
                <ResponsiveImage
                    :image="props.before"
                    class="absolute inset-0"
                    sizes="(min-width: 1200px) 1200px, 100vw"
                />
            </div>
            <span
                class="absolute top-4 left-4 rounded-full bg-black/55 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm"
                aria-hidden="true"
                >Antes</span
            >
            <span
                class="absolute top-4 right-4 rounded-full bg-black/55 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm"
                aria-hidden="true"
                >Después</span
            >
            <span
                class="pointer-events-none absolute inset-y-0 w-0.5 -translate-x-1/2 bg-white shadow-[0_0_0_1px_rgb(0_0_0/0.1)]"
                :style="{ left: 'var(--pos)' }"
                aria-hidden="true"
            >
                <span
                    class="absolute top-1/2 left-1/2 grid size-11 -translate-1/2 place-items-center rounded-full bg-white text-espresso shadow-lg"
                >
                    <MoveHorizontal class="size-5" :stroke-width="1.5" />
                </span>
            </span>
            <input
                v-model.number="position"
                type="range"
                min="0"
                max="100"
                step="1"
                class="absolute inset-0 size-full cursor-ew-resize opacity-0 focus-visible:opacity-0"
                :aria-label="`Comparar antes y después de ${props.title}`"
                :aria-valuetext="`${position}% antes`"
            />
        </div>
        <figcaption class="mt-3 text-sm text-muted-foreground">
            Desliza para comparar el espacio antes y después.
        </figcaption>
    </figure>
</template>

<style scoped>
div:has(> input:focus-visible) {
    outline: 3px solid color-mix(in srgb, var(--ring) 50%, transparent);
    outline-offset: 3px;
}
</style>
