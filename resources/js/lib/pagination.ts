import type { PaginationState, Paginator, ResourcePaginator } from '@/types/ui';

function isResource<T>(
    paginator: Paginator<T>,
): paginator is ResourcePaginator<T> {
    return (
        'meta' in paginator &&
        typeof paginator.meta === 'object' &&
        paginator.meta !== null
    );
}

/** Normaliza cualquiera de las dos formas de paginador de Laravel. */
export function normalizePaginator<T>(
    paginator: Paginator<T> | null | undefined,
): PaginationState | null {
    if (!paginator) {
        return null;
    }

    if (isResource(paginator)) {
        return {
            currentPage: paginator.meta.current_page,
            lastPage: paginator.meta.last_page,
            perPage: paginator.meta.per_page,
            total: paginator.meta.total,
            from: paginator.meta.from ?? null,
            to: paginator.meta.to ?? null,
            prevUrl: paginator.links?.prev ?? null,
            nextUrl: paginator.links?.next ?? null,
        };
    }

    return {
        currentPage: paginator.current_page,
        lastPage: paginator.last_page,
        perPage: paginator.per_page,
        total: paginator.total,
        from: paginator.from ?? null,
        to: paginator.to ?? null,
        prevUrl: paginator.prev_page_url,
        nextUrl: paginator.next_page_url,
    };
}

/** URL de una página concreta conservando los filtros actuales del query string. */
export function pageUrl(page: number, base?: string): string {
    const url = new URL(base ?? window.location.href, window.location.origin);
    url.searchParams.set('page', String(page));

    return `${url.pathname}${url.search}`;
}
