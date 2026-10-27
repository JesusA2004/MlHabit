<script setup lang="ts">
import PublicBreadcrumbs from './PublicBreadcrumbs.vue';

/** Encabezado de páginas interiores del sitio: migas, título y entrada. */
const props = defineProps<{
    title: string;
    eyebrow?: string | null;
    lead?: string | null;
    breadcrumbs?: { label: string; href?: string }[];
}>();
</script>

<template>
    <header class="border-b border-border/70 bg-linen">
        <div class="container-site pt-10 pb-12 sm:pt-14 sm:pb-16">
            <PublicBreadcrumbs
                v-if="props.breadcrumbs?.length"
                :items="props.breadcrumbs"
                class="mb-8"
            />
            <div class="grid gap-6 lg:grid-cols-12 lg:items-end">
                <div class="lg:col-span-8">
                    <p
                        v-if="props.eyebrow"
                        class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-oak-ink"
                    >
                        <span
                            aria-hidden="true"
                            class="h-px w-6 bg-current opacity-70"
                        />
                        {{ props.eyebrow }}
                    </p>
                    <h1 class="text-display-xl">{{ props.title }}</h1>
                </div>
                <p
                    v-if="props.lead"
                    class="text-lead text-muted-foreground lg:col-span-4"
                >
                    {{ props.lead }}
                </p>
            </div>
            <slot />
        </div>
    </header>
</template>
