import type { InjectionKey } from 'vue';
import { inject } from 'vue';
import type { RenderContext, ResolvedMedia, StudioLink, StudioNode } from '@/studio/types';

export const RENDER_CTX: InjectionKey<RenderContext> = Symbol('studio-render-ctx');

const EMPTY: RenderContext = {
    resolved: {
        media: {},
        feeds: {},
        categories: [],
        entries: {},
        pages: {},
        social: [],
        currentPage: null,
        post: null,
    },
    editing: false,
    social: [],
};

export function useRenderCtx(): RenderContext {
    return inject(RENDER_CTX, EMPTY);
}

export function mediaFor(ctx: RenderContext, id: unknown): ResolvedMedia | null {
    if (id === null || id === undefined || id === '') return null;
    const media = ctx.resolved.media[String(id)];

    return media && media.url ? media : null;
}

/** Página del CMS a la que se vincula un bloque (bindPage vacío = actual). */
export function boundPage(ctx: RenderContext, node: StudioNode) {
    const key = String(node.props.bindPage || node.props.page || ctx.resolved.currentPage || '');

    return key ? (ctx.resolved.pages[key] ?? null) : null;
}

export interface ResolvedLink {
    href: string;
    external: boolean;
    newTab: boolean;
    /** Enlace interno navegable con Inertia. */
    internal: boolean;
}

export function resolveLink(ctx: RenderContext, link: StudioLink | null | undefined): ResolvedLink | null {
    if (!link || !link.value) return null;

    switch (link.type) {
        case 'page':
            return link.value.startsWith('/') && !link.value.startsWith('//')
                ? { href: link.value, external: false, newTab: link.newTab, internal: !link.newTab }
                : null;
        case 'url':
            return /^(https?:\/\/|mailto:|tel:)/i.test(link.value)
                ? { href: link.value, external: true, newTab: link.newTab, internal: false }
                : null;
        case 'social': {
            const social = [...ctx.social, ...ctx.resolved.social].find((s) => s.platform === link.value);

            return social ? { href: social.url, external: true, newTab: true, internal: false } : null;
        }
        case 'anchor':
            return { href: `#${link.value.replace(/^#/, '')}`, external: false, newTab: false, internal: false };
    }

    return null;
}

export function splitParagraphs(text: string): string[] {
    return text
        .split(/\n{2,}/)
        .map((p) => p.trim())
        .filter(Boolean);
}

export function formatDate(iso: string | null): string {
    if (!iso) return '';

    return new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(iso));
}
