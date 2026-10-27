import { bedroom, closet, kitchen, shoes, tv } from './furniture';
import { blueprint, tape, toolbox } from './tools';
import type { IllustrationDef, IllustrationName } from './types';

export type { IllustrationDef, IllustrationName } from './types';

export const ILLUSTRATIONS: Record<IllustrationName, IllustrationDef> = {
    kitchen,
    closet,
    bedroom,
    tv,
    shoes,
    tape,
    blueprint,
    toolbox,
};

const KEYWORDS: [RegExp, IllustrationName][] = [
    [/cocin|kitchen|isla|alacena/i, 'kitchen'],
    [/cl[oó]set|vestidor|guardarropa|closet/i, 'closet'],
    [/rec[aá]mara|dormitorio|cabecera|cama|bed/i, 'bedroom'],
    [/entreten|tv|televis|mueble de sala|librero/i, 'tv'],
    [/zapat|calzado|recibidor|shoe/i, 'shoes'],
];

/** Ilustración que corresponde a una categoría (por slug o nombre). */
export function illustrationFor(
    ...hints: (string | null | undefined)[]
): IllustrationName {
    const text = hints.filter(Boolean).join(' ');

    return KEYWORDS.find(([pattern]) => pattern.test(text))?.[1] ?? 'blueprint';
}
