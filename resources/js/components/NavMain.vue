<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { useStorage } from '@vueuse/core';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { isActivePath } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { AdminNavGroup, AdminNavItem } from '@/types/navigation';

/**
 * Navegación agrupada del panel. Cada grupo con etiqueta se puede plegar
 * (se recuerda por dispositivo); en modo riel de iconos todos se muestran.
 */
const props = defineProps<{ groups: AdminNavGroup[] }>();

const page = usePage();
const { state, isMobile, setOpenMobile } = useSidebar();

const collapsed = useStorage<Record<string, boolean>>(
    'mlhabitat.sidebar.groups',
    {},
);

function isActive(item: AdminNavItem): boolean {
    return isActivePath(page.url, item);
}

const activeGroups = computed(
    () =>
        new Set(
            props.groups
                .filter((g) => g.items.some(isActive))
                .map((g) => g.label ?? ''),
        ),
);

function isOpen(label: string): boolean {
    if (state.value === 'collapsed' && !isMobile.value) {
        return true;
    }

    return activeGroups.value.has(label) || !collapsed.value[label];
}

function setOpen(label: string, open: boolean) {
    collapsed.value = { ...collapsed.value, [label]: !open };
}

function onNavigate() {
    if (isMobile.value) {
        setOpenMobile(false);
    }
}

const buttonClass =
    'h-10 gap-2.5 text-sidebar-foreground/85 md:h-8 [&>svg]:text-sidebar-foreground/60 data-[active=true]:bg-primary-soft data-[active=true]:font-medium data-[active=true]:text-primary data-[active=true]:[&>svg]:text-primary';
</script>

<template>
    <template v-for="group in props.groups" :key="group.label ?? 'root'">
        <SidebarGroup v-if="!group.label" class="py-1">
            <SidebarMenu>
                <SidebarMenuItem v-for="item in group.items" :key="item.href">
                    <SidebarMenuButton
                        as-child
                        :is-active="isActive(item)"
                        :tooltip="item.title"
                        :class="buttonClass"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="isActive(item) ? 'page' : undefined"
                            @click="onNavigate"
                        >
                            <component :is="item.icon" stroke-width="1.85" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>

        <Collapsible
            v-else
            :open="isOpen(group.label)"
            class="group/collapsible"
            @update:open="(open: boolean) => setOpen(group.label!, open)"
        >
            <SidebarGroup class="py-1">
                <SidebarGroupLabel
                    as-child
                    class="text-xs font-medium text-sidebar-foreground/60"
                >
                    <CollapsibleTrigger
                        class="flex w-full items-center rounded-md hover:bg-sidebar-accent/70 hover:text-sidebar-foreground focus-visible:ring-2"
                    >
                        {{ group.label }}
                        <ChevronRight
                            class="ml-auto size-3.5 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                            aria-hidden="true"
                        />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent
                    class="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down"
                >
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem
                                v-for="item in group.items"
                                :key="item.href"
                            >
                                <SidebarMenuButton
                                    as-child
                                    :is-active="isActive(item)"
                                    :tooltip="item.title"
                                    :class="cn(buttonClass)"
                                >
                                    <Link
                                        :href="item.href"
                                        :aria-current="
                                            isActive(item) ? 'page' : undefined
                                        "
                                        @click="onNavigate"
                                    >
                                        <component
                                            :is="item.icon"
                                            stroke-width="1.85"
                                        />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    </template>
</template>
