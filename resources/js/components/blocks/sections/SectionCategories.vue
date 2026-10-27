<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import type { StudioNode } from '@/studio/types';
import { useRenderCtx } from '../context';
import SectionHeader from '../shared/SectionHeader.vue';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const items = computed(() => ctx.resolved.categories);
const layout = computed(() => String(node.props.layout ?? 'cards'));

function href(item: (typeof items.value)[number]): string {
    return item.post ? `/tips/${item.post.slug}` : `/tips?categoria=${item.category.slug}`;
}
</script>

<template>
    <div class="sn-container" :class="`w-${node.props.width ?? 'normal'}`">
        <SectionHeader :eyebrow="String(node.props.eyebrow ?? '')" :title="String(node.props.title ?? '')" />

        <p v-if="!items.length" class="sn-empty">Pronto habrá categorías para explorar.</p>

        <ol v-else-if="layout === 'list'" class="sn-cat-list">
            <li v-for="(item, i) in items" :key="item.category.slug" data-stagger-item>
                <Link :href="href(item)" class="sn-cat-list-row group" :style="{ '--cat': item.category.color }">
                    <span class="sn-cat-num font-display">{{ String(i + 1).padStart(2, '0') }}</span>
                    <span class="sn-cat-name type-display-md">{{ item.category.name }}</span>
                    <span class="sn-cat-post">
                        <span class="type-eyebrow">{{ item.post ? 'Empieza con' : 'Explora' }}</span>
                        <span class="link-underline">{{ item.post?.title ?? item.category.description }}</span>
                    </span>
                    <ArrowUpRight class="sn-cat-arrow size-6" aria-hidden="true" />
                </Link>
            </li>
        </ol>

        <div v-else class="sn-cat-grid" :class="{ 'sn-cat-grid--bento': layout === 'bento' }">
            <Link
                v-for="(item, i) in items"
                :key="item.category.slug"
                :href="href(item)"
                class="sn-cat-card group hover-lift"
                :style="{ '--cat': item.category.color }"
                data-stagger-item
            >
                <div v-if="item.post?.coverUrl" class="sn-cat-card-media img-zoom" aria-hidden="true">
                    <img :src="item.post.coverUrl" alt="" loading="lazy" decoding="async" />
                </div>
                <div class="sn-cat-card-body">
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="type-eyebrow sn-card-category">{{ item.category.name }}</span>
                        <span class="sn-cat-num font-display">{{ String(i + 1).padStart(2, '0') }}</span>
                    </div>
                    <p class="sn-cat-card-title">
                        {{ item.post ? item.post.title : item.category.description }}
                    </p>
                    <p v-if="item.post?.excerpt" class="sn-card-excerpt">{{ item.post.excerpt }}</p>
                    <p v-else-if="!item.post" class="sn-card-excerpt">Aún no hay un tip destacado en esta categoría.</p>
                    <span class="sn-card-more">
                        {{ item.post ? 'Leer el tip' : 'Ver categoría' }}
                        <ArrowUpRight class="size-4" aria-hidden="true" />
                    </span>
                </div>
            </Link>
        </div>
    </div>
</template>
