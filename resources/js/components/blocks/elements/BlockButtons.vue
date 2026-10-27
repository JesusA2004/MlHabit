<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import type { StudioLink, StudioNode } from '@/studio/types';
import { resolveLink, useRenderCtx } from '../context';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

interface Item {
    label: string;
    link: StudioLink | null;
    variant: string;
}

const buttons = computed(() =>
    ((node.props.items as Item[]) ?? [])
        .map((item) => ({ ...item, resolved: resolveLink(ctx, item.link) }))
        .filter((item) => item.label && (item.resolved || ctx.editing)),
);
</script>

<template>
    <div
        v-if="buttons.length"
        class="sn-buttons"
        :class="{ 'sn-buttons--stack': node.props.stackOnMobile }"
    >
        <template v-for="(item, i) in buttons" :key="i">
            <Link
                v-if="item.resolved?.internal"
                :href="item.resolved.href"
                class="btn-editorial"
                :class="`btn-editorial--${item.variant}`"
            >
                {{ item.label }}
            </Link>
            <a
                v-else-if="item.resolved"
                :href="item.resolved.href"
                :target="item.resolved.newTab ? '_blank' : undefined"
                :rel="item.resolved.external ? 'noopener noreferrer' : undefined"
                class="btn-editorial"
                :class="`btn-editorial--${item.variant}`"
            >
                {{ item.label }}
                <ArrowUpRight
                    v-if="item.resolved.external && item.variant !== 'primary'"
                    class="size-4"
                    aria-hidden="true"
                />
                <span v-if="item.resolved.newTab" class="sr-only"> (se abre en una pestaña nueva)</span>
            </a>
            <span
                v-else
                class="btn-editorial sn-placeholder-btn"
                :class="`btn-editorial--${item.variant}`"
                title="El destino no está configurado (p. ej. red social sin URL)"
            >
                {{ item.label }}
            </span>
        </template>
    </div>
</template>
