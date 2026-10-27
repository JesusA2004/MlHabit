<script setup lang="ts">
import type { StudioNode } from '@/studio/types';

defineProps<{ node: StudioNode }>();
</script>

<template>
    <div class="sn-divider-el" :class="`sn-divider-el--${node.props.variant ?? 'line'}`" role="separator">
        <span v-if="node.props.variant === 'ornament'" aria-hidden="true">✦</span>
    </div>
</template>
