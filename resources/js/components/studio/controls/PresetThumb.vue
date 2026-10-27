<script setup lang="ts">
import type { ThumbRect } from '@/studio/presets';

/** Miniatura esquemática de una composición (100×64). */
defineProps<{ rects: ThumbRect[] }>();

const FILL: Record<ThumbRect[4], string> = {
    title: 'var(--color-foreground)',
    text: 'color-mix(in srgb, var(--color-muted-foreground) 70%, transparent)',
    media: 'color-mix(in srgb, var(--color-brand) 55%, var(--color-muted))',
    accent: 'var(--color-brand)',
    card: 'color-mix(in srgb, var(--color-foreground) 10%, transparent)',
};
</script>

<template>
    <svg viewBox="0 0 100 64" class="block h-auto w-full" aria-hidden="true">
        <rect width="100" height="64" rx="3" fill="var(--color-muted)" />
        <rect
            v-for="(r, i) in rects"
            :key="i"
            :x="r[0]"
            :y="r[1]"
            :width="r[2]"
            :height="r[3]"
            :rx="r[4] === 'title' || r[4] === 'text' ? 1 : 2"
            :fill="FILL[r[4]]"
        />
    </svg>
</template>
