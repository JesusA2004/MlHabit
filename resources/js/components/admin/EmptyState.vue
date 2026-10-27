<script setup lang="ts">
import type { Component } from 'vue';
import DsEmptyState from '@/components/ds/EmptyState.vue';

/** Compatibilidad: usa `components/ds/EmptyState.vue` en pantallas nuevas. */
defineProps<{ icon?: Component; title: string; description?: string }>();
</script>

<template>
    <DsEmptyState :icon="icon" :title="title" :description="description">
        <slot />
    </DsEmptyState>
</template>
