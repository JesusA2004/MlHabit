<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Marca de éxito para el final de un flujo (pico emocional): el círculo y la
 * palomita se dibujan y las muestras de acabados se abren alrededor.
 * Decorativa; el mensaje real lo da el titular. Sin movimiento con
 * `prefers-reduced-motion`.
 */
const props = defineProps<{ class?: HTMLAttributes['class'] }>();

const chips = [
    { color: '#B07D4F', angle: -150 },
    { color: '#2F5A4C', angle: -100 },
    { color: '#D9CFC2', angle: -55 },
    { color: '#2B2420', angle: -10 },
    { color: '#B07D4F', angle: 35 },
    { color: '#2F5A4C', angle: 80 },
    { color: '#D9CFC2', angle: 130 },
    { color: '#4A3E36', angle: 175 },
];
</script>

<template>
    <span
        :class="
            cn(
                'success-mark relative inline-grid size-16 place-items-center',
                props.class,
            )
        "
        aria-hidden="true"
    >
        <span
            v-for="(chip, i) in chips"
            :key="i"
            class="success-chip absolute top-1/2 left-1/2 size-2.5 rounded-[3px]"
            :style="{
                backgroundColor: chip.color,
                '--a': `${chip.angle}deg`,
                animationDelay: `${520 + i * 25}ms`,
            }"
        />
        <svg viewBox="0 0 64 64" class="relative size-16">
            <circle cx="32" cy="32" r="30" class="fill-success-soft" />
            <circle
                cx="32"
                cy="32"
                r="30"
                fill="none"
                stroke="var(--success)"
                stroke-width="2"
                pathLength="1"
                class="success-ring"
            />
            <path
                d="M20 33l8 8 16-17"
                fill="none"
                stroke="var(--success-ink)"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
                pathLength="1"
                class="success-check"
            />
        </svg>
    </span>
</template>

<style scoped>
.success-ring,
.success-check {
    stroke-dasharray: 1;
    stroke-dashoffset: 0;
}

.success-chip {
    opacity: 0;
}

@media (prefers-reduced-motion: no-preference) {
    .success-ring {
        animation: draw 700ms var(--motion-ease, ease-out) both;
    }

    .success-check {
        animation: draw 420ms var(--motion-ease, ease-out) 480ms both;
    }

    .success-chip {
        animation: burst 900ms var(--motion-ease, ease-out) both;
    }
}

@keyframes draw {
    from {
        stroke-dashoffset: 1;
    }
}

@keyframes burst {
    0% {
        opacity: 0;
        transform: translate(-50%, -50%) rotate(var(--a)) translateX(0)
            scale(0.4);
    }
    35% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -50%) rotate(var(--a)) translateX(46px)
            rotate(90deg) scale(1);
    }
}
</style>
