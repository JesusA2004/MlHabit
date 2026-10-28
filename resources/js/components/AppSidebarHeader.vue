<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useWindowScroll } from '@vueuse/core';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationsMenu from '@/components/platform/NotificationsMenu.vue';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

/**
 * Barra superior del panel. En móvil muestra el monograma y el botón de
 * menú (abre la hoja de navegación); en escritorio, migas de pan. Al hacer
 * scroll se vuelve más opaca y gana una sombra fina para separarse del
 * contenido.
 */
withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const { y } = useWindowScroll();
const scrolled = computed(() => y.value > 4);
</script>

<template>
    <header
        :data-scrolled="scrolled || undefined"
        :class="
            cn(
                'sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b px-3 backdrop-blur-md transition-[background-color,box-shadow,border-color] duration-300 md:rounded-t-xl md:px-5',
                scrolled
                    ? 'border-border bg-background/90 shadow-[0_6px_16px_-12px_rgb(35_30_26/0.25)]'
                    : 'border-border/60 bg-background/70',
            )
        "
    >
        <SidebarTrigger
            class="size-11 text-muted-foreground hover:text-foreground md:-ml-1.5 md:size-8"
        />
        <Link
            href="/admin"
            class="flex items-center transition-transform duration-200 hover:scale-105 md:hidden"
            aria-label="ML Habitat, inicio"
        >
            <AppLogoIcon class-name="size-7" />
        </Link>
        <Separator orientation="vertical" class="mx-1 hidden h-4 md:block" />
        <div class="min-w-0 flex-1">
            <Breadcrumbs v-if="breadcrumbs.length" :breadcrumbs="breadcrumbs" />
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <slot />
            <NotificationsMenu />
        </div>
    </header>
</template>
