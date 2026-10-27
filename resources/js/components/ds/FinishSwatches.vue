<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Firma visual de ML Habitat: un abanico de muestras de acabados, como el
 * que el asesor abre sobre la mesa. Decorativo (aria-hidden). Se usa en
 * acceso, errores y como sustituto elegante cuando no hay fotografía.
 */
const props = withDefaults(
    defineProps<{
        /** `light` para fondos claros, `dark` para bandas espresso. */
        tone?: 'light' | 'dark';
        labels?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'light', labels: true },
);

const chips = [
    { fill: '#F3EEE7', ink: '#6B635A', name: 'Lino', angle: -30 },
    { fill: '#D9CFC2', ink: '#4A433C', name: 'Piedra', angle: -18 },
    { fill: '#B07D4F', ink: '#FFFFFF', name: 'Roble', angle: -6, grain: true },
    { fill: '#2F5A4C', ink: '#E5EEE9', name: 'Eucalipto', angle: 6 },
    { fill: '#2B2420', ink: '#D9CFC2', name: 'Espresso', angle: 18 },
];
</script>

<template>
    <svg
        viewBox="0 0 360 340"
        :class="cn('h-auto w-full select-none', props.class)"
        aria-hidden="true"
        focusable="false"
    >
        <defs>
            <filter
                id="swatch-shadow"
                x="-20%"
                y="-20%"
                width="140%"
                height="140%"
            >
                <feDropShadow
                    dx="0"
                    dy="6"
                    stdDeviation="8"
                    flood-color="#231E1A"
                    :flood-opacity="props.tone === 'dark' ? 0.45 : 0.14"
                />
            </filter>
            <pattern
                id="swatch-grain"
                width="64"
                height="18"
                patternUnits="userSpaceOnUse"
            >
                <path
                    d="M0 6 C16 2 30 10 64 5"
                    fill="none"
                    stroke="#8A6A4A"
                    stroke-opacity="0.35"
                    stroke-width="1"
                />
                <path
                    d="M0 14 C20 11 38 17 64 13"
                    fill="none"
                    stroke="#8A6A4A"
                    stroke-opacity="0.22"
                    stroke-width="0.8"
                />
            </pattern>
        </defs>

        <g
            v-for="chip in chips"
            :key="chip.name"
            :transform="`rotate(${chip.angle} 180 272)`"
        >
            <rect
                x="148"
                y="40"
                width="64"
                height="252"
                rx="12"
                :fill="chip.fill"
                filter="url(#swatch-shadow)"
            />
            <rect
                v-if="chip.grain"
                x="148"
                y="40"
                width="64"
                height="252"
                rx="12"
                fill="url(#swatch-grain)"
            />
            <rect
                x="148"
                y="40"
                width="64"
                height="252"
                rx="12"
                fill="none"
                :stroke="props.tone === 'dark' ? '#FFFFFF' : '#231E1A'"
                stroke-opacity="0.06"
            />
            <text
                v-if="props.labels"
                x="163"
                y="160"
                transform="rotate(-90 163 160)"
                :fill="chip.ink"
                font-family="Figtree, ui-sans-serif, sans-serif"
                font-size="10"
                font-weight="600"
                letter-spacing="0.2"
            >
                {{ chip.name }}
            </text>
        </g>

        <circle
            cx="180"
            cy="272"
            r="7"
            :fill="props.tone === 'dark' ? '#EFEAE3' : '#FFFFFF'"
        />
        <circle cx="180" cy="272" r="3" fill="#B07D4F" />
    </svg>
</template>
