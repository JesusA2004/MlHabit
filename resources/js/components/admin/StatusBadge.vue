<script setup lang="ts">
import { computed } from 'vue';

/** Chip de estado de publicación (único en todo el admin). */
const props = defineProps<{ status: string }>();

const MAP: Record<string, { label: string; cls: string }> = {
    draft: { label: 'Borrador', cls: 'bg-muted text-muted-foreground' },
    scheduled: { label: 'Programada', cls: 'bg-warning/15 text-warning' },
    published: { label: 'Publicada', cls: 'bg-success/12 text-success' },
    archived: { label: 'Archivada', cls: 'bg-secondary text-secondary-foreground line-through decoration-1' },
    changes: { label: 'Cambios sin publicar', cls: 'bg-brand/15 text-brand' },
    unpublished: { label: 'Sin publicar', cls: 'bg-muted text-muted-foreground' },
};

const entry = computed(() => MAP[props.status] ?? { label: props.status, cls: 'bg-muted text-muted-foreground' });
</script>

<template>
    <span class="inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-[11px] font-semibold whitespace-nowrap" :class="entry.cls">
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ entry.label }}
    </span>
</template>
