<script setup lang="ts">
import { ArrowUp } from '@lucide/vue';
import { prefersReducedMotion } from '@/lib/motion';

/**
 * Volver arriba con anillo de progreso de lectura. El anillo usa la variable
 * `--scroll-progress` que mantiene el runtime de movimiento.
 */
function toTop() {
    window.scrollTo({
        top: 0,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
    document.getElementById('contenido')?.focus({ preventScroll: true });
}
</script>

<template>
    <button
        type="button"
        class="group relative grid size-14 place-items-center rounded-full text-espresso-foreground transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-oak"
        aria-label="Volver arriba"
        @click="toTop"
    >
        <svg
            viewBox="0 0 56 56"
            class="absolute inset-0 size-full -rotate-90"
            aria-hidden="true"
        >
            <circle
                cx="28"
                cy="28"
                r="26"
                fill="none"
                stroke="currentColor"
                stroke-opacity="0.15"
                stroke-width="1.5"
            />
            <circle
                cx="28"
                cy="28"
                r="26"
                fill="none"
                stroke="var(--oak)"
                stroke-width="1.5"
                stroke-linecap="round"
                pathLength="1"
                class="progress-ring"
            />
        </svg>
        <ArrowUp
            class="size-5 transition-transform duration-300 ease-out group-hover:-translate-y-1"
            :stroke-width="1.5"
            aria-hidden="true"
        />
    </button>
</template>

<style scoped>
.progress-ring {
    stroke-dasharray: 1;
    stroke-dashoffset: calc(1 - var(--scroll-progress, 0));
}
</style>
