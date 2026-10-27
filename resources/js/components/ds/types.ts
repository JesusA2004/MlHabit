import type { HTMLAttributes } from 'vue';

/** Columna compartida por la tabla (≥768 px) y las tarjetas móviles de DataView. */
export type DataColumn<T = Record<string, unknown>> = {
    key: string;
    label: string;
    /** Clases para celdas de la tabla. */
    class?: HTMLAttributes['class'];
    headerClass?: HTMLAttributes['class'];
    align?: 'left' | 'right' | 'center';
    /** Título de la tarjeta en móvil (por defecto la primera columna). */
    primary?: boolean;
    /** No se muestra en la tarjeta móvil. */
    hideOnMobile?: boolean;
    /** No se muestra en la tabla. */
    hideOnDesktop?: boolean;
    /** Valor derivado; si no, se lee `row[key]`. */
    value?: (row: T) => unknown;
};

export type RowKey<T> = keyof T | ((row: T) => string | number);

/** Clave estable de una fila. */
export function rowKeyOf<T extends Record<string, any>>(
    row: T,
    index: number,
    rowKey: RowKey<T> | undefined,
): string | number {
    if (typeof rowKey === 'function') {
        return rowKey(row);
    }

    return (
        (row[(rowKey ?? 'id') as keyof T] as string | number | undefined) ??
        index
    );
}

/** Valor de una celda (derivado o leído de la fila). */
export function cellValue<T extends Record<string, any>>(
    row: T,
    column: DataColumn<T>,
): unknown {
    return column.value ? column.value(row) : row[column.key];
}

/** Texto a mostrar; vacío → raya. */
export function displayValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value as string | number | boolean | bigint);
}
