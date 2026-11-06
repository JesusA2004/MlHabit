/** Datos del sitio que comparte el backend como `site` en páginas públicas. */
export type SiteNavLink = {
    label: string;
    href: string;
    /** Enlace fuera del sitio (https://, mailto:, tel:). */
    external?: boolean;
    newTab?: boolean;
};

/**
 * Elemento del menú del sitio (App\Services\Web\Navigation::resolve()).
 * Un grupo con submenú no tiene `href`.
 */
export type SiteNavItem = Omit<SiteNavLink, 'href'> & {
    id?: string;
    href: string | null;
    children?: SiteNavLink[];
};

export type SiteCta = {
    label: string;
    href: string;
    external?: boolean;
    newTab?: boolean;
};

export type SiteSocialLink = {
    platform: string;
    url: string;
    label?: string | null;
};

export type SiteContact = {
    phone?: string | null;
    whatsapp?: string | null;
    email?: string | null;
    address?: string | null;
    hours?: string | null;
};

export type SiteData = {
    name: string;
    tagline?: string | null;
    logoUrl?: string | null;
    contact?: SiteContact;
    social?: SiteSocialLink[];
    footerPages?: { title: string; href: string }[];
    navigation?: SiteNavItem[];
    /** Botón destacado de la cabecera; null si el equipo lo ocultó. */
    cta?: SiteCta | null;
};
