<script setup lang="ts">
import { computed } from 'vue';
import { vReveal } from '@/lib/reveal';
import type { StudioNode } from '@/studio/types';
import BlockUnsupported from './elements/BlockUnsupported.vue';
import { useRenderCtx } from './context';
import { sectionComponents } from './sectionRegistry';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const component = computed(() => sectionComponents[node.type] ?? null);
const tone = computed(() => String(node.props.tone ?? 'ink'));
const spacing = computed(() => String(node.props.spacing ?? 'balanced'));
const anchor = computed(() => (node.props.anchor ? String(node.props.anchor) : undefined));
</script>

<template>
    <section
        v-if="!node.hidden || ctx.editing"
        :id="anchor"
        :data-n="node.id"
        :data-type="node.type"
        :data-hidden="node.hidden || undefined"
        class="sn-section"
        :class="[
            `tone-${tone}`,
            `spacing-${spacing}`,
            { 'sn-divider': node.props.divider },
        ]"
    >
        <div v-reveal="node.motion" class="sn-motion">
            <component :is="component" v-if="component" :node="node" />
            <div v-else-if="ctx.editing" class="container-editorial">
                <BlockUnsupported :node="node" />
            </div>
        </div>
    </section>
</template>
