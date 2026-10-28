<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { adminNavigation } from '@/lib/navigation';
import { filterNavigation, usePermissions } from '@/lib/permissions';

/**
 * Barra lateral del panel: marca, navegación filtrada por permisos y
 * cuenta. Colapsa a riel de iconos (Ctrl/Cmd + B) y en móvil es una hoja.
 */
const { user } = usePermissions();

const groups = computed(() => filterNavigation(adminNavigation, user.value));
const homeHref = computed(
    () => groups.value[0]?.items[0]?.href ?? '/settings/profile',
);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        aria-label="Navegación principal"
    >
        <SidebarHeader class="pb-1">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="group/logo hover:bg-transparent [&_svg]:transition-transform [&_svg]:duration-300 hover:[&_svg]:-rotate-6"
                    >
                        <Link
                            :href="homeHref"
                            aria-label="ML Habitat, ir al inicio del panel"
                        >
                            <AppLogo subtitle="Panel de operación" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <!-- Muestrario de acabados: la firma visual, en fino -->
            <div
                class="mx-2 mt-1 flex h-1 gap-0.5 overflow-hidden rounded-full group-data-[collapsible=icon]:hidden"
                aria-hidden="true"
            >
                <span class="admin-grow-x flex-[3] bg-primary" />
                <span class="admin-grow-x flex-[2] bg-oak" />
                <span class="admin-grow-x flex-[1.4] bg-input" />
                <span class="admin-grow-x flex-1 bg-espresso" />
            </div>
        </SidebarHeader>

        <SidebarContent class="gap-0 pb-2">
            <NavMain :groups="groups" />
        </SidebarContent>

        <SidebarFooter class="gap-1 border-t border-sidebar-border/70 pt-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        tooltip="Ver sitio web"
                        class="h-10 text-sidebar-foreground/75 md:h-8"
                    >
                        <a href="/" target="_blank" rel="noopener">
                            <ExternalLink />
                            <span>Ver sitio web</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
    <slot />
</template>
