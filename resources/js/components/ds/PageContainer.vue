<script setup lang="ts">
import { computed, inject } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { pageContainerContextKey } from './pageContext';
import type { PageWidth } from './pageContext';

/**
 * Padding y ancho estándar de una pantalla del panel o del portal.
 *
 * - `fluid`: todo el ancho disponible (tope de 1920 px en monitores muy
 *   grandes). Es el valor por defecto dentro del panel (`AppSidebarLayout`
 *   lo provee): listados, tableros, tablas y kanban.
 * - `default`: 1280 px, lectura cómoda (valor por defecto fuera del panel).
 * - `narrow`: formularios y páginas de lectura.
 * - `wide`: 1536 px.
 * - `full`: sin tope.
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
                'mx-auto flex w-full min-w-0 flex-1 flex-col gap-6 px-4 py-5 md:px-6 md:py-6 lg:px-8 lg:py-8 2xl:px-10',
                widths[width],
                context?.animate && 'admin-enter',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
