import type { PostSummary, SocialLinkSummary, TiptapContent } from '@/types';

export type Breakpoint = 'base' | 'laptop' | 'tablet' | 'mobile';

export type StyleValue = string | number | boolean;
export type StyleMap = Partial<Record<string, StyleValue>>;
export type ResponsiveStyle = Partial<Record<Breakpoint, StyleMap>>;

export interface MotionConfig {
    preset: string;
    duration: number;
    delay: number;
    distance: number;
    trigger: 'scroll' | 'load';
    mobile: 'same' | 'reduce' | 'none';
    once: boolean;
}

export interface StudioNode {
    id: string;
    type: string;
    v: number;
    props: Record<string, unknown>;
    style: ResponsiveStyle;
    motion: MotionConfig | null;
    hidden: boolean;
    slots?: Record<string, StudioNode[]>;
}

export interface StudioDocument {
    schema: number;
    sections: StudioNode[];
}

export interface StudioLink {
    type: 'page' | 'url' | 'social' | 'anchor';
    value: string;
    newTab: boolean;
}

export interface ResolvedMedia {
    id: number;
    type: string;
    mime: string;
    url: string | null;
    posterUrl: string | null;
    width: number | null;
    height: number | null;
    duration: number | null;
    alt: string | null;
    filename: string;
}

export interface ResolvedCategory {
    category: {
        name: string;
        slug: string;
        color: string;
        description: string | null;
    };
    post: PostSummary | null;
}

export interface ResolvedEntry {
    id: number;
    title: string;
    date: string | null;
    coverUrl: string | null;
    coverAlt: string | null;
    body: TiptapContent | null;
}

export interface ResolvedPage {
    key: string;
    title: string;
    intro: string | null;
    body: TiptapContent | null;
    coverMediaId: number | null;
}

export interface ResolvedPost {
    slug: string;
    category: { name: string; slug: string; color: string } | null;
    author: string | null;
    publishedAt: string | null;
    readingMinutes: number;
    gallery: { id: number; url: string; alt: string | null }[];
}

export interface StudioResolved {
    media: Record<string, ResolvedMedia>;
    feeds: Record<string, PostSummary[]>;
    categories: ResolvedCategory[];
    entries: Record<string, ResolvedEntry[]>;
    pages: Record<string, ResolvedPage>;
    social: SocialLinkSummary[];
    currentPage: string | null;
    post: ResolvedPost | null;
}

export interface StudioPayload {
    document: StudioDocument;
    resolved: StudioResolved;
    meta: {
        key: string;
        title: string;
        seoTitle: string | null;
        seoDescription: string | null;
    };
}

/** Contexto de render compartido por todos los bloques. */
export interface RenderContext {
    resolved: StudioResolved;
    editing: boolean;
    /** Redes del sitio (para enlaces de tipo social en botones). */
    social: SocialLinkSummary[];
}

export interface NodeLocation {
    node: StudioNode;
    /** Arreglo que contiene al nodo (secciones o un slot). */
    list: StudioNode[];
    index: number;
    section: StudioNode;
    slot: string | null;
}
