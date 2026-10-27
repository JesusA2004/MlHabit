<script setup lang="ts">
import { computed } from 'vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import HeroElevation from '@/components/web/public/HeroElevation.vue';
import { radiusCss, shadowCss, surfaceCss } from '../tokens';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Formas decorativas de la marca: rectángulo, círculo, aro, divisor, anillos
 * concéntricos, abanico de acabados y alzado de cocina. Siempre decorativas
 * (aria-hidden).
 */
const props = defineProps<{ block: Block }>();

const { str, dark } = useBlock(() => props.block);

const kind = computed(() => str('kind', 'rect'));
const color = computed(() => surfaceCss(str('color', 'primary-soft')));
const style = computed(() => ({
    borderRadius:
        kind.value === 'circle'
            ? '9999px'
            : radiusCss(props.block.style.radius),
    boxShadow: shadowCss(props.block.style.shadow),
}));
</script>

<template>
    <div class="relative flex flex-1 items-center" aria-hidden="true">
        <div
            v-if="kind === 'rect' || kind === 'circle'"
            class="absolute inset-0"
            :style="{ ...style, background: color }"
        />
        <div
            v-else-if="kind === 'ring'"
            class="absolute inset-0 rounded-full border-[clamp(6px,1.2cqi,18px)]"
            :style="{ borderColor: color }"
        />
        <div
            v-else-if="kind === 'line'"
            class="h-0.5 w-full rounded-full"
            :style="{ background: color }"
        />
        <svg
            v-else-if="kind === 'rings'"
            class="absolute inset-0 h-full w-full"
            viewBox="0 0 200 200"
            preserveAspectRatio="xMidYMid meet"
            :style="{ color }"
        >
            <circle
                cx="100"
                cy="100"
                r="96"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            />
            <circle
                cx="100"
                cy="100"
                r="64"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            />
            <circle cx="100" cy="100" r="32" fill="currentColor" />
        </svg>
        <FinishSwatches
            v-else-if="kind === 'swatches'"
            :tone="dark ? 'dark' : 'light'"
            class="mx-auto max-h-full w-full"
        />
        <HeroElevation
            v-else-if="kind === 'elevation'"
            class="mx-auto max-h-full w-full"
            :class="dark ? 'text-white' : 'text-foreground'"
        />
    </div>
</template>
