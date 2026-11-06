<script setup lang="ts">
import { computed, inject } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { pageContainerContextKey } from './pageContext';
import type { PageWidth } from './pageContext';

/**
 * Padding y ancho estándar de una pantalla del panel o del portal.
 *
 * - `full`: todo el ancho disponible, sin tope. Es el valor por defecto en
 *   el panel, el portal y la app de técnicos (los layouts lo proveen): las
 *   pantallas se acomodan en rejillas responsivas en vez de encogerse.
 * - `fluid`: todo el ancho con tope de 1920 px.
 * - `default`: 1280 px (valor por defecto fuera de un layout autenticado).
 * - `narrow` / `wide`: 768 / 1536 px. Solo para casos puntuales; en las
 *   apps internas se prefiere `full` con columnas.
 *
 * Dentro del panel, los hijos directos entran escalonados al navegar
 * (`admin-enter`, ver app.css); con movimiento reducido no se anima.
 */
const props = defineProps<{
    width?: PageWidth;
    class?: HTMLAttributes['class'];
}>();

const context = inject(pageContainerContextKey, null);

const widths: Record<PageWidth, string> = {
    narrow: 'max-w-3xl',
    default: 'max-w-7xl',
    wide: 'max-w-[96rem]',
    fluid: 'max-w-[120rem]',
    full: 'max-w-none',
};

const width = computed<PageWidth>(
    () => props.width ?? context?.defaultWidth ?? 'default',
);
</script>

<template>
    <div
        data-slot="page-container"
        :data-width="width"
        :class="
            cn(
                'mx-auto flex w-full min-w-0 flex-1 flex-col gap-6 px-4 py-5 md:px-6 md:py-6 xl:px-8 xl:py-8 2xl:px-10',
                widths[width],
                context?.animate && 'admin-enter',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
