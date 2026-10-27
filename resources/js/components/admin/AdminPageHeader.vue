<script setup lang="ts">
/**
 * Encabezado estándar de pantalla admin: título, descripción breve y
 * acciones (la primaria a la derecha). Ver skill mrinsight-admin-ux.
 */
defineProps<{ title: string; description?: string; eyebrow?: string }>();
</script>

<template>
    <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-1 text-[11px] font-semibold tracking-[0.16em] text-brand uppercase">{{ eyebrow }}</p>
            <h1 class="font-display text-2xl leading-tight font-semibold tracking-tight md:text-[1.75rem]">{{ title }}</h1>
            <p v-if="description" class="mt-1 max-w-2xl text-sm text-muted-foreground">{{ description }}</p>
            <slot name="meta" />
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
