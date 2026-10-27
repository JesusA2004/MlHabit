<script setup lang="ts">
import { computed } from 'vue';
import { activeSlots } from '@/studio/schema';
import type { StudioNode } from '@/studio/types';
import SlotView from '../SlotView.vue';

const { node } = defineProps<{ node: StudioNode }>();

const slots = computed(() => activeSlots(node));
const layout = computed(() => String(node.props.layout ?? 'two'));
</script>

<template>
    <div class="sn-container" :class="`w-${node.props.width ?? 'normal'}`">
        <div
            class="sn-grid sn-cols"
            :class="[`sn-cols--${layout}`, { 'sn-cols--cards': node.props.cards }]"
        >
            <SlotView
                v-for="slot in slots"
                :key="slot"
                :section="node"
                :name="slot"
                class="sn-col"
            />
        </div>
    </div>
</template>
