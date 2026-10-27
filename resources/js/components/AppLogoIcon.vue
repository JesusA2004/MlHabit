<script setup lang="ts">
import { useId } from 'vue';
import type { HTMLAttributes } from 'vue';

/**
 * Monograma ML Habitat: la M cuyo trazo final se convierte en la L, sobre
 * eucalipto con un canto de roble (como el canto de un tablero).
 * `variant="mono"` hereda `currentColor` para fondos de color.
 */
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        className?: HTMLAttributes['class'];
        variant?: 'color' | 'mono';
        title?: string;
    }>(),
    { variant: 'color' },
);

const clipId = `ml-tile-${useId()}`;
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="props.className"
        :role="props.title ? 'img' : undefined"
        :aria-hidden="props.title ? undefined : 'true'"
        :aria-label="props.title"
        v-bind="$attrs"
    >
        <template v-if="props.variant === 'color'">
            <defs>
                <clipPath :id="clipId">
                    <rect width="32" height="32" rx="8" />
                </clipPath>
            </defs>
            <g :clip-path="`url(#${clipId})`">
                <rect width="32" height="32" fill="#2F5A4C" />
                <rect y="26.5" width="32" height="5.5" fill="#B07D4F" />
            </g>
            <path
                d="M7.5 21.5V9l6.5 7 6.5-7v12.5H25"
                fill="none"
                stroke="#FFFFFF"
                stroke-width="2.6"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </template>
        <path
            v-else
            d="M7.5 21.5V9l6.5 7 6.5-7v12.5H25"
            fill="none"
            stroke="currentColor"
            stroke-width="2.6"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
