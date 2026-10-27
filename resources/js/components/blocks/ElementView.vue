<script setup lang="ts">
import { computed } from 'vue';
import { vReveal } from '@/lib/reveal';
import type { StudioNode } from '@/studio/types';
import { useRenderCtx } from './context';
import { elementComponents } from './elementRegistry';
import BlockUnsupported from './elements/BlockUnsupported.vue';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const component = computed(() => elementComponents[node.type] ?? null);
</script>

<template>
    <div
        v-if="(!node.hidden || ctx.editing) && (component || ctx.editing)"
        v-reveal="node.motion"
        :data-n="node.id"
        :data-type="node.type"
        :data-hidden="node.hidden || undefined"
        class="sn-el"
        data-stagger-item
    >
        <component :is="component ?? BlockUnsupported" :node="node" />
    </div>
</template>
