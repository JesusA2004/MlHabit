<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { ICON_SIZE, ICON_STROKE } from './icons';
import type { IconSize } from './icons';

/** Icono dentro de una pastilla suave; reacciona al hover del `group` padre. */
const props = withDefaults(
    defineProps<{
        icon: Component;
        size?: IconSize;
        tone?: 'brand' | 'oak' | 'dark' | 'light';
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'md', tone: 'brand' },
);

const tones = {
    brand: 'bg-primary-soft text-primary group-hover:bg-primary group-hover:text-primary-foreground',
    oak: 'bg-[color-mix(in_srgb,var(--oak)_16%,transparent)] text-oak-ink group-hover:bg-oak group-hover:text-white',
    dark: 'bg-white/10 text-espresso-foreground group-hover:bg-oak group-hover:text-white',
    light: 'bg-card text-foreground ring-1 ring-border group-hover:ring-primary/40',
};

const boxes: Record<IconSize, string> = {
    xs: 'size-7 rounded-md',
    sm: 'size-9 rounded-lg',
    md: 'size-11 rounded-xl',
    lg: 'size-12 rounded-xl',
    xl: 'size-16 rounded-2xl',
};
</script>

<template>
    <span
        :class="
            cn(
                'inline-grid shrink-0 place-items-center transition-[background-color,color,transform,box-shadow] duration-300 ease-out group-hover:-rotate-3',
                boxes[props.size],
                tones[props.tone],
                props.class,
            )
        "
        aria-hidden="true"
    >
        <component
            :is="props.icon"
            :class="ICON_SIZE[props.size]"
            :stroke-width="ICON_STROKE"
        />
    </span>
</template>
