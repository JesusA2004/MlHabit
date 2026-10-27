<script setup lang="ts">
import { computed } from 'vue';
import type { StudioNode } from '@/studio/types';
import { boundPage, mediaFor, useRenderCtx } from '../context';
import SlotView from '../SlotView.vue';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const layout = computed(() => String(node.props.layout ?? 'editorial'));
const width = computed(() => String(node.props.width ?? 'normal'));
const height = computed(() => String(node.props.height ?? 'auto'));
// Nunca menos de 0.25 de oscurecimiento sobre foto/video: el texto debe
// seguir siendo legible aunque la imagen sea clara.
const overlay = computed(() => Math.max(0.25, Number(node.props.overlay ?? 0.45)));

/** ¿Hay multimedia real que mostrar? Si no, la composición se recompone. */
const hasMedia = computed(() => {
    const items = (node.slots?.media ?? []).filter((child) => !child.hidden);
    if (ctx.editing) return items.length > 0;

    return items.some((child) => {
        if (child.type === 'quote') return true;
        if (child.type === 'image' && child.props.bind === 'cover') return !!mediaFor(ctx, boundPage(ctx, child)?.coverMediaId);

        return !!mediaFor(ctx, child.props.media);
    });
});

const isBackground = computed(() => layout.value === 'cinematic');
</script>

<template>
    <div
        class="sn-hero"
        :class="[
            `sn-hero--${layout}`,
            `sn-hero--h-${height}`,
            {
                'sn-hero--media-left': node.props.mediaSide === 'left',
                'sn-hero--no-media': !hasMedia,
            },
        ]"
        :style="{ '--hero-overlay': overlay }"
    >
        <div v-if="isBackground && hasMedia" class="sn-hero-bg">
            <SlotView :section="node" name="media" />
        </div>
        <div class="sn-container" :class="`w-${width}`">
            <div class="sn-grid sn-hero-grid">
                <SlotView :section="node" name="content" class="sn-hero-content" />
                <SlotView
                    v-if="!isBackground && (hasMedia || ctx.editing)"
                    :section="node"
                    name="media"
                    class="sn-hero-media"
                />
            </div>
        </div>
        <div v-if="isBackground && !hasMedia && ctx.editing" class="sn-hero-bg-hint">
            <SlotView :section="node" name="media" />
        </div>
    </div>
</template>
