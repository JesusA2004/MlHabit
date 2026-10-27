import {
    Activity,
    BadgeCheck,
    ChartColumn,
    BookOpenText,
    CalendarDays,
    CalendarRange,
    ClipboardCheck,
    CreditCard,
    Factory,
    FileText,
    FolderKanban,
    Globe,
    HandCoins,
    HardDrive,
    HardHat,
    House,
    Images,
    KeyRound,
    LayoutGrid,
    LifeBuoy,
    ListChecks,
    MapPinned,
    MessagesSquare,
    PencilRuler,
    ReceiptText,
    Settings,
    ShieldAlert,
    Sofa,
    Sparkles,
    Truck,
    UserRound,
    UserRoundPlus,
    Users,
    UsersRound,
    Wallet,
    Wrench,
} from '@lucide/vue';
import type { AdminNavGroup, TabNavItem } from '@/types/navigation';
import type { SiteNavLink } from '@/types/public';

/**
 * Navegación del panel como datos. Cada elemento declara el permiso que
 * lo hace visible; `filterNavigation()` oculta elementos y grupos vacíos.
 */
export const adminNavigation: AdminNavGroup[] = [
    {
        items: [
            {
                title: 'Inicio',
                href: '/admin',
                icon: House,
                permission: 'dashboard.view',
                exact: true,
            },
        ],
    },
    {
        label: 'Comercial',
        items: [
            {
                title: 'Prospectos',
                href: '/admin/prospectos',
                icon: UserRoundPlus,
                permission: 'leads.view',
            },
            {
                title: 'Clientes',
                href: '/admin/clientes',
                icon: UsersRound,
                permission: 'customers.view',
            },
            {
                title: 'Asesorías',
                href: '/admin/asesorias',
                icon: MessagesSquare,
                permission: 'consultations.view',
            },
            {
                title: 'Visitas',
                href: '/admin/visitas',
                icon: MapPinned,
                permission: 'visits.view',
            },
            {
                title: 'Precalificación',
                href: '/admin/precalificaciones',
                icon: ListChecks,
                permission: 'prequalification.view',
            },
            {
                title: 'Cotizaciones',
                href: '/admin/cotizaciones',
                icon: ReceiptText,
                permission: 'quotes.view',
            },
        ],
    },
    {
        label: 'Proyectos',
        items: [
            {
                title: 'Proyectos',
                href: '/admin/proyectos',
                icon: FolderKanban,
                permission: 'projects.view',
            },
            {
                title: 'Diseño',
                href: '/admin/disenos',
                icon: PencilRuler,
                permission: 'designs.view',
            },
            {
                title: 'Fabricación',
                href: '/admin/fabricacion',
                icon: Factory,
                permission: 'fabrication.view',
            },
            {
                title: 'Instalaciones',
                href: '/admin/instalaciones',
                icon: Wrench,
                permission: 'installations.view',
            },
            {
                title: 'Calidad',
                href: '/admin/calidad',
                icon: ClipboardCheck,
                permission: 'quality.view',
            },
            {
                title: 'Incidencias',
                href: '/admin/incidencias',
                icon: ShieldAlert,
                permission: 'incidents.view',
            },
            {
                title: 'Garantías',
                href: '/admin/garantias',
                icon: BadgeCheck,
                permission: 'warranties.view',
            },
        ],
    },
    {
        label: 'Operación',
        items: [
            {
                title: 'Agenda',
                href: '/admin/agenda',
                icon: CalendarDays,
                permission: 'schedule.view',
            },
            {
                title: 'Cobertura',
                href: '/admin/cobertura',
                icon: Globe,
                permission: 'coverage.view',
            },
            {
                title: 'Técnicos',
                href: '/admin/tecnicos',
                icon: HardHat,
                permission: 'suppliers.view',
            },
            {
                title: 'Proveedores',
                href: '/admin/proveedores',
                icon: Truck,
                permission: 'suppliers.view',
            },
            {
                title: 'Tarifarios',
                href: '/admin/tarifarios',
                icon: FileText,
                permission: 'rate_cards.view',
            },
        ],
    },
    {
        label: 'Finanzas',
        items: [
            {
                title: 'Pagos',
                href: '/admin/pagos',
                icon: Wallet,
                permission: 'payments.view',
            },
            {
                title: 'Pagos a proveedores',
                href: '/admin/pagos-proveedores',
                icon: HandCoins,
                permission: 'payouts.view',
            },
            {
                title: 'Openpay',
                href: '/admin/openpay',
                icon: CreditCard,
                permission: 'openpay.diagnostics',
            },
        ],
    },
    {
        label: 'Contenido',
        items: [
            {
                title: 'Sitio web',
                href: '/admin/sitio',
                icon: LayoutGrid,
                permission: 'content.manage',
            },
            {
                title: 'Catálogo',
                href: '/admin/catalogo',
                icon: Sofa,
                permission: 'catalog.manage',
            },
            {
                title: 'Inspiración',
                href: '/admin/inspiracion',
                icon: Sparkles,
                permission: 'catalog.manage',
            },
            {
                title: 'Multimedia',
                href: '/admin/multimedia',
                icon: Images,
                permission: 'media.manage',
            },
            {
                title: 'Preguntas frecuentes',
                href: '/admin/preguntas',
                icon: BookOpenText,
                permission: 'content.manage',
            },
        ],
    },
    {
        label: 'Administración',
        items: [
            {
                title: 'Usuarios',
                href: '/admin/usuarios',
                icon: Users,
                permission: 'users.manage',
            },
            {
                title: 'Roles y permisos',
                href: '/admin/roles',
                icon: KeyRound,
                permission: 'roles.manage',
            },
            {
                title: 'Actividad',
                href: '/admin/actividad',
                icon: Activity,
                permission: 'activity.view',
            },
            {
                title: 'Estadísticas',
                href: '/admin/estadisticas',
                icon: ChartColumn,
                permission: 'dashboard.view',
            },
            {
                title: 'Configuración',
                href: '/admin/configuracion',
                icon: Settings,
                permission: 'settings.manage',
            },
            {
                title: 'Almacenamiento',
                href: '/admin/almacenamiento',
                icon: HardDrive,
                permission: 'settings.manage',
            },
        ],
    },
];

