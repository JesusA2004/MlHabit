<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChartColumn,
    ClipboardList,
    Database,
    ExternalLink,
    FileText,
    FolderOpen,
    Home,
    Image,
    LayoutDashboard,
    Palette,
    PenTool,
    Settings,
    Share2,
    Tags,
} from '@lucide/vue';
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
import { calendario, dashboard, estadisticas } from '@/routes/admin';
import actividad from '@/routes/admin/actividad';
import almacenamiento from '@/routes/admin/almacenamiento';
import apariencia from '@/routes/admin/apariencia';
import categorias from '@/routes/admin/categorias';
import inicio from '@/routes/admin/inicio';
import multimedia from '@/routes/admin/multimedia';
import paginas from '@/routes/admin/paginas';
import publicaciones from '@/routes/admin/publicaciones';
import redesSociales from '@/routes/admin/redes-sociales';
import studio from '@/routes/admin/studio';
import { edit as profileEdit } from '@/routes/profile';
import type { NavGroup } from '@/types';

/** Navegación agrupada (solo módulos existentes). Ver skill mrinsight-admin-ux. */
const groups: NavGroup[] = [
    {
        label: 'General',
        items: [
            { title: 'Panel', href: dashboard(), icon: LayoutDashboard },
            { title: 'Estadísticas', href: estadisticas(), icon: ChartColumn },
            { title: 'Studio', href: studio.index(), icon: PenTool },
            { title: 'Calendario editorial', href: calendario(), icon: CalendarDays },
        ],
    },
    {
        label: 'Contenido',
        items: [
            { title: 'Publicaciones', href: publicaciones.index(), icon: FolderOpen },
            { title: 'Categorías', href: categorias.index(), icon: Tags },
            { title: 'Multimedia', href: multimedia.index(), icon: Image },
            { title: 'Páginas', href: paginas.index(), icon: FileText },
        ],
    },
    {
        label: 'Sitio web',
        items: [
            { title: 'Apariencia', href: apariencia.edit(), icon: Palette },
            { title: 'Inicio', href: inicio.edit(), icon: Home },
            { title: 'Redes sociales', href: redesSociales.index(), icon: Share2 },
        ],
    },
    {
        label: 'Sistema',
        items: [
            { title: 'Almacenamiento', href: almacenamiento.index(), icon: Database },
            { title: 'Actividad', href: actividad.index(), icon: ClipboardList },
            { title: 'Configuración', href: profileEdit(), icon: Settings },
        ],
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="groups" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child tooltip="Ver sitio público">
                        <a href="/" target="_blank" rel="noopener">
                            <ExternalLink />
                            <span>Ver sitio público</span>
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
