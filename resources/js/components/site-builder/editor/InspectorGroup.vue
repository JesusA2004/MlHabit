<script setup lang="ts">
/** Grupo del panel de propiedades. */
const props = defineProps<{ title: string; description?: string }>();
</script>

<template>
    <section class="border-b border-border px-4 py-4 last:border-b-0">
        <header class="mb-3 flex items-center justify-between gap-2">
            <h3 class="text-xs font-semibold tracking-wide text-foreground">
                {{ props.title }}
            </h3>
            <slot name="action" />
        </header>
        <p
            v-if="props.description"
            class="-mt-2 mb-3 text-xs text-muted-foreground"
        >
            {{ props.description }}
        </p>
        <div class="grid gap-3.5">
            <slot />
        </div>
    </section>
</template>
