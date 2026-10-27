<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationsMenu from '@/components/platform/NotificationsMenu.vue';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

/**
 * Barra superior del panel. En móvil muestra el monograma y el botón de
 * menú (abre la hoja de navegación); en escritorio, migas de pan.
 */
withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-border/70 bg-background/85 px-3 backdrop-blur-md supports-[backdrop-filter]:bg-background/70 md:rounded-t-xl md:px-5"
    >
        <SidebarTrigger class="size-11 md:-ml-1.5 md:size-8" />
        <Link
            href="/admin"
            class="flex items-center md:hidden"
            aria-label="ML Habitat, inicio"
        >
            <AppLogoIcon class-name="size-7" />
        </Link>
        <Separator orientation="vertical" class="mx-1 hidden h-4 md:block" />
        <div class="min-w-0 flex-1">
            <Breadcrumbs v-if="breadcrumbs.length" :breadcrumbs="breadcrumbs" />
        </div>
        <div class="flex items-center gap-2">
            <slot />
            <NotificationsMenu />
        </div>
    </header>
</template>
