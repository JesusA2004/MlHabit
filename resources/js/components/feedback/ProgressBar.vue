<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Barra de progreso de la marca. Con `value` numérico (0–100) es
 * determinada; con `value = null` es indeterminada (franja que recorre la
 * barra; con movimiento reducido queda una franja fija que respira).
 */
const props = withDefaults(
    defineProps<{
        value?: number | null;
        label?: string;
        tone?: 'primary' | 'success' | 'danger' | 'warning';
        size?: 'xs' | 'sm' | 'md';
        class?: HTMLAttributes['class'];
    }>(),
    { value: null, tone: 'primary', size: 'sm', label: undefined },
);

const clamped = computed(() =>
    props.value === null
        ? null
        : Math.max(0, Math.min(100, Math.round(props.value))),
);

const toneClass = computed(
    () =>
        ({
            primary: 'bg-primary',
            success: 'bg-success',
            danger: 'bg-danger',
            warning: 'bg-warning',
        })[props.tone],
);
</script>

<template>
    <div
        role="progressbar"
        :aria-label="props.label"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="clamped ?? undefined"
        :aria-valuetext="clamped === null ? 'En proceso' : `${clamped}%`"
        :class="
            cn(
                'relative w-full overflow-hidden rounded-full bg-primary/12',
                {
                    'h-1': props.size === 'xs',
                    'h-1.5': props.size === 'sm',
                    'h-2.5': props.size === 'md',
                },
                props.class,
            )
        "
    >
        <div
            v-if="clamped !== null"
            :class="
                cn(
                    'h-full rounded-full transition-[width] duration-300 ease-out motion-reduce:transition-none',
                    toneClass,
                )
            "
            :style="{ width: `${clamped}%` }"
        />
        <div
            v-else
            :class="
                cn(
                    'ml-progress-indeterminate absolute inset-y-0 w-2/5 rounded-full',
                    toneClass,
                )
            "
        />
    </div>
</template>

<style scoped>
.ml-progress-indeterminate {
    animation: ml-progress-slide 1.4s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}

@keyframes ml-progress-slide {
    from {
        transform: translateX(-110%);
    }
    to {
        transform: translateX(260%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .ml-progress-indeterminate {
        left: 30%;
        animation: ml-progress-breathe 2.4s ease-in-out infinite;
    }

    @keyframes ml-progress-breathe {
        50% {
            opacity: 0.45;
        }
    }
}
</style>
