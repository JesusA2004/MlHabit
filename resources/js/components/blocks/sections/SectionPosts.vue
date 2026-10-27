<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { StudioNode } from '@/studio/types';
import { formatDate, useRenderCtx } from '../context';
import Carousel from '../shared/Carousel.vue';
import EditorialCard from '../shared/EditorialCard.vue';
import SectionHeader from '../shared/SectionHeader.vue';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const posts = computed(() => ctx.resolved.feeds[node.id] ?? []);
const layout = computed(() => String(node.props.layout ?? 'cards'));
const showExcerpt = computed(() => Boolean(node.props.showExcerpt));
const showDate = computed(() => Boolean(node.props.showDate));
const moreHref = computed(() =>
    node.props.source === 'category' && node.props.category ? `/tips?categoria=${node.props.category}` : '/tips',
);
</script>

<template>
    <div class="sn-container" :class="`w-${node.props.width ?? 'normal'}`">
        <SectionHeader
            :eyebrow="String(node.props.eyebrow ?? '')"
            :title="String(node.props.title ?? '')"
            :link-label="String(node.props.linkLabel ?? '')"
            :link-href="moreHref"
        />

        <p v-if="!posts.length" class="sn-empty">
            Todavía no hay tips publicados aquí. Vuelve pronto: lo bueno se está escribiendo.
        </p>

        <div v-else-if="layout === 'magazine'" class="sn-posts-magazine">
            <EditorialCard
                v-for="(post, i) in posts"
                :key="post.id"
                :post="post"
                :variant="i === 0 ? 'feature' : i < 3 ? 'horizontal' : 'default'"
                :show-excerpt="showExcerpt && i < 3"
                :show-date="showDate"
                data-stagger-item
            />
        </div>

        <div v-else-if="layout === 'spotlight'" class="sn-posts-spotlight">
            <EditorialCard
                :post="posts[0]"
                variant="overlay"
                :show-excerpt="showExcerpt"
                :show-date="showDate"
                data-stagger-item
            />
            <div v-if="posts.length > 1" class="sn-posts-grid">
                <EditorialCard
                    v-for="post in posts.slice(1)"
                    :key="post.id"
                    :post="post"
                    :show-excerpt="false"
                    :show-date="showDate"
                    data-stagger-item
                />
            </div>
        </div>

        <Carousel v-else-if="layout === 'carousel'" :label="String(node.props.title || 'Publicaciones')">
            <EditorialCard
                v-for="post in posts"
                :key="post.id"
                :post="post"
                variant="feature"
                :show-excerpt="showExcerpt"
                :show-date="showDate"
            />
        </Carousel>

        <ol v-else-if="layout === 'list'" class="sn-posts-list">
            <li v-for="(post, i) in posts" :key="post.id" data-stagger-item>
                <Link :href="`/tips/${post.slug}`" class="sn-posts-list-row group">
                    <span class="sn-posts-list-num font-display">{{ String(i + 1).padStart(2, '0') }}</span>
                    <span class="min-w-0 flex-1">
                        <span v-if="post.category" class="type-eyebrow sn-card-category" :style="{ '--cat': post.category.color }">{{ post.category.name }}</span>
                        <span class="sn-posts-list-title type-title"><span class="link-underline">{{ post.title }}</span></span>
                        <span v-if="showExcerpt && post.excerpt" class="sn-card-excerpt">{{ post.excerpt }}</span>
                    </span>
                    <span v-if="showDate" class="sn-posts-list-date type-caption">{{ formatDate(post.publishedAt) }}</span>
                    <span v-if="post.coverUrl" class="sn-posts-list-thumb" aria-hidden="true">
                        <img :src="post.coverUrl" alt="" loading="lazy" decoding="async" />
                    </span>
                </Link>
            </li>
        </ol>

        <div v-else class="sn-posts-grid">
            <EditorialCard
                v-for="post in posts"
                :key="post.id"
                :post="post"
                :show-excerpt="showExcerpt"
                :show-date="showDate"
                data-stagger-item
            />
        </div>
    </div>
</template>
