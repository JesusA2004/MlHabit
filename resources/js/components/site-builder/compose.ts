/**
 * Composición del lienzo para el SITIO PÚBLICO (nunca en el editor).
 *
 * El editor guarda bloques sueltos en una retícula de 12 columnas. Al
 * publicarse, algunas combinaciones se comportan mejor como una unidad:
 *
 * 1. **Fila de botones**: dos o más botones contiguos en la misma fila se
 *    vuelven un grupo con `flex-wrap`. En tableta o en escritorio angosto
 *    cada botón ya no queda recortado en su columna ni se encima con el
 *    vecino: si no caben juntos, el segundo baja de renglón.
 * 2. **Columna fija ("sticky")**: el texto de una sección (antetítulo,
 *    título, entradilla, botón) que acompaña a un bloque alto (línea de
 *    tiempo, preguntas, formas de pago) se agrupa y se queda fijo mientras
 *    el bloque alto avanza. Desaparece el hueco vacío bajo el título.
 *
 * Es una transformación de solo lectura: el editor sigue viendo y guardando
 * los bloques originales; el orden de lectura (móvil) no cambia.
 */
import type { Block, BlockType } from './types';

export type CompositeKind = 'buttons' | 'stack';

export const COMPOSITE_KEY = '__composite';

export type CompositeProps = {
    [COMPOSITE_KEY]: { kind: CompositeKind; children: Block[] };
};

/** Bloques de texto que pueden viajar en una columna fija. */
const TEXTUAL: BlockType[] = [
    'heading',
    'text',
    'badge',
    'button',
    'list',
    'icon',
    'stat',
];

/** Bloques altos que "arrastran" una columna fija a su lado. */
const ANCHORS: BlockType[] = [
    'process_steps',
    'faq',
    'payment_options',
    'benefits_list',
];

const MIN_ANCHOR_ROWS = 18;

export function compositeOf(
    block: Block,
): { kind: CompositeKind; children: Block[] } | null {
    const value = (block.props as Partial<CompositeProps>)[COMPOSITE_KEY];

    return value && Array.isArray(value.children) ? value : null;
}

function makeComposite(
    kind: CompositeKind,
    children: Block[],
    area: {
        x: number;
        y: number;
        w: number;
        h: number;
    },
): Block {
    const first = children[0];
    const hideOn = (['tablet', 'mobile'] as const).filter((device) =>
        children.every((c) => c.hideOn.includes(device)),
    );

    return {
        ...first,
        ...area,
        id: `${kind}:${children.map((c) => c.id).join('+')}`,
        z: Math.max(...children.map((c) => c.z)),
        hideOn: [...hideOn],
        locked: true,
        motion: { preset: 'none', delay: 0 },
        style: { ...first.style, opacity: 100, rotate: 0, shadow: 'none' },
        props: {
            [COMPOSITE_KEY]: { kind, children },
        } satisfies CompositeProps,
    };
}

function overlapsRows(a: Block, b: Block): boolean {
    return a.y < b.y + b.h && b.y < a.y + a.h;
}

/** Une botones contiguos de la misma fila. */
function groupButtons(blocks: Block[]): Block[] {
    const buttons = blocks
        .filter((b) => b.type === 'button' && b.style.rotate === 0)
        .sort((a, b) => a.y - b.y || a.x - b.x);
    const used = new Set<string>();
    const groups: Block[][] = [];

    for (const button of buttons) {
        if (used.has(button.id)) continue;

        const row = [button];
        let right = button.x + button.w;

        for (const next of buttons) {
            if (
                used.has(next.id) ||
                next === button ||
                Math.abs(next.y - button.y) > 1 ||
                next.x < right ||
                next.x > right + 1
            ) {
                continue;
            }

            row.push(next);
            right = next.x + next.w;
        }

        if (row.length > 1) {
            row.forEach((b) => used.add(b.id));
            groups.push(row);
        }
    }

    if (!groups.length) return blocks;

    const out = blocks.filter((b) => !used.has(b.id));

    for (const row of groups) {
        const x = Math.min(...row.map((b) => b.x));
        const y = Math.min(...row.map((b) => b.y));
        const right = Math.max(...row.map((b) => b.x + b.w));
        const bottom = Math.max(...row.map((b) => b.y + b.h));
        out.push(
            makeComposite('buttons', row, {
                x,
                y,
                w: right - x,
                h: bottom - y,
            }),
        );
    }

    return out;
}

/** Agrupa el texto que acompaña a un bloque alto en una columna fija. */
function stickyStacks(blocks: Block[]): Block[] {
    let current = blocks;

    for (const anchor of blocks.filter(
        (b) => ANCHORS.includes(b.type) && b.h >= MIN_ANCHOR_ROWS,
    )) {
        const bottom = anchor.y + anchor.h;

        for (const side of ['left', 'right'] as const) {
            const beside = current.filter((b) => {
                if (b === anchor || b.y >= bottom) return false;
                const onSide =
                    side === 'left'
                        ? b.x + b.w <= anchor.x
                        : b.x >= anchor.x + anchor.w;

                return onSide && overlapsRows(b, anchor);
            });

            if (beside.length < 2) continue;

            const members = beside;
            const textual = members.every((b) => {
                const composite = compositeOf(b);

                return composite
                    ? composite.kind === 'buttons'
                    : TEXTUAL.includes(b.type) && b.style.rotate === 0;
            });

            if (!textual) continue;

            const top = Math.min(...members.map((b) => b.y));
            const end = Math.max(...members.map((b) => b.y + b.h));

            // Solo si el texto es claramente más corto que el bloque alto.
            if (
                top < anchor.y - 4 ||
                end > anchor.y + anchor.h * 0.75 ||
                end - top > anchor.h * 0.75
            ) {
                continue;
            }

            const ordered = [...members].sort((a, b) => a.y - b.y || a.x - b.x);
            const x = Math.min(...members.map((b) => b.x));
            const right = Math.max(...members.map((b) => b.x + b.w));
            const ids = new Set(members.map((b) => b.id));

            current = [
                ...current.filter((b) => !ids.has(b.id)),
                makeComposite('stack', ordered, {
                    x,
                    y: top,
                    w: right - x,
                    h: Math.max(bottom, end) - top,
                }),
            ];
        }
    }

    return current;
}

export function composeForSite(blocks: Block[]): Block[] {
    return stickyStacks(groupButtons(blocks));
}
