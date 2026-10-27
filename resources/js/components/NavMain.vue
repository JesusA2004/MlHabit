<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavGroup } from '@/types';

defineProps<{ groups: NavGroup[] }>();

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

function active(href: NavGroup['items'][number]['href']): boolean {
    const url = toUrl(href);

    // El panel solo está activo en /admin exacto; el resto, también en subrutas.
    return url.replace(/\/$/, '').endsWith('/admin') ? isCurrentUrl(href) : isCurrentOrParentUrl(href);
}
</script>

<template>
    <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-1">
        <SidebarGroupLabel class="text-[10px] font-semibold tracking-[0.16em] uppercase">{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="active(item.href)"
                    :tooltip="item.title"
                    class="data-[active=true]:bg-sidebar-accent data-[active=true]:font-semibold data-[active=true]:shadow-[inset_2px_0_0_var(--color-brand)]"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
