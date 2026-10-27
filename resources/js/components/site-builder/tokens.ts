/**
 * Tokens visuales del editor: traducen los valores guardados (nombres de
 * token de la marca o #hex validados en el servidor) a CSS.
 */
import type { CSSProperties } from 'vue';
import type { Block, SectionSettings } from './types';

const HEX = /^#[0-9a-f]{6}$/i;

export const SURFACE_TOKENS: { value: string; label: string; css: string }[] = [
    { value: 'background', label: 'Lino', css: 'var(--color-background)' },
    { value: 'surface', label: 'Blanco cálido', css: 'var(--color-card)' },
    { value: 'linen', label: 'Lino claro', css: 'var(--color-linen)' },
    {
        value: 'stone-soft',
        label: 'Piedra suave',
        css: 'color-mix(in oklab, var(--color-stone) 60%, var(--color-background))',
    },
    { value: 'stone', label: 'Piedra', css: 'var(--color-stone)' },
    {
        value: 'primary-soft',
        label: 'Eucalipto suave',
        css: 'var(--color-primary-soft)',
    },
    { value: 'primary', label: 'Eucalipto', css: 'var(--color-primary)' },
    { value: 'oak', label: 'Roble', css: 'var(--color-oak)' },
    { value: 'espresso', label: 'Espresso', css: 'var(--color-espresso)' },
    { value: 'white', label: 'Blanco', css: '#ffffff' },
];

export const INK_TOKENS: { value: string; label: string; css: string }[] = [
    { value: 'auto', label: 'Automático', css: 'var(--sb-ink)' },
    { value: 'foreground', label: 'Espresso', css: 'var(--color-foreground)' },
    { value: 'muted', label: 'Secundario', css: 'var(--sb-ink-muted)' },
    { value: 'primary', label: 'Eucalipto', css: 'var(--color-primary)' },
    { value: 'oak', label: 'Roble', css: 'var(--color-oak)' },
    { value: 'oak-ink', label: 'Roble legible', css: 'var(--color-oak-ink)' },
    { value: 'white', label: 'Blanco', css: '#ffffff' },
    {
        value: 'espresso',
        label: 'Espresso oscuro',
        css: 'var(--color-espresso)',
    },
];

export const GRADIENTS: {
    value: string;
    label: string;
    css: string;
    dark: boolean;
}[] = [
    {
        value: 'linen-stone',
        label: 'Lino a piedra',
        css: 'linear-gradient(180deg, var(--color-linen), color-mix(in oklab, var(--color-stone) 70%, var(--color-linen)))',
        dark: false,
    },
    {
        value: 'dawn',
        label: 'Amanecer',
        css: 'radial-gradient(120% 90% at 100% 0%, color-mix(in oklab, var(--color-oak) 18%, transparent), transparent 60%), linear-gradient(180deg, #ffffff, var(--color-linen))',
        dark: false,
    },
    {
        value: 'oak-glow',
        label: 'Brillo de roble',
        css: 'radial-gradient(90% 80% at 0% 100%, color-mix(in oklab, var(--color-oak) 30%, transparent), transparent 65%), var(--color-linen)',
        dark: false,
    },
    {
        value: 'espresso-depth',
        label: 'Espresso profundo',
        css: 'radial-gradient(110% 80% at 0% 0%, #463a32, var(--color-espresso) 62%)',
        dark: true,
    },
    {
        value: 'eucalyptus',
        label: 'Eucalipto',
        css: 'radial-gradient(100% 90% at 100% 0%, #3c6d5d, var(--color-primary) 55%, #1f3d33)',
        dark: true,
    },
];

const DARK_SURFACES = ['espresso', 'primary', 'oak'];

export function surfaceCss(value: string): string {
    if (HEX.test(value)) return value;

    return (
        SURFACE_TOKENS.find((t) => t.value === value)?.css ??
        'var(--color-background)'
    );
}

export function inkCss(value: string | undefined | null): string {
    if (value && HEX.test(value)) return value;

    return INK_TOKENS.find((t) => t.value === value)?.css ?? 'var(--sb-ink)';
}

