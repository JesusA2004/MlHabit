<script setup lang="ts">
import type { Component } from 'vue';

/** Estado vacío con una acción directa (nunca un "No data" seco). */
defineProps<{ icon?: Component; title: string; description?: string }>();
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed bg-card/50 px-6 py-14 text-center">
        <span v-if="icon" class="grid size-12 place-items-center rounded-full bg-brand/12 text-brand">
            <component :is="icon" class="size-5" />
        </span>
        <div>
            <p class="font-medium">{{ title }}</p>
            <p v-if="description" class="mt-1 max-w-md text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="mt-1 flex flex-wrap justify-center gap-2"><slot /></div>
    </div>
</template>
