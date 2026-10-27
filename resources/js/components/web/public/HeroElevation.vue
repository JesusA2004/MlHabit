<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Alzado de una cocina dibujado con los acabados de la marca (lino, piedra,
 * roble, eucalipto, espresso): la portada tipográfica cuando no hay foto.
 * Decorativo. Única entrada orquestada de la portada: los frentes se
 * "instalan" en secuencia; con movimiento reducido aparecen sin animar.
 */
const props = defineProps<{ class?: HTMLAttributes['class'] }>();

const LINO = '#F3EEE7';
const PIEDRA = '#D9CFC2';
const ROBLE = '#B07D4F';
const EUCALIPTO = '#2F5A4C';
const ESPRESSO = '#4A3E36';

type Front = {
    x: number;
    y: number;
    w: number;
    h: number;
    fill: string;
    grain?: boolean;
    handle: 'top' | 'side-l' | 'side-r';
};

const uppers: Front[] = [
    { x: 40, y: 48, w: 98, h: 150, fill: LINO, handle: 'side-r' },
    { x: 142, y: 48, w: 98, h: 150, fill: LINO, handle: 'side-l' },
    { x: 244, y: 48, w: 98, h: 150, fill: PIEDRA, handle: 'side-r' },
];

const lowers: Front[] = [
    { x: 40, y: 300, w: 98, h: 52, fill: ROBLE, grain: true, handle: 'top' },
    { x: 40, y: 356, w: 98, h: 52, fill: ROBLE, grain: true, handle: 'top' },
    { x: 40, y: 412, w: 98, h: 52, fill: ROBLE, grain: true, handle: 'top' },
    { x: 142, y: 300, w: 98, h: 164, fill: EUCALIPTO, handle: 'side-r' },
    { x: 244, y: 300, w: 98, h: 164, fill: EUCALIPTO, handle: 'side-l' },
    { x: 346, y: 48, w: 98, h: 416, fill: ESPRESSO, handle: 'side-l' },
];

const fronts = [...uppers, ...lowers];

function handle(f: Front) {
    if (f.handle === 'top') {
        return { x: f.x + f.w / 2 - 18, y: f.y + 9, w: 36, h: 3 };
    }

    const x = f.handle === 'side-r' ? f.x + f.w - 10 : f.x + 7;
    const y = f.y > 200 || f.h > 300 ? f.y + 14 : f.y + f.h - 54;

    return { x, y, w: 3, h: 40 };
}

const legend = [
    { name: 'Lino', fill: LINO },
    { name: 'Piedra', fill: PIEDRA },
    { name: 'Roble', fill: ROBLE },
    { name: 'Eucalipto', fill: EUCALIPTO },
    { name: 'Espresso', fill: ESPRESSO },
];
</script>

<template>
    <figure :class="cn('select-none', props.class)" aria-hidden="true">
        <svg viewBox="0 0 484 520" class="h-auto w-full" focusable="false">
            <defs>
                <pattern
                    id="elevation-grain"
                    width="98"
                    height="10"
                    patternUnits="userSpaceOnUse"
                >
                    <path
                        d="M0 4 C22 1 40 7 98 3"
                        fill="none"
                        stroke="#000"
                        stroke-opacity="0.09"
                    />
                </pattern>
            </defs>

            <!-- Cota: "a tu medida" -->
            <g class="elev-line" stroke="currentColor" stroke-opacity="0.35">
                <line x1="40" y1="22" x2="444" y2="22" />
                <line x1="40" y1="16" x2="40" y2="28" />
                <line x1="444" y1="16" x2="444" y2="28" />
            </g>
            <rect x="196" y="12" width="92" height="20" class="fill-espresso" />
            <text
                x="242"
                y="26"
                text-anchor="middle"
                font-size="12"
                fill="currentColor"
                fill-opacity="0.7"
                style="font-family: var(--font-sans)"
            >
                a tu medida
            </text>

            <!-- Cubierta y zoclo -->
            <rect
                class="elev-front"
                style="--d: 420ms"
                x="28"
                y="284"
                width="322"
                height="12"
                rx="2"
                :fill="PIEDRA"
            />
            <rect
                x="40"
                y="468"
                width="404"
                height="10"
                rx="1"
                fill="#1E1916"
            />
            <line
                x1="16"
                y1="480"
                x2="468"
                y2="480"
                stroke="currentColor"
                stroke-opacity="0.3"
            />

            <!-- Salpicadero: líneas finas -->
            <g stroke="currentColor" stroke-opacity="0.12">
                <line x1="40" y1="230" x2="342" y2="230" />
                <line x1="40" y1="256" x2="342" y2="256" />
            </g>

            <g
                v-for="(f, i) in fronts"
                :key="i"
                class="elev-front"
                :style="{ '--d': `${80 + i * 70}ms` }"
            >
                <rect
                    :x="f.x"
                    :y="f.y"
                    :width="f.w"
                    :height="f.h"
                    rx="3"
                    :fill="f.fill"
                />
                <rect
                    v-if="f.grain"
                    :x="f.x"
                    :y="f.y"
                    :width="f.w"
                    :height="f.h"
                    rx="3"
                    fill="url(#elevation-grain)"
                />
                <rect
                    :x="handle(f).x"
                    :y="handle(f).y"
                    :width="handle(f).w"
                    :height="handle(f).h"
                    rx="1.5"
                    :fill="
                        f.fill === LINO || f.fill === PIEDRA
                            ? '#8A6A4A'
                            : '#E9DFD2'
                    "
                    fill-opacity="0.85"
                />
            </g>
        </svg>
        <figcaption
            class="mt-5 flex flex-wrap gap-x-4 gap-y-2 text-xs text-white/65"
        >
            <span
                v-for="item in legend"
                :key="item.name"
                class="inline-flex items-center gap-1.5"
            >
                <span
                    class="size-2.5 rounded-full ring-1 ring-white/25"
                    :style="{ backgroundColor: item.fill }"
                />
                {{ item.name }}
            </span>
        </figcaption>
    </figure>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .elev-front {
        animation: elev-install 720ms var(--ease-out, ease-out) both;
        animation-delay: var(--d, 0ms);
    }
}

@keyframes elev-install {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
