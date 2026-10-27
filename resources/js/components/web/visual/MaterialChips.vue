<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Chips de material que flotan suavemente (y con parallax) alrededor de una
 * composición. Decorativos: nombres de la paleta de acabados de la marca.
 */
const props = defineProps<{ class?: HTMLAttributes['class'] }>();

const chips = [
    {
        name: 'Roble',
        bg: '#B07D4F',
        ink: '#fff',
        pos: 'top-[6%] left-[2%]',
        rot: '-6deg',
        speed: '0.06',
        dur: '7s',
        grain: true,
    },
    {
        name: 'Eucalipto',
        bg: '#2F5A4C',
        ink: '#E5EEE9',
        pos: 'top-[18%] right-[0%]',
        rot: '5deg',
        speed: '-0.05',
        dur: '8.5s',
    },
    {
        name: 'Lino',
        bg: '#F3EEE7',
        ink: '#4A433C',
        pos: 'bottom-[14%] left-[8%]',
        rot: '4deg',
        speed: '0.09',
        dur: '9s',
    },
    {
        name: 'Espresso',
        bg: '#2B2420',
        ink: '#EFEAE3',
        pos: 'bottom-[4%] right-[12%]',
        rot: '-4deg',
        speed: '-0.08',
        dur: '7.5s',
    },
];
</script>

<template>
    <div
        :class="cn('pointer-events-none absolute inset-0', props.class)"
        aria-hidden="true"
    >
        <span
            v-for="chip in chips"
            :key="chip.name"
            :class="cn('absolute', chip.pos)"
            :data-parallax="chip.speed"
        >
            <span
                class="float-soft inline-flex items-center gap-2 rounded-full py-1.5 pr-3.5 pl-1.5 text-xs font-medium shadow-[0_12px_28px_-12px_rgb(35_30_26/0.45)] ring-1 ring-black/5"
                :style="{
                    backgroundColor: chip.bg,
                    color: chip.ink,
                    '--float-rotate': chip.rot,
                    '--float-duration': chip.dur,
                    rotate: chip.rot,
                }"
            >
                <span
                    :class="
                        cn(
                            'size-5 rounded-full ring-1 ring-white/30',
                            chip.grain && 'tex-grain',
                        )
                    "
                    :style="{
                        backgroundColor: chip.bg,
                        filter: 'brightness(0.9)',
                    }"
                />
                {{ chip.name }}
            </span>
        </span>
    </div>
</template>