function hexIsDark(hex: string): boolean {
    const n = Number.parseInt(hex.slice(1), 16);
    const r = (n >> 16) & 255;
    const g = (n >> 8) & 255;
    const b = n & 255;

    return (0.299 * r + 0.587 * g + 0.114 * b) / 255 < 0.5;
}

/** ¿El fondo de la sección pide texto claro? */
export function isDarkSection(settings: SectionSettings): boolean {
    if (settings.tone !== 'auto') return settings.tone === 'dark';

    const bg = settings.background;
    switch (bg.kind) {
        case 'image':
            return bg.mediaId !== null && bg.overlay >= 30
                ? true
                : DARK_SURFACES.includes(bg.color);
        case 'gradient':
            return (
                GRADIENTS.find((g) => g.value === bg.gradient)?.dark ?? false
            );
        case 'color':
            return HEX.test(bg.color)
                ? hexIsDark(bg.color)
                : DARK_SURFACES.includes(bg.color);
        default:
            return false;
    }
}

export function backgroundCss(settings: SectionSettings): string | undefined {
    const bg = settings.background;
    if (bg.kind === 'color') return surfaceCss(bg.color);
    if (bg.kind === 'gradient') {
        return GRADIENTS.find((g) => g.value === bg.gradient)?.css;
    }
    if (bg.kind === 'image') return surfaceCss(bg.color || 'espresso');

    return undefined;
}

export function overlayCss(settings: SectionSettings): string | undefined {
    const { overlay, overlayStyle } = settings.background;
    if (!overlay) return undefined;
    const a = overlay / 100;
    const ink = (alpha: number) => `rgb(22 17 14 / ${alpha.toFixed(2)})`;

    switch (overlayStyle) {
        case 'left':
            return `linear-gradient(90deg, ${ink(a)} 0%, ${ink(a * 0.55)} 50%, ${ink(a * 0.15)} 100%)`;
        case 'bottom':
            return `linear-gradient(0deg, ${ink(a)} 0%, ${ink(a * 0.45)} 55%, ${ink(a * 0.2)} 100%)`;
        default:
            return ink(a);
    }
}

const RADIUS: Record<Block['style']['radius'], string> = {
    none: '0px',
    sm: '6px',
    md: '8px',
    lg: '10px',
    xl: '14px',
    '2xl': '2rem',
    full: '9999px',
};

const SHADOW: Record<Block['style']['shadow'], string> = {
    none: 'none',
    sm: '0 1px 3px rgb(35 30 26 / .07), 0 1px 2px rgb(35 30 26 / .04)',
    md: '0 12px 32px -12px rgb(35 30 26 / .18), 0 4px 10px -6px rgb(35 30 26 / .08)',
    lg: '0 32px 64px -24px rgb(35 30 26 / .32), 0 12px 24px -12px rgb(35 30 26 / .12)',
};

export function radiusCss(value: Block['style']['radius']): string {
    return RADIUS[value] ?? '0px';
}

export function shadowCss(value: Block['style']['shadow']): string {
    return SHADOW[value] ?? 'none';
}

/** Estilo visual común de un bloque (opacidad y giro). */
export function blockVisualStyle(block: Block): CSSProperties {
    const style: CSSProperties = {};
    if (block.style.opacity < 100) style.opacity = block.style.opacity / 100;
    if (block.style.rotate) style.rotate = `${block.style.rotate}deg`;

    return style;
}

export const MOTION_PRESETS: { value: string; label: string }[] = [
    { value: 'fade-up', label: 'Aparecer hacia arriba' },
    { value: 'fade-in', label: 'Desvanecer' },
    { value: 'zoom-in', label: 'Acercar' },
    { value: 'slide-left', label: 'Deslizar desde la derecha' },
    { value: 'slide-right', label: 'Deslizar desde la izquierda' },
    { value: 'blur-in', label: 'Enfocar' },
    { value: 'mask-reveal', label: 'Revelar con máscara' },
    { value: 'settle', label: 'Asentar (imagen principal)' },
    { value: 'none', label: 'Sin movimiento' },
];
