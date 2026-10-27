<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/** Migas de pan; en móvil solo se ve la página actual. */
defineProps<{ breadcrumbs: BreadcrumbItemType[] }>();
</script>

<template>
    <Breadcrumb aria-label="Ruta de navegación">
        <BreadcrumbList class="flex-nowrap">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem
                    :class="
                        index < breadcrumbs.length - 1
                            ? 'hidden md:inline-flex'
                            : 'min-w-0'
                    "
                >
                    <BreadcrumbPage
                        v-if="index === breadcrumbs.length - 1"
                        class="truncate font-medium"
                    >
                        {{ item.title }}
                    </BreadcrumbPage>
                    <BreadcrumbLink v-else as-child>
                        <Link :href="item.href">{{ item.title }}</Link>
                    </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator
                    v-if="index !== breadcrumbs.length - 1"
                    class="hidden md:inline-flex"
                />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
