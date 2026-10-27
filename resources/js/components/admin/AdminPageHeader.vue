<script setup lang="ts">
import PageHeader from '@/components/ds/PageHeader.vue';

/**
 * Compatibilidad: encabezado de pantalla del panel. Usa
 * `components/ds/PageHeader.vue` en pantallas nuevas.
 */
defineProps<{ title: string; description?: string; eyebrow?: string }>();
</script>

<template>
    <PageHeader :title="title" :description="description">
        <template v-if="$slots.breadcrumbs" #breadcrumbs
            ><slot name="breadcrumbs"
        /></template>
        <template v-if="$slots.meta || eyebrow" #meta>
            <span v-if="eyebrow">{{ eyebrow }}</span>
            <slot name="meta" />
        </template>
        <template v-if="$slots.actions" #actions
            ><slot name="actions"
        /></template>
    </PageHeader>
</template>
