<script setup lang="ts">
import { computed } from 'vue';
import PostBody from '@/components/public/PostBody.vue';
import type { StudioNode } from '@/studio/types';
import { boundPage, useRenderCtx } from '../context';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const page = computed(() => boundPage(ctx, node));
const hasBody = computed(() => {
    const body = page.value?.body as { content?: unknown[] } | null | undefined;

    return Array.isArray(body?.content) && body.content.length > 0;
});
</script>

<template>
    <PostBody v-if="page && hasBody" :content="page.body" class="sn-prose" />
    <p v-else-if="ctx.editing" class="sn-placeholder">
        {{ page ? `«${page.title}» aún no tiene contenido. Escríbelo en Contenido → Páginas.` : 'Elige la página del CMS cuyo contenido se mostrará.' }}
    </p>
</template>
