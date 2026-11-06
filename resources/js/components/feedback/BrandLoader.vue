<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { cn } from '@/lib/utils';

/**
 * Indicador de carga con la insignia de ML Hábitat: el logotipo al centro y
 * un aro de roble y eucalipto que gira alrededor. Con `progress` (0–100) el
 * aro se llena en vez de girar. Con movimiento reducido no gira: el aro
 * queda fijo y la insignia "respira" suavemente.
 */
const props = withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg';
        progress?: number | null;
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'md', progress: null },
);

const RADIUS = 46;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const dash = computed(() => {
    if (props.progress === null) {
        return null;
    }

    const value = Math.max(0, Math.min(100, props.progress));

    return CIRCUMFERENCE * (1 - value / 100);
});
</script>

<template>
    <span
        data-slot="brand-loader"
        aria-hidden="true"
        :class="
            cn(
                'relative inline-grid shrink-0 place-items-center',
                {
                    'size-12': props.size === 'sm',
                    'size-20': props.size === 'md',
                    'size-28': props.size === 'lg',
                },
                props.class,
            )
        "
    >
        <svg
            viewBox="0 0 100 100"
            class="absolute inset-0 size-full -rotate-90"
        >
            <circle
                cx="50"
                cy="50"
                :r="RADIUS"
                fill="none"
                stroke-width="3"
                class="stroke-primary/12"
            />
            <circle
                v-if="dash !== null"
                cx="50"
                cy="50"
                :r="RADIUS"
                fill="none"
                stroke-width="3.5"
                stroke-linecap="round"
                class="stroke-primary transition-[stroke-dashoffset] duration-300 ease-out motion-reduce:transition-none"
                :stroke-dasharray="CIRCUMFERENCE"
                :stroke-dashoffset="dash"
            />
            <g v-else class="brand-loader-spin">
                <circle
                    cx="50"
                    cy="50"
                    :r="RADIUS"
                    fill="none"
                    stroke-width="3.5"
                    stroke-linecap="round"
                    class="stroke-oak"
                    :stroke-dasharray="`${CIRCUMFERENCE * 0.22} ${CIRCUMFERENCE}`"
                />
                <circle
                    cx="50"
                    cy="50"
                    :r="RADIUS"
                    fill="none"
                    stroke-width="3.5"
                    stroke-linecap="round"
                    class="stroke-primary"
                    :stroke-dasharray="`${CIRCUMFERENCE * 0.18} ${CIRCUMFERENCE}`"
                    :stroke-dashoffset="-CIRCUMFERENCE * 0.5"
                />
            </g>
        </svg>
        <AppLogoIcon
            class-name="brand-loader-mark size-[74%] shadow-sm ring-1 ring-border/60"
            :sizes="props.size === 'lg' ? '96px' : '64px'"
        />
    </span>
</template>

<style scoped>
.brand-loader-spin {
    transform-origin: 50px 50px;
    animation: brand-loader-rotate 1.25s cubic-bezier(0.55, 0.15, 0.45, 0.85)
        infinite;
}

:deep(.brand-loader-mark) {
    animation: brand-loader-breathe 2.4s ease-in-out infinite;
}

@keyframes brand-loader-rotate {
    to {
        transform: rotate(360deg);
    }
}

@keyframes brand-loader-breathe {
    50% {
        transform: scale(0.96);
    }
}

@media (prefers-reduced-motion: reduce) {
    .brand-loader-spin {
        animation: none;
    }

    :deep(.brand-loader-mark) {
        animation: brand-loader-fade 2.4s ease-in-out infinite;
    }

    @keyframes brand-loader-fade {
        50% {
            opacity: 0.8;
        }
    }
}
</style>
