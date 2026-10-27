<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/** Padding y ancho estándar de una pantalla del panel o del portal. */
const props = withDefaults(
    defineProps<{
        width?: 'narrow' | 'default' | 'wide' | 'full';
        class?: HTMLAttributes['class'];
    }>(),
    { width: 'default' },
);

const widths = {
    narrow: 'max-w-3xl',
    default: 'max-w-7xl',
    wide: 'max-w-[96rem]',
    full: 'max-w-none',
} as const;
</script>

<template>
    <div
        data-slot="page-container"
        :class="
            cn(
                'mx-auto flex w-full flex-1 flex-col gap-6 px-4 py-5 md:px-6 md:py-6 lg:px-8 lg:py-8',
                widths[props.width],
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
