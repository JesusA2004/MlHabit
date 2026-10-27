<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { ResponsiveImageData } from '@/types/web';

/**
 * Imagen responsiva: <picture> con AVIF/WebP, srcset/sizes, dimensiones
 * intrínsecas (evita saltos de diseño) y color de marcador mientras carga.
 * `priority` la carga de inmediato con fetchpriority alto (imagen del hero).
 */
const props = withDefaults(
    defineProps<{
        image: ResponsiveImageData;
        sizes?: string;
        priority?: boolean;
        alt?: string;
        class?: HTMLAttributes['class'];
        imgClass?: HTMLAttributes['class'];
    }>(),
    { priority: false },
);

const sizes = computed(() => props.sizes ?? props.image.sizes);
const alt = computed(() => props.alt ?? props.image.alt ?? '');
</script>

<template>
    <picture
        :class="cn('block', props.class)"
        :style="
            props.image.placeholder
                ? { backgroundColor: props.image.placeholder }
                : undefined
        "
    >
        <source
            v-for="source in props.image.sources"
            :key="source.type"
            :type="source.type"
            :srcset="source.srcset"
            :sizes="sizes"
        />
        <img
            :src="props.image.src"
            :srcset="props.image.srcset ?? undefined"
            :sizes="props.image.srcset ? sizes : undefined"
            :width="props.image.width ?? undefined"
            :height="props.image.height ?? undefined"
            :alt="alt"
            :loading="props.priority ? 'eager' : 'lazy'"
            :fetchpriority="props.priority ? 'high' : 'auto'"
            decoding="async"
            :class="cn('h-full w-full object-cover', props.imgClass)"
        />
    </picture>
</template>
