<script setup lang="ts">
import { computed } from 'vue';
import CategoryTile from '@/components/web/public/CategoryTile.vue';
import { cn } from '@/lib/utils';
import type { CategoryCardData } from '@/types/web';
import type { Block } from '../types';
import DynamicEmpty from './DynamicEmpty.vue';
import { useBlock } from './useBlock';

/** Categorías publicadas en mosaico tipo bento (la primera, protagonista). */
const props = defineProps<{ block: Block }>();

const { section, bridge } = useBlock(() => props.block);

const categories = computed(
    () =>
        (section?.value.data[props.block.id]?.items ??
            []) as CategoryCardData[],
);

/**
 * Reparte el mosaico sin huecos: la primera ocupa 2×2 en escritorio, las
 * cuatro siguientes llenan el bloque de al lado y las demás forman filas
 * de cuatro; la última fila incompleta se estira para cerrar la retícula.
 */
function tileClass(i: number, total: number): string {
    if (i === 0) {
        return total === 1
            ? '@2xl/sbpage:col-span-2 @5xl/sbpage:col-span-4 min-h-96'
            : '@2xl/sbpage:col-span-2 @5xl/sbpage:col-span-2 @5xl/sbpage:row-span-2 min-h-80 @5xl/sbpage:min-h-[34rem]';
    }

    const rest = total - 1;
    const classes = ['min-h-64'];
    const smWide = rest % 2 === 1 && i === total - 1;

    let lg = '';

    if (i <= 4) {
        const inBlock = Math.min(rest, 4);

        if (inBlock === 1) {
            lg = '@5xl/sbpage:col-span-2 @5xl/sbpage:row-span-2';
        } else if (inBlock === 2 || (inBlock === 3 && i === 3)) {
            lg = '@5xl/sbpage:col-span-2';
        }
    } else {
        const tail = rest - 4;
        const lastRow = tail % 4 || 4;
        const pos = i - 5;

        if (pos >= tail - lastRow) {
            if (lastRow === 1) {
                lg = '@5xl/sbpage:col-span-4';
            } else if (lastRow === 2 || (lastRow === 3 && pos === tail - 3)) {
                lg = '@5xl/sbpage:col-span-2';
            }
        }
    }

    if (smWide) {
        classes.push('@2xl/sbpage:col-span-2', lg || '@5xl/sbpage:col-span-1');
    } else if (lg) {
        classes.push(lg);
    }

    return classes.join(' ');
}
</script>

<template>
    <div
        v-if="categories.length"
        class="grid h-full auto-rows-[minmax(16rem,1fr)] gap-4 @2xl/sbpage:grid-cols-2 @5xl/sbpage:grid-cols-4"
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
    <DynamicEmpty
        v-else-if="bridge"
        title="Aún no hay categorías publicadas"
        body="En el sitio esta sección se oculta sola hasta que publiques al menos una categoría del catálogo."
    />
</template>
