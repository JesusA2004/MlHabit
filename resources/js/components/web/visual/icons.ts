/**
 * Lenguaje de iconos del sitio público: Lucide con trazo 1.5 y tamaños por
 * token. Los iconos acompañan texto; solos, llevan etiqueta accesible.
 */
import {
    BedDouble,
    CookingPot,
    Footprints,
    Gem,
    Hammer,
    Layers,
    Lightbulb,
    PencilRuler,
    Ruler,
    ShieldCheck,
    Shirt,
    Sparkles,
    Timer,
    Truck,
    Tv,
    Wrench,
} from '@lucide/vue';
import type { Component } from 'vue';

export const ICON_STROKE = 1.5;

/** Tamaños: xs 14 · sm 16 · md 20 · lg 24 · xl 32. */
export const ICON_SIZE = {
    xs: 'size-3.5',
    sm: 'size-4',
    md: 'size-5',
    lg: 'size-6',
    xl: 'size-8',
} as const;

export type IconSize = keyof typeof ICON_SIZE;

const CATEGORY_ICONS: [RegExp, Component][] = [
    [/cocin|kitchen|isla|alacena/i, CookingPot],
    [/cl[oó]set|vestidor|guardarropa/i, Shirt],
    [/rec[aá]mara|dormitorio|cabecera|cama/i, BedDouble],
    [/entreten|tv|televis|librero/i, Tv],
    [/zapat|calzado|recibidor/i, Footprints],
];

/** Icono de una categoría por su slug o nombre. */
export function categoryIcon(
    ...hints: (string | null | undefined)[]
): Component {
    const text = hints.filter(Boolean).join(' ');

    return CATEGORY_ICONS.find(([p]) => p.test(text))?.[1] ?? PencilRuler;
}

const FEATURE_ICONS: [RegExp, Component][] = [
    [/medid|dimens|a la medida|cm|metro/i, Ruler],
    [
        /material|melamina|mdf|madera|chapa|laca|acabado|cubierta|cuarzo|granito/i,
        Layers,
    ],
    [/herraje|bisagra|corredera|cierre|amortigu|riel/i, Wrench],
    [/garant|resisten|durab|calidad/i, ShieldCheck],
    [/luz|led|ilumin/i, Lightbulb],
    [/instal|montaje/i, Hammer],
    [/entreg|envío|flete|traslado/i, Truck],
    [/tiempo|semana|días|plazo/i, Timer],
    [/diseñ|estilo|render/i, Sparkles],
    [/premium|lujo|fino/i, Gem],
];

/** Icono para una característica de producto según su texto. */
export function featureIcon(text: string): Component {
    return FEATURE_ICONS.find(([p]) => p.test(text))?.[1] ?? Sparkles;
}
