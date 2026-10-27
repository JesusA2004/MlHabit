<script setup lang="ts">
import { computed } from 'vue';
import type { StudioNode } from '@/studio/types';
import { boundPage, splitParagraphs, useRenderCtx } from '../context';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const bound = computed(() => node.props.bind === 'intro');
const text = computed(() => {
    if (bound.value) return boundPage(ctx, node)?.intro ?? '';

    return String(node.props.text ?? '');
});
const paragraphs = computed(() => splitParagraphs(text.value));
const sizeClass = computed(() => ({ lead: 'type-lead', body: 'type-body', small: 'type-caption' })[String(node.props.size)] ?? 'type-body');
</script>

<template>
    <div v-if="paragraphs.length" class="sn-text" :data-inline="bound ? undefined : 'text'" data-inline-multiline>
        <p v-for="(p, i) in paragraphs" :key="i" class="sn-t" :class="sizeClass">
            {{ p }}
        </p>
    </div>
    <p v-else-if="ctx.editing && bound" class="sn-placeholder">
        La introducción de esta página está vacía. Escríbela en Contenido → Páginas.
    </p>
</template>
