<script setup lang="ts">
import type { StudioNode } from '@/studio/types';

defineProps<{ node: StudioNode }>();
</script>

<template>
    <aside class="sn-question" aria-label="Pregunta al lector">
        <p class="type-eyebrow sn-question-label">{{ node.props.label }}</p>
        <p class="sn-t type-title" data-inline="text" data-inline-multiline>
            {{ node.props.text }}
        </p>
    </aside>
</template>
