<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

/**
 * Layout del panel. Las páginas pasan migas con
 * `defineOptions({ layout: { breadcrumbs: [...] } })` y envuelven su
 * contenido en `<PageContainer>` (components/ds) para el padding estándar.
 */
const { breadcrumbs = [] } = defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();
</script>

<template>
    <AppSidebarLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppSidebarLayout>
</template>
