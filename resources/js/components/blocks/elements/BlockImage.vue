<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ImagePlus } from '@lucide/vue';
import { computed } from 'vue';
import type { StudioLink, StudioNode } from '@/studio/types';
import { boundPage, mediaFor, resolveLink, useRenderCtx } from '../context';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const media = computed(() => {
    if (node.props.bind === 'cover') {
        return mediaFor(ctx, boundPage(ctx, node)?.coverMediaId ?? null);
    }

    return mediaFor(ctx, node.props.media);
});

const alt = computed(() => String(node.props.alt || media.value?.alt || ''));
const link = computed(() => resolveLink(ctx, node.props.link as StudioLink | null));
const priority = computed(() => Boolean(node.props.priority));
</script>

<template>
    <figure v-if="media" class="sn-figure">
        <component
            :is="link?.internal ? Link : link ? 'a' : 'div'"
            :href="link?.href"
            :target="link?.newTab ? '_blank' : undefined"
            :rel="link?.external ? 'noopener noreferrer' : undefined"
            class="sn-frame"
        >
            <img
                :src="media.url!"
                :alt="alt"
                :width="media.width ?? undefined"
                :height="media.height ?? undefined"
                :loading="priority ? 'eager' : 'lazy'"
                :fetchpriority="priority ? 'high' : undefined"
                decoding="async"
            />
        </component>
        <figcaption v-if="node.props.caption" class="sn-caption type-caption">
            {{ node.props.caption }}
        </figcaption>
    </figure>
    <div v-else-if="ctx.editing" class="sn-frame sn-media-empty" data-media-empty>
        <ImagePlus class="size-7" aria-hidden="true" />
        <span>{{ node.props.bind === 'cover' ? 'La página del CMS no tiene portada' : 'Elegir fotografía' }}</span>
    </div>
</template>
