<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import type { StudioNode } from '@/studio/types';
import { formatDate, useRenderCtx } from '../context';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const post = computed(() => ctx.resolved.post);

const showCategory = computed(() => node.props.showCategory !== false);
const showDate = computed(() => node.props.showDate !== false);
const showReadingTime = computed(() => node.props.showReadingTime !== false);

const category = computed(() => (showCategory.value ? (post.value?.category ?? null) : null));
const date = computed(() => (showDate.value ? formatDate(post.value?.publishedAt ?? null) : ''));
const minutes = computed(() => (showReadingTime.value ? (post.value?.readingMinutes ?? 0) : 0));

const hasAny = computed(() => Boolean(category.value) || Boolean(date.value) || minutes.value > 0);
</script>

<template>
    <div v-if="post && hasAny" class="sn-post-meta">
        <Link
            v-if="category"
            :href="`/tips?categoria=${category.slug}`"
            class="sn-post-meta-cat"
            :style="{ '--cat': category.color }"
        >
            <span class="sn-post-meta-dot" aria-hidden="true" />
            {{ category.name }}
        </Link>

        <span v-if="date" class="sn-post-meta-item">{{ date }}</span>

        <span v-if="minutes > 0" class="sn-post-meta-item sn-post-meta-time">
            <Clock class="size-3.5" aria-hidden="true" />
            {{ minutes }} min de lectura
        </span>
    </div>

    <div v-else-if="ctx.editing" class="sn-post-meta sn-post-meta--placeholder">
        <span class="sn-post-meta-cat sn-post-meta-cat--ghost">
            <span class="sn-post-meta-dot" aria-hidden="true" />
            Categoría
        </span>
        <span class="sn-post-meta-item">1 de enero de 2026</span>
        <span class="sn-post-meta-item sn-post-meta-time">
            <Clock class="size-3.5" aria-hidden="true" />
            5 min de lectura
        </span>
    </div>
</template>
