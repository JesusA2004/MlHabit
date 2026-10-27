<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PenTool, Plus } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import publicaciones from '@/routes/admin/publicaciones';
import studio from '@/routes/admin/studio';
import type { BreadcrumbItem } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/85 px-4 backdrop-blur-md md:rounded-t-xl md:px-5"
    >
        <SidebarTrigger class="-ml-1" />
        <div class="min-w-0 flex-1">
            <Breadcrumbs v-if="breadcrumbs.length" :breadcrumbs="breadcrumbs" />
        </div>
        <Button as-child size="sm" variant="ghost" class="hidden sm:inline-flex">
            <Link :href="studio.index()"><PenTool class="size-4" /> Studio</Link>
        </Button>
        <Button as-child size="sm">
            <Link :href="publicaciones.create()"><Plus class="size-4" /> <span class="hidden sm:inline">Nueva publicación</span></Link>
        </Button>
    </header>
</template>
