<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

withDefaults(defineProps<{ variant?: AppVariant }>(), {
    variant: 'sidebar',
});

const isOpen = usePage().props.sidebarOpen ?? true;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-svh w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <slot />
    </SidebarProvider>
</template>