/** Barra inferior de la app de técnicos. */
export const technicianTabs: TabNavItem[] = [
    { title: 'Hoy', href: '/tecnico', icon: CalendarRange, exact: true },
    { title: 'Visitas', href: '/tecnico/visitas', icon: MapPinned },
    { title: 'Instalaciones', href: '/tecnico/instalaciones', icon: Wrench },
    {
        title: 'Perfil',
        href: '/settings/profile',
        icon: UserRound,
        match: '/settings',
    },
];

/** Navegación del portal de clientes. */
export const portalNavigation: TabNavItem[] = [
    { title: 'Mi proyecto', href: '/portal', icon: House, exact: true },
    { title: 'Citas', href: '/portal/citas', icon: CalendarDays },
    { title: 'Pagos', href: '/portal/pagos', icon: Wallet },
    { title: 'Documentos', href: '/portal/documentos', icon: FileText },
    { title: 'Ayuda', href: '/portal/ayuda', icon: LifeBuoy },
];

/** Enlaces de la cuenta (menú de usuario). */
export const accountLinks = {
    profile: '/settings/profile',
    security: '/settings/security',
    logout: '/logout',
} as const;

/** Normaliza una ruta: sin query/hash y sin barra final. */
export function normalizePath(url: string): string {
    const path = url.split(/[?#]/)[0] || '/';

    return path.length > 1 ? path.replace(/\/+$/, '') : path;
}

/** ¿La URL actual corresponde a este destino de navegación? */
export function isActivePath(
    currentUrl: string,
    item: { href: string; match?: string; exact?: boolean },
): boolean {
    const current = normalizePath(currentUrl);
    const target = normalizePath(item.match ?? item.href);

    if (item.exact) {
        return current === target;
    }

    return current === target || current.startsWith(`${target}/`);
}

/** Navegación pública por defecto si el backend no envía `site.navigation`. */
export const defaultSiteNavigation: SiteNavLink[] = [
    { label: 'Catálogo', href: '/catalogo' },
    { label: 'Inspiración', href: '/inspiracion' },
    { label: 'Cómo funciona', href: '/#como-funciona' },
    { label: 'Preguntas frecuentes', href: '/preguntas-frecuentes' },
];
