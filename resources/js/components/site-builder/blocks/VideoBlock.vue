<script setup lang="ts">
import { Video } from '@lucide/vue';
import { computed } from 'vue';
import { radiusCss, shadowCss } from '../tokens';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Video público de la biblioteca (silenciado si se reproduce solo). */
const props = defineProps<{ block: Block }>();

const { section, bridge, bool } = useBlock(() => props.block);

const video = computed(() => {
    const id = props.block.props.mediaId;

    return typeof id === 'number'
        ? (section?.value.videos[String(id)] ?? null)
        : null;
});
const autoplay = computed(() => bool('autoplay', true) && !bridge);
</script>

<template>
    <div
        class="relative flex-1 overflow-hidden bg-espresso"
        :style="{
            borderRadius: radiusCss(props.block.style.radius),
            boxShadow: shadowCss(props.block.style.shadow),
        }"
    >
        <video
            v-if="video && video.src"
            class="sb-video absolute inset-0 h-full w-full"
            :poster="video.poster ?? undefined"
            :autoplay="autoplay"
            :loop="bool('loop', true)"
            :controls="bool('controls', false)"
            :muted="autoplay || !bool('controls', false)"
            playsinline
            preload="metadata"
        >
            <source :src="video.src" :type="video.mime ?? undefined" />
        </video>
        <div
            v-else-if="bridge"
            class="absolute inset-0 grid place-items-center text-sm text-white/70"
        >
            <span class="inline-flex items-center gap-2"
                ><Video class="size-4" aria-hidden="true" /> Elige un video en
                el panel derecho</span
            >
        </div>
    </div>
</template>
