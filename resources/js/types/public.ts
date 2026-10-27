/** Datos del sitio que comparte el backend como `site` en páginas públicas. */
export type SiteNavLink = {
    label: string;
    href: string;
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
    navigation?: SiteNavLink[];
};
