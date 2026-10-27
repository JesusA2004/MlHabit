<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import type { PostSummary } from '@/types';
import { formatDate } from '../context';

/**
 * Tarjeta de artículo única del sitio (Home, Tips, relacionados, Studio).
 * Variantes:
 * - default:    imagen 3:2 arriba, texto abajo
 * - feature:    imagen 4:5 grande con texto debajo, título display
 * - horizontal: imagen a la izquierda, texto a la derecha
 * - overlay:    texto sobre la imagen con degradado (portadas)
 */
withDefaults(
    defineProps<{
        post: PostSummary;
        variant?: 'default' | 'feature' | 'horizontal' | 'overlay';
        showExcerpt?: boolean;
        showDate?: boolean;
        headingLevel?: 'h2' | 'h3';
    }>(),
    { variant: 'default', showExcerpt: true, showDate: true, headingLevel: 'h3' },
);
</script>

<template>
    <article class="sn-card group" :class="`sn-card--${variant}`">
        <Link :href="`/tips/${post.slug}`" class="sn-card-link">
            <div class="sn-card-media img-zoom">
                <img
                    v-if="post.coverUrl"
                    :src="post.coverUrl"
                    :alt="post.title"
                    loading="lazy"
                    decoding="async"
                />
                <div v-else class="sn-card-media-empty" aria-hidden="true">
                    <span class="font-display">{{ post.title.charAt(0) }}</span>
                </div>
            </div>
            <div class="sn-card-body">
                <p class="sn-card-meta type-eyebrow">
                    <span
                        v-if="post.category"
                        class="sn-card-category"
                        :style="{ '--cat': post.category.color }"
                        >{{ post.category.name }}</span
                    >
                    <span v-if="showDate && post.publishedAt" class="sn-card-date">{{ formatDate(post.publishedAt) }}</span>
                </p>
                <component :is="headingLevel" class="sn-card-title">
                    <span class="link-underline">{{ post.title }}</span>
                </component>
                <p v-if="showExcerpt && post.excerpt" class="sn-card-excerpt">
                    {{ post.excerpt }}
                </p>
                <span class="sn-card-more" aria-hidden="true">
                    Leer <ArrowUpRight class="size-4" />
                </span>
            </div>
        </Link>
    </article>
</template>
