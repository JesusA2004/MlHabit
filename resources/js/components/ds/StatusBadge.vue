<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { toneClasses } from './tones';
import type { Tone } from './tones';

/**
 * Chip de estado genérico. El significado lo da el texto (y el icono
 * opcional), nunca solo el color.
 */
const props = withDefaults(
    defineProps<{
        tone?: Tone;
        label: string;
        icon?: Component;
        /** `soft` (por defecto) o `solid` para estados que deben gritar. */
        variant?: 'soft' | 'solid' | 'outline';
        size?: 'sm' | 'md';
        dot?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'neutral', variant: 'soft', size: 'md', dot: true },
);
</script>

<template>
    <span
        data-slot="status-badge"
        :data-tone="props.tone"
        :class="
            cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-full font-medium whitespace-nowrap',
                props.size === 'sm'
                    ? 'h-5 px-2 text-[11px]'
                    : 'h-6 px-2.5 text-xs',
                props.variant === 'soft' && toneClasses[props.tone].soft,
                props.variant === 'solid' && toneClasses[props.tone].solid,
                props.variant === 'outline' &&
                    'bg-transparent text-foreground ring-1 ring-border ring-inset',
                props.class,
            )
        "
    >
        <component
            :is="props.icon"
            v-if="props.icon"
            class="size-3.5 shrink-0"
            aria-hidden="true"
        />
        <span
            v-else-if="props.dot"
            :class="
                cn(
                    'size-1.5 shrink-0 rounded-full',
                    props.variant === 'solid'
                        ? 'bg-current'
                        : toneClasses[props.tone].dot,
                )
            "
            aria-hidden="true"
        />
        {{ props.label }}
    </span>
</template>
