export interface SiteIdentity {
    name: string;
    subtitle: string;
    logoMediaId: number | null;
    logoUrl: string | null;
    footerTagline: string;
}

export interface ThemePayload {
    colors: { background?: string; foreground?: string; accent?: string; muted?: string };
    fonts: { display?: string; body?: string };
    buttons: { style?: 'solid' | 'outline'; radius?: 'none' | 'sm' | 'md' | 'lg' | 'full' };
    cards: { radius?: 'none' | 'sm' | 'md' | 'lg' | 'xl' };
    markUrl: string | null;
    faviconUrl: string | null;
}

export interface CategorySummary {
    id: number;
    name: string;
    slug: string;
    color: string;
    icon: string | null;
    description: string | null;
}

export interface SocialLinkSummary {
    platform: string;
    username: string | null;
    url: string;
    icon: string | null;
    open_new_tab: boolean;
}

export interface PostSummary {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    publishedAt: string | null;
    category: { name: string; slug: string; color: string } | null;
    coverUrl: string | null;
}

export interface PublicPageProps {
    site: SiteIdentity;
    categories: CategorySummary[];
    social: SocialLinkSummary[];
    theme: ThemePayload;
}
