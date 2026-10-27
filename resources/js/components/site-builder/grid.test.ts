import { describe, expect, it } from 'vitest';
import {
    computeResponsive,
    freeSpot,
    overlapGroups,
    tabletColumns,
} from './grid';
import type { Block } from './types';

function block(
    id: string,
    x: number,
    y: number,
    w: number,
    h: number,
    extra: Partial<Block> = {},
): Block {
    return {
        id,
        type: 'text',
        x,
        y,
        w,
        h,
        z: 1,
        props: {},
        style: {
            align: 'start',
            radius: 'none',
            shadow: 'none',
            opacity: 100,
            rotate: 0,
        },
        motion: { preset: 'fade-up', delay: 0 },
        hideOn: [],
        locked: false,
        ...extra,
    };
}

const ids = (blocks: Block[]) => blocks.map((b) => b.id);

describe('mobile reading order', () => {
    it('stacks by rows, then left to right', () => {
        const { order } = computeResponsive([
            block('c', 6, 10, 6, 4),
            block('a', 0, 0, 12, 4),
            block('b', 0, 10, 6, 4),
        ]);

        expect(ids(order)).toEqual(['a', 'b', 'c']);
    });

    it('reads a column completely before the next one in the same band', () => {
        // Título y texto a la izquierda, foto alta a la derecha.
        const { order } = computeResponsive([
            block('image', 7, 0, 5, 20, { type: 'image' }),
            block('title', 0, 2, 6, 5, { type: 'heading' }),
            block('text', 0, 8, 6, 4),
            block('button', 0, 13, 3, 2, { type: 'button' }),
            block('footer', 0, 24, 12, 3),
        ]);

        expect(ids(order)).toEqual([
            'title',
            'text',
            'button',
            'image',
            'footer',
        ]);
    });

    it('groups overlapping blocks into one cell with a backdrop', () => {
        const { order, placements } = computeResponsive([
            block('photo', 0, 0, 12, 20, { type: 'image', z: 0 }),
            block('caption', 1, 14, 6, 4, { z: 2 }),
            block('next', 0, 22, 6, 4),
        ]);

        expect(ids(order)).toEqual(['photo', 'caption', 'next']);
        expect(placements.photo.role).toBe('backdrop');
        expect(placements.caption.role).toBe('overlay');
        expect(placements.caption.mobileAlign).toBe('end');
        expect(placements.photo.mobileRow).toBe(placements.caption.mobileRow);
        expect(placements.next.mobileRow).toBe(placements.photo.mobileRow + 1);
    });

    it('stacks overlapping texts instead of piling them up', () => {
        const { placements } = computeResponsive([
            block('title', 1, 6, 8, 10, { type: 'heading' }),
            block('text', 0, 14, 6, 5),
        ]);

        expect(placements.title.role).toBe('single');
        expect(placements.text.mobileRow).toBe(placements.title.mobileRow + 1);
    });

    it('moves blocks hidden on mobile to the end', () => {
        const { order } = computeResponsive([
            block('decor', 0, 0, 4, 10, { hideOn: ['mobile'] }),
            block('title', 4, 0, 8, 4),
        ]);

        expect(ids(order)).toEqual(['title', 'decor']);
    });

    it('detects transitive overlap groups', () => {
        const groups = overlapGroups([
            block('a', 0, 0, 4, 4, { type: 'image' }),
            block('b', 3, 3, 4, 4, { type: 'shape' }),
            block('c', 6, 6, 4, 4),
            block('d', 0, 20, 4, 4),
        ]);

        expect(
            groups.map((g) => g.blocks.length).sort((a, b) => a - b),
        ).toEqual([1, 3]);
    });
});

describe('tablet mapping', () => {
    it('maps 12 columns onto 8 without gaps or overlaps', () => {
        expect(tabletColumns(0, 4)).toEqual({ x: 0, w: 3 });
        expect(tabletColumns(4, 4)).toEqual({ x: 3, w: 2 });
        expect(tabletColumns(8, 4)).toEqual({ x: 5, w: 3 });
        expect(tabletColumns(0, 12)).toEqual({ x: 0, w: 8 });
        expect(tabletColumns(11, 1)).toEqual({ x: 7, w: 1 });
    });

    it('never produces an empty span', () => {
        for (let x = 0; x < 12; x++) {
            for (let w = 1; x + w <= 12; w++) {
                const t = tabletColumns(x, w);
                expect(t.w).toBeGreaterThanOrEqual(1);
                expect(t.x + t.w).toBeLessThanOrEqual(8);
            }
        }
    });
});

describe('free spot', () => {
    it('finds the first free area', () => {
        expect(freeSpot([block('a', 0, 0, 6, 4)], 6, 4)).toEqual({
            x: 6,
            y: 0,
        });
        expect(freeSpot([block('a', 0, 0, 12, 4)], 6, 4)).toEqual({
            x: 0,
            y: 4,
        });
    });
});
