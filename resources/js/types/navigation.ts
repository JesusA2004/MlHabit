import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

/** Enlace genérico (ajustes, menús). */
export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

/** Entrada de la navegación del panel (datos en lib/navigation.ts). */
export type AdminNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    /** Permiso requerido, p. ej. `leads.view`. */
    permission: string;
    /** Prefijo de URL que marca el elemento como activo (por defecto `href`). */
    match?: string;
    /** Si es true, solo está activo en la URL exacta. */
    exact?: boolean;
};

export type AdminNavGroup = {
    /** Sin etiqueta = grupo raíz (p. ej. Inicio). */
    label?: string;
    items: AdminNavItem[];
};

/** Pestaña de las barras inferiores (técnicos, portal). */
export type TabNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    match?: string;
    exact?: boolean;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
