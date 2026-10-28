<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatNumber } from '@/lib/format';

/**
 * Cifra que cuenta hasta su valor al aparecer (y entre valores cuando
 * cambia). Nunca inventa números: parte de 0 la primera vez y termina
 * exactamente en `value`. Los lectores de pantalla leen solo el valor final;
 * con movimiento reducido se muestra el valor de inmediato.
 */
const props = withDefaults(
    defineProps<{
        value: number;
        /** Cómo se pinta cada cuadro (por defecto, número es-MX). */
        format?: (value: number) => string;
        duration?: number;
        /** Decimales que se conservan durante la animación. */
        decimals?: number;
    }>(),
    { duration: 900, decimals: 0, format: undefined },
);

const render = (n: number) =>
    props.format ? props.format(n) : formatNumber(n);

function prefersReducedMotion(): boolean {
    return (
        typeof window === 'undefined' ||
        typeof window.matchMedia !== 'function' ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

// Arranca en 0 para no mostrar un cuadro con la cifra final antes de contar.
const shown = ref(prefersReducedMotion() ? props.value : 0);
let frame = 0;

function animate(from: number, to: number) {
    cancelAnimationFrame(frame);

    if (from === to || prefersReducedMotion()) {
        shown.value = to;

        return;
    }

    const factor = 10 ** props.decimals;
    const start = performance.now();

    const tick = (now: number) => {
        const t = Math.min(1, (now - start) / props.duration);
        const eased = 1 - Math.pow(1 - t, 3);
        const current = from + (to - from) * eased;

        shown.value = t < 1 ? Math.round(current * factor) / factor : to;

        if (t < 1) {
            frame = requestAnimationFrame(tick);
        }
    };

    frame = requestAnimationFrame(tick);
}

onMounted(() => animate(0, props.value));
watch(
    () => props.value,
    (to, from) => animate(from ?? 0, to),
);
onBeforeUnmount(() => cancelAnimationFrame(frame));
</script>

<template>
    <span class="tabular-nums">
        <span aria-hidden="true">{{ render(shown) }}</span>
        <span class="sr-only">{{ render(props.value) }}</span>
    </span>
</template>
