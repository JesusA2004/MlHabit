<script setup lang="ts">
import { ImagePlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { ResolvedMedia, StudioNode } from '@/studio/types';
import { mediaFor, useRenderCtx } from '../context';
import Carousel from '../shared/Carousel.vue';
import SectionHeader from '../shared/SectionHeader.vue';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

interface PhotoMedia {
    url: string | null;
    alt: string | null;
    width?: number | null;
    height?: number | null;
}
interface Photo {
    key: number;
    media: PhotoMedia;
    caption: string;
}

const layout = computed(() => String(node.props.layout ?? 'grid'));
const photos = computed<Photo[]>(() => {
    // Origen: galería de la publicación actual (plantilla de Tips).
    if (node.props.source === 'post') {
        return (ctx.resolved.post?.gallery ?? [])
            .filter((g) => Boolean(g.url))
            .map((g) => ({ key: g.id, media: { url: g.url, alt: g.alt }, caption: '' }));
    }

    // Origen: fotografías elegidas manualmente.
    return ((node.props.items as { media: number | null; caption: string }[]) ?? [])
        .map((item, i) => ({ key: i, media: mediaFor(ctx, item.media), caption: item.caption }))
        .filter((p): p is { key: number; media: ResolvedMedia; caption: string } => p.media !== null);
});
const active = ref(0);
const current = computed(() => photos.value[Math.min(active.value, photos.value.length - 1)]);
</script>

<template>
    <div class="sn-container" :class="`w-${node.props.width ?? 'normal'}`">
        <SectionHeader :eyebrow="String(node.props.eyebrow ?? '')" :title="String(node.props.title ?? '')" />

        <div v-if="!photos.length && ctx.editing" class="sn-frame sn-media-empty" data-media-empty>
            <ImagePlus class="size-7" aria-hidden="true" />
            <span>Agrega fotografías a la galería desde el panel de propiedades</span>
        </div>

        <Carousel v-else-if="layout === 'carousel'" :label="String(node.props.title || 'Galería')">
            <figure v-for="photo in photos" :key="photo.key" class="sn-gallery-item sn-gallery-item--slide">
                <div class="sn-frame"><img :src="photo.media!.url!" :alt="photo.media!.alt ?? photo.caption ?? ''" loading="lazy" decoding="async" /></div>
                <figcaption v-if="photo.caption" class="sn-caption type-caption">{{ photo.caption }}</figcaption>
            </figure>
        </Carousel>

        <div v-else-if="layout === 'spotlight' && current" class="sn-gallery-spotlight">
            <figure class="sn-gallery-item">
                <div class="sn-frame sn-gallery-hero">
                    <img :key="current.key" :src="current.media!.url!" :alt="current.media!.alt ?? current.caption ?? ''" decoding="async" />
                </div>
                <figcaption v-if="current.caption" class="sn-caption type-caption">{{ current.caption }}</figcaption>
            </figure>
            <div v-if="photos.length > 1" class="sn-gallery-thumbs" role="tablist" aria-label="Elegir fotografía">
                <button
                    v-for="(photo, i) in photos"
                    :key="photo.key"
                    type="button"
                    role="tab"
                    :aria-selected="i === active"
                    :aria-label="`Fotografía ${i + 1}`"
                    :class="{ 'is-active': i === active }"
                    @click="active = i"
                >
                    <img :src="photo.media!.url!" alt="" loading="lazy" decoding="async" />
                </button>
            </div>
        </div>

        <div v-else class="sn-gallery" :class="`sn-gallery--${layout}`">
            <figure v-for="photo in photos" :key="photo.key" class="sn-gallery-item" data-stagger-item>
                <div class="sn-frame img-zoom">
                    <img
                        :src="photo.media!.url!"
                        :alt="photo.media!.alt ?? photo.caption ?? ''"
                        :width="photo.media!.width ?? undefined"
                        :height="photo.media!.height ?? undefined"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
                <figcaption v-if="photo.caption" class="sn-caption type-caption">{{ photo.caption }}</figcaption>
            </figure>
        </div>
    </div>
</template>
