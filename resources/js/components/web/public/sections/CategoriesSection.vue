<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { CategoryCardData, HomeSection } from '@/types/web';
import CategoryTile from '../CategoryTile.vue';
import SectionHeading from '../SectionHeading.vue';
import { text } from './content';

/** Categorías publicadas en mosaico tipo bento (la primera, protagonista). */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const categories = computed(
    () => (props.section.items ?? []) as CategoryCardData[],
);

/**
 * Reparte el mosaico sin huecos: la primera ocupa 2×2 en escritorio, las
 * cuatro siguientes llenan el bloque de al lado y las demás forman filas
 * de cuatro; la última fila incompleta se estira para cerrar la retícula.
 */
function tileClass(i: number, total: number): string {
    if (i === 0) {
        return total === 1
            ? 'sm:col-span-2 lg:col-span-4 min-h-96'
            : 'sm:col-span-2 lg:col-span-2 lg:row-span-2 min-h-80 lg:min-h-[34rem]';
    }

    const rest = total - 1;
    const classes = ['min-h-64'];
    // Tableta (2 columnas): si sobra una, la última ocupa la fila completa.
    const smWide = rest % 2 === 1 && i === total - 1;

    let lg = '';

    if (i <= 4) {
        const inBlock = Math.min(rest, 4);

        if (inBlock === 1) {
            lg = 'lg:col-span-2 lg:row-span-2';
        } else if (inBlock === 2 || (inBlock === 3 && i === 3)) {
            lg = 'lg:col-span-2';
        }
    } else {
        const tail = rest - 4;
        const lastRow = tail % 4 || 4;
        const pos = i - 5;

        if (pos >= tail - lastRow) {
            if (lastRow === 1) {
                lg = 'lg:col-span-4';
            } else if (lastRow === 2 || (lastRow === 3 && pos === tail - 3)) {
                lg = 'lg:col-span-2';
            }
        }
    }

    if (smWide) {
        classes.push('sm:col-span-2', lg || 'lg:col-span-1');
    } else if (lg) {
        classes.push(lg);
    }

    return classes.join(' ');
}
</script>

<template>
    <section aria-labelledby="categorias-title" class="section-y">
        <div class="container-site">
            <div
                class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between"
            >
                <SectionHeading
                    id="categorias-title"
                    :eyebrow="text(c, 'eyebrow')"
                    :title="text(c, 'title')"
                    :subtitle="text(c, 'subtitle')"
                />
                <Link
                    v-if="text(c, 'cta_label')"
                    href="/catalogo"
                    class="group inline-flex shrink-0 items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                >
                    {{ text(c, 'cta_label') }}
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    />
                </Link>
            </div>

            <div
                class="mt-12 grid auto-rows-[minmax(16rem,auto)] gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <CategoryTile
                    v-for="(category, i) in categories"
                    :key="category.id"
                    :category="category"
                    :index="i"
                    :large="i === 0"
                    :class="cn(tileClass(i, categories.length))"
                />
            </div>
        </div>
    </section>
</template>
