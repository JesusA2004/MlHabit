/**
 * Retícula del editor visual y cálculo responsivo automático.
 *
 * El dueño acomoda los bloques SOLO en escritorio (12 columnas, filas de
 * 24 px sobre un contenido de 1200 px). A partir de ahí:
 *
 * - Tableta (768–1023 px): misma fila/alto, columnas mapeadas a 8. Cada
 *   borde de columna `b` se lleva a `round(b × 8 / 12)`, así dos bloques que
 *   se tocaban se siguen tocando y ninguno se encima por redondeo.
 * - Móvil (< 768 px): una sola columna en orden de lectura. Los bloques que
 *   se enciman (p. ej. texto sobre una foto) forman un grupo que comparte
 *   la misma celda: el más grande hace de fondo y los demás van encima.
 *   Los grupos se ordenan por franjas horizontales (de arriba abajo) y,
 *   dentro de cada franja, por columnas (de izquierda a derecha, y cada
 *   columna de arriba abajo), que es como se lee la composición.
 */
import type { Block } from './types';

export const COLUMNS = 12;
export const TABLET_COLUMNS = 8;
export const ROW_PX = 24;
export const CONTENT_PX = 1200;
export const GAP_PX = 24;
export const COLUMN_PX = (CONTENT_PX - (COLUMNS - 1) * GAP_PX) / COLUMNS;
export const MAX_ROW = 400;
export const MAX_HEIGHT = 200;

/** Ancho en px de escritorio (lienzo de referencia) de `w` columnas. */
export function widthPx(w: number): number {
    return w * COLUMN_PX + Math.max(0, w - 1) * GAP_PX;
}

export function heightPx(h: number): number {
    return h * ROW_PX;
}

/** Filas que ocupa el lienzo. */
export function rowsOf(blocks: Pick<Block, 'y' | 'h'>[], min = 0): number {
    return blocks.reduce((max, b) => Math.max(max, b.y + b.h), min);
}

/** Columnas de tableta: mapeo de bordes con redondeo. */
export function tabletColumns(x: number, w: number): { x: number; w: number } {
    const map = (edge: number) => Math.round((edge * TABLET_COLUMNS) / COLUMNS);
    const start = Math.min(TABLET_COLUMNS - 1, map(x));
    const end = Math.min(TABLET_COLUMNS, Math.max(start + 1, map(x + w)));

    return { x: start, w: end - start };
}

type Rect = { top: number; bottom: number; left: number; right: number };

function rectOf(b: Block): Rect {
    return { top: b.y, bottom: b.y + b.h, left: b.x, right: b.x + b.w };
}

function intersects(a: Rect, b: Rect): boolean {
    return (
        a.left < b.right &&
        b.left < a.right &&
        a.top < b.bottom &&
        b.top < a.bottom
    );
}

export type MobileUnit = {
    /** Bloques del grupo, del fondo hacia el frente (z ascendente). */
    blocks: Block[];
    /** Bloque que hace de fondo cuando el grupo tiene varios. */
    backdropId: string | null;
    rect: Rect;
};

/** Bloques que pueden quedar detrás de otros en móvil. */
const BACKDROPS = ['image', 'video', 'shape'];

function isBackdrop(b: Block): boolean {
    return BACKDROPS.includes(b.type);
}

/** Agrupa los bloques que se enciman sobre una foto, video o forma. */
export function overlapGroups(blocks: Block[]): MobileUnit[] {
    const parent = blocks.map((_, i) => i);
    const find = (i: number): number =>
        parent[i] === i ? i : (parent[i] = find(parent[i]));

    for (let i = 0; i < blocks.length; i++) {
        for (let j = i + 1; j < blocks.length; j++) {
            // Solo se agrupan si uno puede hacer de fondo (foto, video,
            // forma); dos textos encimados se apilan uno tras otro.
            if (
                (isBackdrop(blocks[i]) || isBackdrop(blocks[j])) &&
                intersects(rectOf(blocks[i]), rectOf(blocks[j]))
            ) {
                parent[find(i)] = find(j);
            }
        }
    }

    const groups = new Map<number, Block[]>();
    blocks.forEach((b, i) => {
        const root = find(i);
        groups.set(root, [...(groups.get(root) ?? []), b]);
    });

    return [...groups.values()].map((members) => {
        const rect = members.map(rectOf).reduce((acc, r) => ({
            top: Math.min(acc.top, r.top),
            bottom: Math.max(acc.bottom, r.bottom),
            left: Math.min(acc.left, r.left),
            right: Math.max(acc.right, r.right),
        }));
        const sorted = [...members].sort((a, b) => a.z - b.z || a.y - b.y);
        const backdrop =
            members.length > 1
                ? [...members].sort(
                      (a, b) =>
                          Number(isBackdrop(b)) - Number(isBackdrop(a)) ||
                          b.w * b.h - a.w * a.h ||
                          a.z - b.z,
                  )[0]
                : null;

        return {
            blocks: backdrop
                ? [backdrop, ...sorted.filter((b) => b.id !== backdrop.id)]
                : sorted,
            backdropId: backdrop?.id ?? null,
            rect,
        };
    });
}

