<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/** Manchas de luz cálida que respiran despacio detrás de un bloque. */
const props = withDefaults(
    defineProps<{
        tone?: 'light' | 'dark';
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'light' },
);
</script>

<template>
    <div
        :class="
            cn(
                'pointer-events-none absolute inset-0 -z-10 overflow-hidden',
                props.class,
            )
        "
        aria-hidden="true"
    >
        <span
            class="blob -top-24 -left-16 size-80"
            :style="{
                background:
                    props.tone === 'dark'
                        ? 'rgb(176 125 79 / 0.35)'
                        : 'rgb(176 125 79 / 0.22)',
            }"
        />
        <span
            class="blob top-1/3 -right-24 size-96"
            :style="{
                background:
                    props.tone === 'dark'
                        ? 'rgb(47 90 76 / 0.55)'
                        : 'rgb(47 90 76 / 0.14)',
                '--blob-duration': '28s',
            }"
        />
        <span
            class="blob -bottom-32 left-1/3 size-72"
            :style="{
                background:
                    props.tone === 'dark'
                        ? 'rgb(244 239 232 / 0.08)'
                        : 'rgb(217 207 194 / 0.5)',
                '--blob-duration': '34s',
            }"
        />
    </div>
</template>
