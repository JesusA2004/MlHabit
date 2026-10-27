<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';
import DsStatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/types/ui';

/**
 * Chip de estado del panel. Preferido: `tone` + `label` (genérico). Con
 * `status` se traducen estados de contenido comunes; los módulos de negocio
 * deben mapear su estado a tono/etiqueta en su propio código.
 */
const props = defineProps<{
    tone?: Tone;
    label?: string;
    status?: string;
    icon?: Component;
}>();

const CONTENT_STATUS: Record<string, { label: string; tone: Tone }> = {
    draft: { label: 'Borrador', tone: 'neutral' },
    scheduled: { label: 'Programado', tone: 'info' },
    published: { label: 'Publicado', tone: 'success' },
    archived: { label: 'Archivado', tone: 'neutral' },
    active: { label: 'Activo', tone: 'success' },
    inactive: { label: 'Inactivo', tone: 'neutral' },
};

const resolved = computed(() => {
    if (props.label) {
        return { label: props.label, tone: props.tone ?? 'neutral' };
    }

    const entry = props.status ? CONTENT_STATUS[props.status] : undefined;

    return (
        entry ?? { label: props.status ?? '', tone: props.tone ?? 'neutral' }
    );
});
</script>

<template>
    <DsStatusBadge :tone="resolved.tone" :label="resolved.label" :icon="icon" />
</template>
