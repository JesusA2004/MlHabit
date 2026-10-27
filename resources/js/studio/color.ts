/**
 * Utilidades de color para Studio: resolución de tokens de marca y
 * contraste WCAG, usadas para impedir combinaciones ilegibles.
 */

export const TOKEN_DEFAULTS: Record<string, string> = {
    ink: '#0D0E10',
    'ink-soft': '#16181B',
    'ink-raised': '#1D2024',
    paper: '#F4F1EB',
    gold: '#C7AA7D',
    'gold-soft': '#D9C4A1',
    mist: '#ABA9A3',
};

export function isToken(value: string): boolean {
    return value in TOKEN_DEFAULTS;
}

/** Valor CSS de un color de Studio (token → var(--color-x), hex → hex). */
export function cssColor(value: unknown): string | null {
    if (typeof value !== 'string') return null;
    if (isToken(value)) return `var(--color-${value})`;
    if (/^#[0-9a-fA-F]{6}$/.test(value)) return value;

    return null;
}

/** Hex efectivo leyendo la variable del documento (respeta Apariencia). */
export function resolveHex(value: string, root?: HTMLElement): string {
    if (!isToken(value)) return value;
    if (typeof window !== 'undefined') {
        const el = root ?? document.documentElement;
        const live = getComputedStyle(el).getPropertyValue(`--color-${value}`).trim();
        if (/^#[0-9a-fA-F]{6}$/.test(live)) return live;
    }

    return TOKEN_DEFAULTS[value];
}

function luminance(hex: string): number {
    const n = hex.replace('#', '');
    const channels = [0, 2, 4].map((i) => parseInt(n.slice(i, i + 2), 16) / 255);
    const [r, g, b] = channels.map((c) =>
        c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4,
    );

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrastRatio(a: string, b: string): number {
    const la = luminance(a);
    const lb = luminance(b);

    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

/** Fondo y texto por defecto de cada tono de sección. */
export const TONES: Record<string, { bg: string; fg: string; muted: string }> = {
    ink: { bg: 'ink', fg: 'paper', muted: 'mist' },
    'ink-soft': { bg: 'ink-soft', fg: 'paper', muted: 'mist' },
    paper: { bg: 'paper', fg: 'ink', muted: '#5C5A55' },
    gold: { bg: 'gold', fg: 'ink', muted: '#3A3326' },
};

/** Sugiere el color legible (papel o tinta) sobre un fondo. */
export function readableOn(background: string): string {
    const bg = resolveHex(background);

    return contrastRatio(bg, resolveHex('paper')) >= contrastRatio(bg, resolveHex('ink'))
        ? 'paper'
        : 'ink';
}
