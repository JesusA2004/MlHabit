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
import type { SiteCta, SiteNavItem, SiteNavLink } from '@/types/public';

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

/**
 * Navegación pública por defecto si el backend no envía `site.navigation`.
 * Es el mismo menú que `App\Services\Web\Navigation::defaults()`.
 */
export const defaultSiteNavigation: SiteNavItem[] = [
    { id: 'inicio', label: 'Inicio', href: '/' },
    { id: 'catalogo', label: 'Catálogo', href: '/catalogo' },
    { id: 'inspiracion', label: 'Inspiración', href: '/inspiracion' },
    { id: 'como-funciona', label: 'Cómo funciona', href: '/#como-funciona' },
    {
        id: 'servicios',
        label: 'Servicios',
        href: null,
        children: [
            {
                label: 'Asesoría por videollamada',
                href: '/solicitar?servicio=asesoria',
            },
            { label: 'Visita a domicilio', href: '/agendar-visita' },
            { label: 'Cotiza tu proyecto', href: '/solicitar' },
        ],
    },
    {
        id: 'preguntas',
        label: 'Preguntas frecuentes',
        href: '/preguntas-frecuentes',
    },
];

/** Botón destacado por defecto (igual que en el backend). */
export const defaultSiteCta: SiteCta = {
    label: 'Agenda tu asesoría',
    href: '/solicitar?servicio=asesoria',
};

/**
 * Aplana el menú (para el pie): cada enlace con destino y luego los de su
 * submenú, sin repetir destinos.
 */
export function flattenNavigation(items: SiteNavItem[]): SiteNavLink[] {
    const seen = new Set<string>();
    const out: SiteNavLink[] = [];

    for (const item of items) {
        const own: SiteNavLink[] = item.href
            ? [
                  {
                      label: item.label,
                      href: item.href,
                      external: item.external,
                      newTab: item.newTab,
                  },
              ]
            : [];

        for (const link of [...own, ...(item.children ?? [])]) {
            if (seen.has(link.href)) continue;
            seen.add(link.href);
            out.push(link);
        }
    }

    return out;
}

/* ── Enlaces a secciones (`/#como-funciona`) ───────────────────────── */

/** Id de la sección de un enlace con `#`, o null. */
export function hashOf(href: string | null | undefined): string | null {
    if (!href) return null;
    const i = href.indexOf('#');
    if (i < 0) return null;

    const id = href.slice(i + 1);

    try {
        return id ? decodeURIComponent(id) : null;
    } catch {
        return id || null;
    }
}

/**
 * Si `href` apunta a una sección de la página actual (`#id`, `/#id` estando
 * en `/`, `/ruta#id` estando en `/ruta`), devuelve el id; si no, null.
 */
export function samePageHash(href: string, currentUrl: string): string | null {
    const id = hashOf(href);
    if (!id) return null;

    const path = href.slice(0, href.indexOf('#'));
    if (path === '') return id;
    if (!path.startsWith('/')) return null;

    return normalizePath(path) === normalizePath(currentUrl) ? id : null;
}

/** Evento que avisa a la cabecera de un salto a sección (no esconderse). */
export const ANCHOR_SCROLL_EVENT = 'site:anchor-scroll';

/** El salto a sección terminó (la cabecera vuelve a su comportamiento). */
export const ANCHOR_SCROLL_END_EVENT = 'site:anchor-scroll-end';

/** Espacio mínimo sobre la sección: cabecera compacta (64 px) + aire. */
const ANCHOR_OFFSET = 88;

/**
 * Ejecuta `run` cuando la página puede desplazarse: si un menú o diálogo
 * (menú móvil) la tiene bloqueada, espera a que se cierre (máx. ~0.7 s).
 */
export function whenScrollable(run: () => void, tries = 40): void {
    const locked =
        document.documentElement.hasAttribute('data-menu-open') ||
        getComputedStyle(document.body).overflow === 'hidden';

    if (!locked || tries <= 0) {
        run();

        return;
    }

    requestAnimationFrame(() => whenScrollable(run, tries - 1));
}

/**
 * Desplaza hasta `el` dejando libre la cabecera fija (compacta al bajar):
 * respeta su `scroll-margin-top` si es mayor que el mínimo. Suave salvo con
 * `prefers-reduced-motion`. Avisa a la cabecera para que no se esconda
 * durante el recorrido y corrige al final si el diseño cambió de alto.
 */
export function scrollBelowHeader(
    el: HTMLElement,
    options: { smooth?: boolean } = {},
): void {
    const target = () => {
        const margin = parseFloat(getComputedStyle(el).scrollMarginTop) || 0;

        return Math.max(
            0,
            Math.round(
                el.getBoundingClientRect().top +
                    window.scrollY -
                    Math.max(margin, ANCHOR_OFFSET),
            ),
        );
    };
    const reduce = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    const smooth = options.smooth !== false && !reduce;

    window.dispatchEvent(new CustomEvent(ANCHOR_SCROLL_EVENT));
    window.scrollTo({ top: target(), behavior: smooth ? 'smooth' : 'auto' });

    // Si algo arriba cambió de alto durante el recorrido (imágenes,
    // revelados), corrige al terminar para no quedar desfasado.
    const settle = () => {
        window.removeEventListener('scrollend', settle);
        window.clearTimeout(fallback);
        const top = target();
        if (Math.abs(window.scrollY - top) > 3) {
            window.dispatchEvent(new CustomEvent(ANCHOR_SCROLL_EVENT));
            window.scrollTo({ top, behavior: 'auto' });
        }
        window.dispatchEvent(new CustomEvent(ANCHOR_SCROLL_END_EVENT));
    };
    const fallback = window.setTimeout(settle, smooth ? 1600 : 120);
    if ('onscrollend' in window) {
        window.addEventListener('scrollend', settle, { once: true });
    }
}

/** `scrollBelowHeader` por id. Devuelve false si la sección no existe. */
export function scrollToSection(
    id: string,
    options: { smooth?: boolean } = {},
): boolean {
    const el = document.getElementById(id);
    if (!el) return false;

    scrollBelowHeader(el, options);

    return true;
}

/** Ruta de inicio de sesión (Fortify). */
export const loginPath = '/login';

export type AccountArea = { href: string; label: string; short: string };

/**
 * Área propia de la persona autenticada. El backend comparte
 * `auth.user.homePath` (UserRole::homePath); si no viniera, se deduce del rol.
 */
export function accountArea(user: {
    role?: string;
    homePath?: unknown;
}): AccountArea {
    const href =
        typeof user.homePath === 'string' && user.homePath.startsWith('/')
            ? user.homePath
            : user.role === 'cliente'
              ? '/portal'
              : user.role === 'tecnico'
                ? '/tecnico'
                : '/admin';

    if (href.startsWith('/portal')) {
        return { href, label: 'Ir a mi proyecto', short: 'Mi proyecto' };
    }
    if (href.startsWith('/tecnico')) {
        return { href, label: 'Ir a mi agenda', short: 'Mi agenda' };
    }

    return { href, label: 'Ir a mi panel', short: 'Mi panel' };
}
