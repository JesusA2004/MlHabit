<script setup lang="ts">
import { Clapperboard, Pause, Play } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import type { StudioNode } from '@/studio/types';
import { mediaFor, useRenderCtx } from '../context';

const { node, background = false } = defineProps<{
    node: StudioNode;
    /** Usado como fondo de sección (hero cinematográfico). */
    background?: boolean;
}>();
const ctx = useRenderCtx();

const media = computed(() => mediaFor(ctx, node.props.media));
const poster = computed(() => mediaFor(ctx, node.props.poster)?.url ?? media.value?.posterUrl ?? undefined);
const captions = computed(() => mediaFor(ctx, node.props.captions));

const reduced = ref(false);
const video = ref<HTMLVideoElement | null>(null);
const paused = ref(true);

onMounted(() => {
    reduced.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
});

// Como fondo: silenciado, en bucle y sin controles nativos, con botón de
// pausa propio (WCAG 2.2.2). Autoplay solo silenciado y sin reduced-motion.
const autoplay = computed(() => (background || Boolean(node.props.autoplay)) && !reduced.value && !ctx.editing);
const muted = computed(() => background || autoplay.value || Boolean(node.props.muted));
const controls = computed(() => !background && Boolean(node.props.controls));
const loop = computed(() => background || Boolean(node.props.loop));

function toggle() {
    if (!video.value) return;
    if (video.value.paused) void video.value.play();
    else video.value.pause();
}
</script>

<template>
    <figure v-if="media" class="sn-figure" :class="{ 'sn-video-bg': background }">
        <div class="sn-frame">
            <video
                ref="video"
                :src="media.url!"
                :poster="poster"
                :controls="controls"
                :autoplay="autoplay"
                :muted="muted"
                :loop="loop"
                playsinline
                :preload="autoplay ? 'auto' : 'metadata'"
                :aria-label="String(node.props.title || '') || undefined"
                @play="paused = false"
                @pause="paused = true"
            >
                <track
                    v-if="captions"
                    kind="subtitles"
                    srclang="es"
                    label="Español"
                    :src="captions.url!"
                    default
                />
            </video>
            <button
                v-if="!controls"
                type="button"
                class="sn-video-toggle"
                :aria-label="paused ? 'Reproducir video' : 'Pausar video'"
                @click="toggle"
            >
                <Play v-if="paused" class="size-4" aria-hidden="true" />
                <Pause v-else class="size-4" aria-hidden="true" />
            </button>
        </div>
    </figure>
    <div v-else-if="ctx.editing" class="sn-frame sn-media-empty" data-media-empty>
        <Clapperboard class="size-7" aria-hidden="true" />
        <span>Elegir video</span>
    </div>
</template>
