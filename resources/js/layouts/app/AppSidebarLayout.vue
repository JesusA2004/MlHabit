<script setup lang="ts">
import { provide } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AppToaster from '@/components/ds/AppToaster.vue';
import { pageContainerContextKey } from '@/components/ds/pageContext';
import type { BreadcrumbItem } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

// Las pantallas del panel usan todo el ancho disponible y entran con una
// animación breve al navegar (ver `admin-enter` en app.css).
provide(pageContainerContextKey, {
    defaultWidth: 'fluid',
    animate: true,
    sectionIcons: true,
});
</script>

<template>
    <a
        href="#contenido"
        class="sr-only z-50 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
    >
        Saltar al contenido
    </a>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="admin-canvas min-w-0 overflow-x-clip"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs">
                <slot name="header-actions" />
            </AppSidebarHeader>
            <slot />
        </AppContent>
        <AppToaster />
    </AppShell>
</template>
