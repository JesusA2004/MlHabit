import { describe, expect, it } from 'vitest';
import { composeForSite, compositeOf } from './compose';
import type { Block, BlockType } from './types';

function block(
    id: string,
    type: BlockType,
    x: number,
    y: number,
    w: number,
    h: number,
): Block {
    return {
        id,
        type,
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
    };
}

describe('composeForSite', () => {
    it('groups contiguous buttons of the same row', () => {
        const out = composeForSite([
            block('h', 'heading', 0, 0, 8, 10),
            block('a', 'button', 0, 20, 3, 2),
            block('b', 'button', 3, 20, 3, 2),
        ]);

        const group = out.find((b) => compositeOf(b)?.kind === 'buttons');
        expect(group).toBeDefined();
        expect(group).toMatchObject({ x: 0, y: 20, w: 6, h: 2 });
        expect(compositeOf(group!)?.children.map((c) => c.id)).toEqual([
            'a',
            'b',
        ]);
        expect(out).toHaveLength(2);
    });

    it('keeps distant buttons apart', () => {
        const out = composeForSite([
            block('a', 'button', 0, 20, 3, 2),
            block('b', 'button', 8, 20, 3, 2),
        ]);

        expect(out.some((b) => compositeOf(b))).toBe(false);
    });

    it('stacks the copy beside a tall timeline into a sticky column', () => {
        const out = composeForSite([
            block('badge', 'badge', 0, 0, 5, 1),
            block('title', 'heading', 0, 2, 5, 7),
            block('lead', 'text', 0, 10, 5, 3),
            block('steps', 'process_steps', 6, 0, 6, 35),
        ]);

        const stack = out.find((b) => compositeOf(b)?.kind === 'stack');
        expect(stack).toMatchObject({ x: 0, y: 0, w: 5, h: 35 });
        expect(compositeOf(stack!)?.children.map((c) => c.id)).toEqual([
            'badge',
            'title',
            'lead',
        ]);
    });

    it('does not stack when an image sits beside the tall block', () => {
        const out = composeForSite([
            block('title', 'heading', 0, 0, 5, 7),
            block('photo', 'image', 0, 8, 5, 6),
            block('steps', 'process_steps', 6, 0, 6, 35),
        ]);

        expect(out.some((b) => compositeOf(b))).toBe(false);
    });
});