/**
 * Orden de lectura de las unidades (bloques o grupos) para el apilado
 * móvil: franjas de arriba abajo; dentro de cada franja, columnas de
 * izquierda a derecha; dentro de cada columna, de arriba abajo.
 */
export function readingOrder(units: MobileUnit[]): MobileUnit[] {
    const byTop = [...units].sort(
        (a, b) => a.rect.top - b.rect.top || a.rect.left - b.rect.left,
    );
    const bands: MobileUnit[][] = [];
    let bottom = -Infinity;

    for (const unit of byTop) {
        if (bands.length && unit.rect.top < bottom) {
            bands[bands.length - 1].push(unit);
            bottom = Math.max(bottom, unit.rect.bottom);
        } else {
            bands.push([unit]);
            bottom = unit.rect.bottom;
        }
    }

    return bands.flatMap((band) => {
        const byLeft = [...band].sort(
            (a, b) => a.rect.left - b.rect.left || a.rect.top - b.rect.top,
        );
        const columns: MobileUnit[][] = [];
        let right = -Infinity;

        for (const unit of byLeft) {
            if (columns.length && unit.rect.left < right) {
                columns[columns.length - 1].push(unit);
                right = Math.max(right, unit.rect.right);
            } else {
                columns.push([unit]);
                right = unit.rect.right;
            }
        }

        return columns.flatMap((column) =>
            [...column].sort(
                (a, b) => a.rect.top - b.rect.top || a.rect.left - b.rect.left,
            ),
        );
    });
}

export type Placement = {
    /** grid-area de escritorio. */
    area: string;
    /** grid-area de tableta. */
    areaTablet: string;
    /** Fila de la columna única en móvil (1-based). */
    mobileRow: number;
    /** Alineación vertical dentro de su grupo en móvil. */
    mobileAlign: 'start' | 'center' | 'end' | 'stretch';
    role: 'single' | 'backdrop' | 'overlay';
    /** Proporción ancho/alto del marco de escritorio. */
    aspect: number;
};

export type ResponsiveLayout = {
    /** Bloques en orden de lectura (orden del DOM). */
    order: Block[];
    placements: Record<string, Placement>;
    rows: number;
};

/**
 * Calcula la colocación de escritorio, tableta y móvil de todos los
 * bloques de un lienzo. Los bloques ocultos en móvil quedan al final del
 * orden (no se ven en móvil y en escritorio el orden del DOM no importa
 * para la posición).
 */
export function computeResponsive(blocks: Block[]): ResponsiveLayout {
    const visibleMobile = blocks.filter((b) => !b.hideOn.includes('mobile'));
    const hiddenMobile = blocks.filter((b) => b.hideOn.includes('mobile'));
    const units = readingOrder(overlapGroups(visibleMobile));
    const placements: Record<string, Placement> = {};
    const order: Block[] = [];

    const base = (
        b: Block,
    ): Omit<Placement, 'mobileRow' | 'mobileAlign' | 'role'> => {
        const t = tabletColumns(b.x, b.w);

        return {
            area: `${b.y + 1} / ${b.x + 1} / span ${b.h} / span ${b.w}`,
            areaTablet: `${b.y + 1} / ${t.x + 1} / span ${b.h} / span ${t.w}`,
            aspect: widthPx(b.w) / heightPx(b.h),
        };
    };

    units.forEach((unit, index) => {
        const height = Math.max(1, unit.rect.bottom - unit.rect.top);

        for (const b of unit.blocks) {
            const center = (b.y + b.h / 2 - unit.rect.top) / height;
            const role: Placement['role'] =
                unit.backdropId === null
                    ? 'single'
                    : unit.backdropId === b.id
                      ? 'backdrop'
                      : 'overlay';

            placements[b.id] = {
                ...base(b),
                mobileRow: index + 1,
                mobileAlign:
                    role === 'overlay'
                        ? center < 0.34
                            ? 'start'
                            : center > 0.66
                              ? 'end'
                              : 'center'
                        : 'stretch',
                role,
            };
            order.push(b);
        }
    });

    for (const b of hiddenMobile) {
        placements[b.id] = {
            ...base(b),
            mobileRow: units.length + 1,
            mobileAlign: 'stretch',
            role: 'single',
        };
        order.push(b);
    }

    return { order, placements, rows: rowsOf(blocks) };
}

/** Busca un hueco libre para un bloque nuevo de w×h (debajo de todo). */
export function freeSpot(
    blocks: Block[],
    w: number,
    h: number,
    preferY = 0,
): { x: number; y: number } {
    for (let y = preferY; y < MAX_ROW; y++) {
        for (let x = 0; x + w <= COLUMNS; x++) {
            const rect = { top: y, bottom: y + h, left: x, right: x + w };
            if (!blocks.some((b) => intersects(rect, rectOf(b)))) {
                return { x, y };
            }
        }
    }

    return { x: 0, y: rowsOf(blocks) };
}

export function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}
