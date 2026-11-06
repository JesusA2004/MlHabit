import type { TourStep } from '../types';

/**
 * Atajos para escribir pasos que apuntan a piezas compartidas del sistema
 * de diseño (encabezado de pantalla, filtros, listas, tarjetas, pestañas).
 * Así casi ningún recorrido necesita atributos propios en las páginas.
 */
type Extra = Partial<TourStep>;

export const header = (title: string, body: string, extra: Extra = {}) =>
    ({ target: 'page-header', title, body, ...extra }) satisfies TourStep;

export const actions = (title: string, body: string, extra: Extra = {}) =>
    ({
        target: 'page-actions',
        title,
        body,
        optional: true,
        ...extra,
    }) satisfies TourStep;

export const action = (
    label: string,
    title: string,
    body: string,
    extra: Extra = {},
) =>
    ({
        target: [`action:${label}`],
        title,
        body,
        optional: true,
        ...extra,
    }) satisfies TourStep;

export const filters = (body: string, extra: Extra = {}) =>
    ({
        target: 'filters',
        title: 'Busca y filtra',
        body,
        optional: true,
        ...extra,
    }) satisfies TourStep;

export const list = (title: string, body: string, extra: Extra = {}) =>
    ({
        target: ['data-view', 'css:[data-slot="empty-state"]'],
        title,
        body,
        optional: true,
        ...extra,
    }) satisfies TourStep;

export const card = (
    name: string,
    title: string,
    body: string,
    extra: Extra = {},
) =>
    ({
        target: `card:${name}`,
        title,
        body,
        optional: true,
        ...extra,
    }) satisfies TourStep;

export const tabs = (title: string, body: string, extra: Extra = {}) =>
    ({
        target: 'css:[data-slot="tabs-list"]',
        title,
        body,
        optional: true,
        ...extra,
    }) satisfies TourStep;

export const tab = (
    label: string,
    title: string,
    body: string,
    extra: Extra = {},
) =>
    ({
        target: `tab:${label}`,
        title,
        body,
        optional: true,
        ...extra,
    }) satisfies TourStep;

export const content = (title: string, body: string, extra: Extra = {}) =>
    ({
        target: ['css:[data-slot="page-container"]', 'css:#contenido'],
        title,
        body,
        ...extra,
    }) satisfies TourStep;

export const note = (title: string, body: string, extra: Extra = {}) =>
    ({ title, body, ...extra }) satisfies TourStep;

/** Regex exacta para una ruta (con o sin barra final). */
export function exact(path: string): RegExp {
    return new RegExp(`^${path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/?$`);
}

/** Regex para el detalle `base/{id}` que no choca con rutas fijas hermanas. */
export function detail(base: string, reserved: string[] = []): RegExp {
    const escaped = base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const not = reserved.length ? `(?!(?:${reserved.join('|')})/?$)` : '';

    return new RegExp(`^${escaped}/${not}[^/]+/?$`);
}
