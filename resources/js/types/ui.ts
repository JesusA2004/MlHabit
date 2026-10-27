export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    description?: string;
};

/** Tonos semánticos compartidos (StatusBadge, Timeline, alertas). */
export type Tone =
    | 'success'
    | 'warning'
    | 'danger'
    | 'info'
    | 'neutral'
    | 'brand';

/** Paginador de Laravel serializado como recurso (`{ data, links, meta }`). */
export type ResourcePaginator<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        path?: string;
    };
};

/** Paginador de Laravel serializado directamente (`->paginate()`). */
export type LengthAwarePaginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

export type Paginator<T> = ResourcePaginator<T> | LengthAwarePaginator<T>;

/** Forma normalizada que usan DataView y DataPagination. */
export type PaginationState = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
    prevUrl: string | null;
    nextUrl: string | null;
};
