<script setup lang="ts">
import { computed } from 'vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import HeroElevation from '@/components/web/public/HeroElevation.vue';
import HeroScene from '@/components/web/visual/HeroScene.vue';
import Marquee from '@/components/web/visual/Marquee.vue';
import type { MarqueeItem } from '@/components/web/visual/Marquee.vue';
import { radiusCss, shadowCss, surfaceCss } from '../tokens';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Formas decorativas de la marca: rectángulo, círculo, aro, divisor, anillos
 * concéntricos, abanico de acabados y alzado de cocina. Siempre decorativas
 * (aria-hidden), salvo la marquesina de materiales, que es una región con
 * nombre (su copia duplicada es decorativa).
 *
 * - `interior`: escena ilustrada de cocina (portada sin fotografía).
 * - `materials`: marquesina a sangre con muestras de materiales.
 * - `slats`: panel de listones de madera, como el muro del logotipo.
 */
const props = defineProps<{ block: Block }>();

const { str, dark, editing } = useBlock(() => props.block);

const MATERIALS: MarqueeItem[] = [
    { label: 'Roble natural', swatch: '#B07D4F' },
    { label: 'Laca mate', swatch: '#F4F1EC' },
    { label: 'Eucalipto', swatch: '#2F5A4C' },
    { label: 'Nogal', swatch: '#6B4A33' },
    { label: 'Cuarzo', swatch: '#DAD6CF' },
    { label: 'Herrajes de cierre suave', swatch: '#8E8A84' },
    { label: 'Encino ahumado', swatch: '#8A6A4A' },
    { label: 'Piedra', swatch: '#CFC6B8' },
];
const MATERIALS_B: MarqueeItem[] = [
    { label: 'Cocinas integrales', swatch: '#2F5A4C' },
    { label: 'Clósets y vestidores', swatch: '#B07D4F' },
    { label: 'Recámaras', swatch: '#E9E2D6' },
    { label: 'Centros de entretenimiento', swatch: '#6B4A33' },
    { label: 'Zapateras', swatch: '#CFC6B8' },
    { label: 'Baños y lavanderías', swatch: '#8A6A4A' },
];
const SLATS = Array.from({ length: 14 }, (_, i) => i);

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
    <div
        v-if="kind === 'materials'"
        class="flex flex-1 flex-col justify-center gap-3"
        :class="!editing && 'sb-bleed'"
    >
        <Marquee
            :items="MATERIALS"
            :tone="dark ? 'dark' : 'light'"
            label="Materiales y acabados"
            :duration="52"
        />
        <Marquee
            :items="MATERIALS_B"
            :tone="dark ? 'dark' : 'light'"
            label="Espacios que diseñamos"
            :duration="60"
            reverse
            class="opacity-70"
        />
    </div>
    <div v-else class="relative flex flex-1 items-center" aria-hidden="true">
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
        <HeroScene
            v-else-if="kind === 'interior'"
            class="absolute inset-0"
            :chips="!editing"
        />
        <div
            v-else-if="kind === 'slats'"
            class="absolute inset-0 flex gap-[3%] overflow-hidden p-[6%]"
            :style="{ ...style, background: color }"
        >
            <span
                v-for="i in SLATS"
                :key="i"
                class="flex-1 rounded-full bg-gradient-to-b from-[#c39062] via-[#b07d4f] to-[#8a5f3a] shadow-[inset_-2px_0_0_rgb(0_0_0/0.12)]"
                :style="{ opacity: i % 4 === 0 ? 0.85 : 1 }"
            />
            <span
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(70%_60%_at_30%_20%,rgb(255_236_200/0.35),transparent_70%)]"
            />
        </div>
        <HeroElevation
            v-else-if="kind === 'elevation'"
            class="mx-auto max-h-full w-full"
            :class="dark ? 'text-white' : 'text-foreground'"
        />
    </div>
</template>
