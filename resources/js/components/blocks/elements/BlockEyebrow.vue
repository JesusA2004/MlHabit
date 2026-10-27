<script setup lang="ts">
import type { StudioNode } from '@/studio/types';

defineProps<{ node: StudioNode }>();
</script>

<template>
    <p class="sn-eyebrow sn-t type-eyebrow" data-inline="text">
        {{ node.props.text }}
    </p>
</template>
