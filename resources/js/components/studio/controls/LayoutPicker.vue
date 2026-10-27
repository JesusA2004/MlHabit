<script setup lang="ts">
import { computed } from 'vue';
import type { ThumbRect } from '@/studio/presets';
import { sectionDef } from '@/studio/schema';
import PresetThumb from './PresetThumb.vue';

/** Selector visual de composición de una sección. */
const props = defineProps<{ sectionType: string; modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const THUMBS: Record<string, ThumbRect[]> = {
    'hero.editorial': [[6, 20, 46, 8, 'title'], [6, 32, 36, 8, 'title'], [6, 46, 18, 5, 'accent'], [60, 8, 34, 48, 'media']],
    'hero.split': [[0, 0, 50, 64, 'card'], [6, 24, 36, 8, 'title'], [6, 38, 18, 5, 'accent'], [50, 0, 50, 64, 'media']],
    'hero.cinematic': [[0, 0, 100, 64, 'media'], [8, 38, 50, 8, 'title'], [8, 50, 18, 5, 'accent']],
    'hero.centered': [[20, 16, 60, 9, 'title'], [30, 30, 40, 5, 'text'], [40, 42, 20, 5, 'accent']],
    'hero.asymmetric': [[0, 4, 54, 56, 'media'], [60, 30, 34, 8, 'title'], [60, 44, 18, 5, 'accent']],
    'hero.framed': [[10, 6, 28, 52, 'media'], [48, 22, 44, 8, 'title'], [48, 36, 18, 5, 'accent']],
    'hero.video': [[25, 4, 50, 7, 'title'], [10, 18, 80, 42, 'media']],
    'columns.one': [[25, 10, 50, 6, 'title'], [25, 22, 50, 4, 'text'], [25, 30, 50, 4, 'text'], [25, 38, 36, 4, 'text']],
    'columns.two': [[4, 8, 44, 48, 'card'], [52, 8, 44, 48, 'card']],
    'columns.two-wide-left': [[4, 8, 54, 48, 'card'], [62, 8, 34, 48, 'card']],
    'columns.two-wide-right': [[4, 8, 34, 48, 'card'], [42, 8, 54, 48, 'card']],
    'columns.three': [[4, 8, 28, 48, 'card'], [36, 8, 28, 48, 'card'], [68, 8, 28, 48, 'card']],
    'columns.bento': [[4, 4, 54, 56, 'card'], [62, 4, 34, 26, 'card'], [62, 34, 34, 26, 'card']],
    'posts.cards': [[4, 12, 28, 40, 'media'], [36, 12, 28, 40, 'media'], [68, 12, 28, 40, 'media']],
    'posts.magazine': [[4, 4, 54, 56, 'media'], [62, 4, 34, 26, 'media'], [62, 34, 34, 26, 'media']],
    'posts.spotlight': [[4, 4, 92, 30, 'media'], [4, 38, 28, 22, 'media'], [36, 38, 28, 22, 'media'], [68, 38, 28, 22, 'media']],
    'posts.carousel': [[4, 8, 32, 48, 'media'], [40, 8, 32, 48, 'media'], [76, 8, 24, 48, 'media']],
    'posts.list': [[4, 10, 8, 6, 'accent'], [16, 10, 70, 6, 'title'], [4, 28, 8, 6, 'accent'], [16, 28, 70, 6, 'title'], [4, 46, 8, 6, 'accent'], [16, 46, 70, 6, 'title']],
    'categories.cards': [[4, 10, 28, 44, 'card'], [36, 10, 28, 44, 'card'], [68, 10, 28, 44, 'card']],
    'categories.list': [[4, 10, 92, 10, 'card'], [4, 27, 92, 10, 'card'], [4, 44, 92, 10, 'card']],
    'categories.bento': [[4, 4, 54, 56, 'card'], [62, 4, 34, 26, 'card'], [62, 34, 34, 26, 'card']],
    'gallery.grid': [[4, 4, 28, 26, 'media'], [36, 4, 28, 26, 'media'], [68, 4, 28, 26, 'media'], [4, 34, 28, 26, 'media'], [36, 34, 28, 26, 'media'], [68, 34, 28, 26, 'media']],
    'gallery.bento': [[4, 4, 44, 56, 'media'], [52, 4, 20, 26, 'media'], [76, 4, 20, 26, 'media'], [52, 34, 44, 26, 'media']],
    'gallery.masonry': [[4, 4, 28, 34, 'media'], [36, 4, 28, 22, 'media'], [68, 4, 28, 40, 'media'], [4, 42, 28, 18, 'media'], [36, 30, 28, 30, 'media']],
    'gallery.carousel': [[4, 8, 36, 48, 'media'], [44, 8, 36, 48, 'media'], [84, 8, 16, 48, 'media']],
    'gallery.spotlight': [[4, 4, 92, 40, 'media'], [4, 48, 14, 12, 'media'], [22, 48, 14, 12, 'media'], [40, 48, 14, 12, 'media']],
    'timeline.vertical': [[8, 4, 2, 56, 'accent'], [16, 6, 60, 8, 'card'], [16, 26, 60, 8, 'card'], [16, 46, 60, 8, 'card']],
    'timeline.alternating': [[49, 2, 2, 60, 'accent'], [8, 8, 34, 10, 'card'], [58, 26, 34, 10, 'card'], [8, 44, 34, 10, 'card']],
    'timeline.chapters': [[4, 4, 50, 26, 'media'], [60, 10, 34, 6, 'title'], [46, 34, 50, 26, 'media'], [6, 40, 34, 6, 'title']],
    'cta.band': [[0, 10, 100, 44, 'accent'], [25, 24, 50, 7, 'title'], [40, 38, 20, 5, 'card']],
    'cta.card': [[10, 6, 80, 52, 'card'], [25, 22, 50, 7, 'title'], [40, 36, 20, 5, 'accent']],
    'cta.split': [[4, 18, 40, 7, 'title'], [4, 32, 18, 5, 'accent'], [52, 6, 44, 52, 'media']],
    'social.cards': [[4, 16, 28, 22, 'card'], [36, 16, 28, 22, 'card'], [68, 16, 28, 22, 'card']],
    'social.list': [[4, 10, 92, 10, 'card'], [4, 27, 92, 10, 'card'], [4, 44, 92, 10, 'card']],
    'social.pills': [[4, 26, 26, 10, 'card'], [34, 26, 26, 10, 'card'], [64, 26, 26, 10, 'card']],
};

const layouts = computed(() => Object.entries(sectionDef(props.sectionType)?.layouts ?? {}));
</script>

<template>
    <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Composición">
        <button
            v-for="[key, layout] in layouts"
            :key="key"
            type="button"
            role="radio"
            :aria-checked="modelValue === key"
            class="flex flex-col gap-1.5 rounded-lg border p-1.5 text-left transition-colors hover:border-brand/60"
            :class="modelValue === key ? 'border-brand bg-brand/10 ring-1 ring-brand' : 'border-border'"
            @click="emit('update:modelValue', key)"
        >
            <PresetThumb :rects="THUMBS[`${sectionType}.${key}`] ?? []" />
            <span class="px-0.5 text-[11px] leading-tight font-medium">{{ layout.label }}</span>
        </button>
    </div>
</template>
