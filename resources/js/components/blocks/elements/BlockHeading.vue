<script setup lang="ts">
import { computed } from 'vue';
import type { StudioNode } from '@/studio/types';
import { boundPage, useRenderCtx } from '../context';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const tag = computed(() => (['h1', 'h2', 'h3', 'h4'].includes(String(node.props.level)) ? String(node.props.level) : 'h2'));
const bound = computed(() => node.props.bind === 'title');
const text = computed(() => {
    if (bound.value) return boundPage(ctx, node)?.title ?? (ctx.editing ? 'Título de la página (CMS)' : '');

    return String(node.props.text ?? '');
});
const lines = computed(() => text.value.split('\n').filter((l, i, all) => l !== '' || i < all.length - 1));
</script>

<template>
    <component
        :is="tag"
        v-if="text"
        class="sn-heading sn-t"
        :class="`type-display-${node.props.size === 'title' ? 'title' : node.props.size ?? 'lg'}`"
        :data-inline="bound ? undefined : 'text'"
    >
        <span v-for="(line, i) in lines" :key="i" class="block">{{ line }}</span>
    </component>
</template>
