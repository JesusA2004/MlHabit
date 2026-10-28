<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Anillo de avance (0–100). El trazo se dibuja al montarse (CSS, se apaga
 * con movimiento reducido). El valor también va en texto para lectores.
 */
const props = withDefaults(
    defineProps<{
        value: number;
        size?: number;
        stroke?: number;
        label: string;
        inverted?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { size: 132, stroke: 10, inverted: false },
);

const pct = computed(() => Math.min(100, Math.max(0, Math.round(props.value))));
const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const offset = computed(() => circumference.value * (1 - pct.value / 100));
</script>

<template>
    <div
        role="img"
        :aria-label="`${label}: ${pct} %`"
        :class="
            cn('relative inline-grid shrink-0 place-items-center', props.class)
        "
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <svg
            :width="size"
            :height="size"
            :viewBox="`0 0 ${size} ${size}`"
            class="-rotate-90"
            aria-hidden="true"
        >
            <defs>
                <linearGradient
                    :id="`ring-${size}-${inverted ? 'i' : 'n'}`"
                    x1="0"
                    y1="0"
                    x2="1"
                    y2="1"
                >
                    <stop
                        offset="0%"
                        :stop-color="inverted ? '#f4efe8' : 'var(--primary)'"
                    />
                    <stop offset="100%" stop-color="var(--oak)" />
                </linearGradient>
            </defs>
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                :stroke-width="stroke"
                :class="inverted ? 'stroke-white/12' : 'stroke-muted'"
            />
            <circle
                class="portal-ring-value"
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                :stroke="`url(#ring-${size}-${inverted ? 'i' : 'n'})`"
                :stroke-width="stroke"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="offset"
                :style="{ '--ring-circumference': `${circumference}` }"
            />
        </svg>
        <div
            class="absolute inset-0 grid place-content-center text-center"
            aria-hidden="true"
        >
            <slot :value="pct">
                <span
                    :class="
                        cn(
                            'font-display text-3xl font-semibold tracking-tight tabular-nums',
                            inverted ? 'text-white' : 'text-foreground',
                        )
                    "
                    >{{ pct }}<span class="text-base">%</span></span
                >
            </slot>
        </div>
    </div>
</template>
