<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Abanico de muestras (firma de la marca) en HTML/CSS: las láminas giran
 * desde un perno y se abren más al pasar el cursor por el `group` padre o
 * por el propio abanico. Decorativo.
 */
const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        labels?: boolean;
    }>(),
    { labels: true },
);

const blades = [
    { name: 'Lino', bg: '#F3EEE7', ink: '#6B635A', rest: -34, open: -52 },
    { name: 'Piedra', bg: '#D9CFC2', ink: '#4A433C', rest: -22, open: -32 },
    {
        name: 'Roble',
        bg: '#B07D4F',
        ink: '#FFFFFF',
        rest: -10,
        open: -12,
        grain: true,
    },
    { name: 'Eucalipto', bg: '#2F5A4C', ink: '#E5EEE9', rest: 2, open: 8 },
    { name: 'Espresso', bg: '#2B2420', ink: '#D9CFC2', rest: 14, open: 28 },
];
</script>

<template>
    <div
        :class="
            cn(
                'swatch-fan group/fan relative aspect-square w-56 select-none',
                props.class,
            )
        "
        aria-hidden="true"
    >
        <span
            v-for="(blade, i) in blades"
            :key="blade.name"
            class="swatch-blade absolute bottom-[12%] left-[46%] flex h-[78%] w-[24%] origin-[50%_92%] items-start justify-center rounded-[10px] pt-3 shadow-[0_10px_24px_-10px_rgb(35_30_26/0.45)] ring-1 ring-black/5"
            :class="blade.grain && 'tex-grain'"
            :style="{
                backgroundColor: blade.bg,
                color: blade.ink,
                '--rest': `${blade.rest}deg`,
                '--open': `${blade.open}deg`,
                transitionDelay: `${i * 30}ms`,
                zIndex: i,
            }"
        >
            <span
                v-if="props.labels"
                class="text-[10px] font-medium tracking-wide [writing-mode:vertical-rl]"
                >{{ blade.name }}</span
            >
            <span
                class="absolute bottom-[6%] left-1/2 size-2 -translate-x-1/2 rounded-full bg-black/20 ring-1 ring-white/30"
            />
        </span>
    </div>
</template>

<style scoped>
.swatch-blade {
    transform: rotate(var(--rest));
    transition: transform 700ms var(--motion-ease, ease-out);
}

.swatch-fan:hover .swatch-blade,
:global(.group:hover) .swatch-blade,
:global(.group:focus-visible) .swatch-blade {
    transform: rotate(var(--open));
}
</style>
