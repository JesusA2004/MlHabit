<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import PostBody from '@/components/public/PostBody.vue';
import type { StudioNode } from '@/studio/types';
import type { TiptapContent } from '@/types';
import { formatDate, mediaFor, splitParagraphs, useRenderCtx } from '../context';
import SectionHeader from '../shared/SectionHeader.vue';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

interface Item {
    key: string;
    label: string;
    title: string;
    paragraphs: string[];
    body: TiptapContent | null;
    imageUrl: string | null;
    imageAlt: string;
}

function hasBody(body: TiptapContent | null): boolean {
    const content = (body as { content?: unknown[] } | null)?.content;

    return Array.isArray(content) && content.length > 0;
}

const items = computed<Item[]>(() => {
    if (node.props.source === 'manual') {
        return ((node.props.items as Record<string, unknown>[]) ?? []).map((item, i) => {
            const media = mediaFor(ctx, item.media);

            return {
                key: `m${i}`,
                label: String(item.label ?? ''),
                title: String(item.title ?? ''),
                paragraphs: splitParagraphs(String(item.text ?? '')),
                body: null,
                imageUrl: media?.url ?? null,
                imageAlt: media?.alt ?? '',
            };
        });
    }

    return (ctx.resolved.entries[String(node.props.page)] ?? []).map((entry) => ({
        key: `e${entry.id}`,
        label: formatDate(entry.date),
        title: entry.title,
        paragraphs: [],
        body: hasBody(entry.body) ? entry.body : null,
        imageUrl: entry.coverUrl,
        imageAlt: entry.coverAlt ?? '',
    }));
});

const layout = computed(() => String(node.props.layout ?? 'vertical'));
const expanded = ref(new Set<string>());

function toggle(key: string) {
    const next = new Set(expanded.value);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    expanded.value = next;
}
</script>

<template>
    <div class="sn-container" :class="`w-${node.props.width ?? 'normal'}`">
        <SectionHeader :eyebrow="String(node.props.eyebrow ?? '')" :title="String(node.props.title ?? '')" />

        <p v-if="!items.length" class="sn-empty">
            Este camino apenas comienza. Las primeras entradas se publicarán muy pronto.
        </p>

        <ol v-else class="sn-timeline" :class="`sn-timeline--${layout}`">
            <li v-for="(item, i) in items" :key="item.key" class="sn-timeline-item" data-stagger-item>
                <span class="sn-timeline-dot" aria-hidden="true" />
                <div v-if="item.imageUrl" class="sn-timeline-media sn-frame img-zoom">
                    <img :src="item.imageUrl" :alt="item.imageAlt" loading="lazy" decoding="async" />
                </div>
                <div class="sn-timeline-body">
                    <p class="type-eyebrow sn-eyebrow">
                        <template v-if="layout === 'chapters'">Capítulo {{ String(i + 1).padStart(2, '0') }} · </template>{{ item.label }}
                    </p>
                    <h3 class="sn-timeline-title" :class="layout === 'chapters' ? 'type-display-md' : 'type-title'">{{ item.title }}</h3>
                    <p v-for="(p, j) in item.paragraphs" :key="j" class="type-body sn-muted">{{ p }}</p>
                    <template v-if="item.body">
                        <button
                            type="button"
                            class="sn-timeline-toggle"
                            :aria-expanded="expanded.has(item.key)"
                            @click="toggle(item.key)"
                        >
                            {{ expanded.has(item.key) ? 'Ocultar' : 'Leer la entrada' }}
                            <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': expanded.has(item.key) }" aria-hidden="true" />
                        </button>
                        <PostBody v-if="expanded.has(item.key)" :content="item.body" class="sn-prose" />
                    </template>
                </div>
            </li>
        </ol>
    </div>
</template>
