<script setup lang="ts">
import { computed } from 'vue';
import type { StudioNode } from '@/studio/types';
import SlotView from '../SlotView.vue';

const { node } = defineProps<{ node: StudioNode }>();
const layout = computed(() => String(node.props.layout ?? 'card'));
</script>

<template>
    <div class="sn-container" :class="`w-${node.props.width ?? 'normal'}`">
        <div class="sn-cta" :class="`sn-cta--${layout}`">
            <div class="sn-grid sn-cta-grid">
                <SlotView :section="node" name="content" class="sn-cta-content" />
                <SlotView v-if="layout === 'split'" :section="node" name="media" class="sn-cta-media" />
            </div>
        </div>
    </div>
</template>
