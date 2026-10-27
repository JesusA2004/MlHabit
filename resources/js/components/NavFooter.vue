<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

/** Enlaces externos al pie de la barra lateral. */
const props = defineProps<{ items: NavItem[]; class?: string }>();
</script>

<template>
    <SidebarGroup :class="cn('group-data-[collapsible=icon]:p-0', props.class)">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in props.items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :tooltip="item.title"
                        class="text-sidebar-foreground/75 hover:text-sidebar-foreground"
                    >
                        <a
                            :href="toUrl(item.href)"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
