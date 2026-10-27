import { cssColor } from './color';
import { schema } from './schema';
import type { Breakpoint, StudioDocument, StudioNode, StyleValue } from './types';

/**
 * Genera el CSS de un documento de Studio.
 *
 * Modelo responsive (determinista):
 * - Cascada desktop-first: base → laptop (≤1279) → tablet (≤1023) → mobile (≤767).
 * - Un valor no definido en un breakpoint se hereda del anterior.
 * - Propiedades de medida (tamaños, márgenes, espacios) heredadas se
 *   AUTO-ESCALAN por dispositivo para evitar desbordes: un título de 82 px
 *   en escritorio hereda ~51 px en móvil salvo que se fije otro valor.
 * - Propiedades de estructura (columnas, apilar, invertir) NO se heredan:
 *   cada dispositivo usa su valor o el comportamiento por defecto.
 *
 * Seguridad: solo se emiten propiedades de la lista blanca de abajo, con
 * valores numéricos acotados, enums o colores validados. Nunca CSS libre.
 */

export const BP_ORDER: Breakpoint[] = ['base', 'laptop', 'tablet', 'mobile'];

const SCALE: Record<Breakpoint, number> = { base: 1, laptop: 0.9, tablet: 0.78, mobile: 0.62 };

/** Media query de cascada (desktop-first). */
const CASCADE_MQ: Record<Breakpoint, string | null> = {
    base: null,
    laptop: '(max-width: 1279px)',
    tablet: '(max-width: 1023px)',
    mobile: '(max-width: 767px)',
};

/** Media query exacta (para propiedades que no se heredan). */
const EXACT_MQ: Record<Breakpoint, string> = {
    base: '(min-width: 1280px)',
    laptop: '(min-width: 1024px) and (max-width: 1279px)',
    tablet: '(min-width: 768px) and (max-width: 1023px)',
    mobile: '(max-width: 767px)',
};

/** Claves cuyo valor heredado se escala por dispositivo. */
const SCALED = new Set([
    'fontSize', 'paddingX', 'paddingY', 'marginTop', 'marginBottom', 'offsetX', 'offsetY',
    'height', 'paddingTop', 'paddingBottom', 'gap', 'spacerHeight', 'radius',
]);

/** Claves que no se heredan entre dispositivos. */
const NON_CASCADING = new Set(['columns', 'stack', 'reverse']);

type Target = 'self' | 'text' | 'frame' | 'media' | 'container' | 'grid' | 'gridFirst';

type Emit = (value: StyleValue, ctx: { mediaNode: boolean }) => Partial<Record<Target, Record<string, string>>>;

const px = (v: StyleValue) => `${Math.round(Number(v) * 100) / 100}px`;

const CONTENT_WIDTH: Record<string, string> = { narrow: '760px', normal: '1240px', wide: '1440px', full: 'none' };

const SHADOWS: Record<string, string> = {
    none: '0 0 #0000',
    soft: '0 12px 32px -14px rgb(0 0 0 / 0.45)',
    medium: '0 24px 60px -22px rgb(0 0 0 / 0.6)',
    deep: '0 40px 100px -30px rgb(0 0 0 / 0.78)',
};

const FRAMES: Record<string, Record<string, string>> = {
    none: { '--sn-frame-shadow': '0 0 #0000', padding: '0' },
    offset: {
        '--sn-frame-shadow': '18px 18px 0 -1px var(--sn-bg, var(--color-ink)), 18px 18px 0 0 var(--color-gold)',
        padding: '0',
    },
    arch: { 'border-radius': '999px 999px 14px 14px', '--sn-frame-shadow': '0 0 #0000', padding: '0' },
    circle: { 'border-radius': '50%', 'aspect-ratio': '1 / 1', '--sn-frame-shadow': '0 0 #0000', padding: '0' },
    inset: { padding: '12px', background: 'var(--color-paper)', '--sn-frame-shadow': '0 0 #0000' },
};

export function fontStack(value: StyleValue): string | null {
    const name = String(value);
    if (name === 'display') return 'var(--font-display)';
    if (name === 'body') return 'var(--font-sans)';
    const def = schema.fonts[name];
    if (!def) return null;

    return `"${name}", ${def.fallback === 'serif' ? 'ui-serif, Georgia, serif' : 'ui-sans-serif, system-ui, sans-serif'}`;
}

const EMIT: Record<string, Emit> = {
    fontFamily: (v) => ({ text: { 'font-family': fontStack(v) ?? 'inherit' } }),
    fontSize: (v) => ({ text: { 'font-size': px(v) } }),
    fontWeight: (v) => ({ text: { 'font-weight': String(v) } }),
    fontStyle: (v) => ({ text: { 'font-style': String(v) } }),
    color: (v) => ({ text: { color: cssColor(v) ?? 'inherit' } }),
    letterSpacing: (v) => ({ text: { 'letter-spacing': `${Number(v)}em` } }),
    lineHeight: (v) => ({ text: { 'line-height': String(Number(v)) } }),
    textAlign: (v) => ({ text: { 'text-align': String(v) } }),
    textTransform: (v) => ({ text: { 'text-transform': String(v) } }),
    maxWidth: (v) => ({ self: { 'max-width': Number(v) > 0 ? px(v) : 'none' } }),

    background: (v) => ({ self: { 'background-color': cssColor(v) ?? 'transparent' } }),
    paddingX: (v) => ({ self: { 'padding-inline': px(v) } }),
    paddingY: (v) => ({ self: { 'padding-block': px(v) } }),
    radius: (v, { mediaNode }) => (mediaNode ? { frame: { 'border-radius': px(v) } } : { self: { 'border-radius': px(v), overflow: 'hidden' } }),
    marginTop: (v) => ({ self: { 'margin-top': px(v) } }),
    marginBottom: (v) => ({ self: { 'margin-bottom': px(v) } }),

    align: (v) => ({ self: { 'align-self': ({ start: 'flex-start', center: 'center', end: 'flex-end', stretch: 'stretch' } as Record<string, string>)[String(v)] ?? 'auto' } }),
    widthPct: (v) => ({ self: { width: `${Number(v)}%` } }),
    offsetX: (v) => ({ self: { '--sn-ox': px(v) } }),
    offsetY: (v) => ({ self: { '--sn-oy': px(v) } }),
    order: (v) => ({ self: { order: String(Math.round(Number(v))) } }),

    hide: (v) => ({ self: { display: v ? 'none' : 'block' } }),

    aspectRatio: (v) => ({ frame: { 'aspect-ratio': v === 'auto' ? 'auto' : String(v).replace('/', ' / ') } }),
    height: (v) => ({ frame: (Number(v) > 0 ? { height: px(v), 'aspect-ratio': 'auto' } : { height: 'auto' }) as Record<string, string> }),
    objectFit: (v) => ({ media: { 'object-fit': String(v) } }),
    focalX: (v) => ({ frame: { '--sn-fx': `${Number(v)}%` } }),
    focalY: (v) => ({ frame: { '--sn-fy': `${Number(v)}%` } }),
    zoom: (v) => ({ frame: { '--sn-zoom': String(Number(v)) } }),
    frame: (v) => ({ frame: FRAMES[String(v)] ?? FRAMES.none }),
    shadow: (v) => ({ frame: { '--sn-shadow': SHADOWS[String(v)] ?? SHADOWS.none } }),
    opacity: (v) => ({ media: { opacity: String(Number(v)) } }),
    overlayColor: (v) => ({ frame: { '--sn-ov-color': cssColor(v) ?? '#000' } }),
    overlayOpacity: (v) => ({ frame: { '--sn-ov': String(Number(v)) } }),
    borderWidth: (v) => ({ frame: { 'border-width': px(v), 'border-style': 'solid' } }),
    borderColor: (v) => ({ frame: { 'border-color': cssColor(v) ?? 'currentColor' } }),

    paddingTop: (v) => ({ self: { 'padding-top': px(v) } }),
    paddingBottom: (v) => ({ self: { 'padding-bottom': px(v) } }),
    minHeight: (v) => ({ self: { 'min-height': Number(v) > 0 ? `${Number(v)}vh` : 'auto' } }),
    sectionBackground: (v) => ({ self: { 'background-color': cssColor(v) ?? 'transparent', '--sn-bg': cssColor(v) ?? 'transparent' } }),
    textColor: (v) => ({ self: { color: cssColor(v) ?? 'inherit', '--sn-fg': cssColor(v) ?? 'inherit' } }),
    contentWidth: (v) => ({ container: { 'max-width': CONTENT_WIDTH[String(v)] ?? '1240px' } }),
    gap: (v) => ({ self: { '--sn-gap': px(v) } }),
    verticalAlign: (v) => ({ self: { '--sn-valign': String(v) } }),
    stack: (v) => ({ grid: { 'grid-template-columns': v ? 'minmax(0, 1fr)' : 'var(--sn-template)' } }),
    reverse: (v) => ({ gridFirst: { order: v ? '2' : '0' } }),
    columns: (v) => ({ self: { '--sn-cols': String(Math.round(Number(v))) } }),
    spacerHeight: (v) => ({ self: { height: px(v) } }),
};

const SELECTOR: Record<Target, (id: string) => string> = {
    self: (id) => `[data-n="${id}"]`,
    text: (id) => `[data-n="${id}"] .sn-t`,
    frame: (id) => `[data-n="${id}"] .sn-frame`,
    media: (id) => `[data-n="${id}"] .sn-frame > :is(img, video)`,
    container: (id) => `[data-n="${id}"] .sn-container`,
    grid: (id) => `[data-n="${id}"] .sn-grid`,
    gridFirst: (id) => `[data-n="${id}"] .sn-grid > :first-child`,
};

export interface EffectiveValue {
    value: StyleValue | undefined;
    /** Breakpoint donde está definido el valor de origen. */
    source: Breakpoint | null;
    /** El valor proviene de otro dispositivo y se auto-escaló. */
    scaled: boolean;
}

/** Valor efectivo de una propiedad de estilo en un breakpoint. */
export function effectiveStyle(node: StudioNode, key: string, bp: Breakpoint): EffectiveValue {
    const own = node.style?.[bp]?.[key];
    if (own !== undefined) return { value: own, source: bp, scaled: false };
    if (NON_CASCADING.has(key)) return { value: undefined, source: null, scaled: false };

    const chain = BP_ORDER.slice(0, BP_ORDER.indexOf(bp)).reverse();
    for (const from of chain) {
        const inherited = node.style?.[from]?.[key];
        if (inherited !== undefined) {
            if (SCALED.has(key) && typeof inherited === 'number') {
                const factor = SCALE[bp] / SCALE[from];
                let scaled = inherited * factor;
                if (key === 'fontSize') scaled = Math.max(scaled, Math.min(inherited, 14));
                scaled = Math.round(scaled);

                return { value: scaled, source: from, scaled: scaled !== inherited };
            }

            return { value: inherited, source: from, scaled: false };
        }
    }

    return { value: undefined, source: null, scaled: false };
}

function nodeCss(node: StudioNode, buckets: Record<string, string[]>): void {
    const mediaNode = node.type === 'image' || node.type === 'video';
    const keys = new Set<string>();
    for (const bp of BP_ORDER) {
        Object.keys(node.style?.[bp] ?? {}).forEach((k) => keys.add(k));
    }
    if (keys.size === 0) return;

    // Orden estable según schema.json (p. ej. `frame` después de `radius`).
    const ordered = Object.keys(schema.styleProps).filter((k) => keys.has(k));

    for (const bp of BP_ORDER) {
        const perTarget: Partial<Record<Target, Record<string, string>>> = {};
        const exactTarget: Partial<Record<Target, Record<string, string>>> = {};

        for (const key of ordered) {
            const emit = EMIT[key];
            if (!emit) continue;

            if (NON_CASCADING.has(key)) {
                const own = node.style?.[bp]?.[key];
                if (own === undefined) continue;
                merge(exactTarget, emit(own, { mediaNode }));
                continue;
            }

            const eff = effectiveStyle(node, key, bp);
            if (eff.value === undefined) continue;
            // Solo se emite donde cambia algo: definición propia o escalado.
            if (eff.source !== bp && !eff.scaled) continue;
            merge(perTarget, emit(eff.value, { mediaNode }));
        }

        const cascadeKey = CASCADE_MQ[bp] ?? '';
        for (const [target, decls] of Object.entries(perTarget)) {
            (buckets[cascadeKey] ??= []).push(rule(SELECTOR[target as Target](node.id), decls));
        }
        for (const [target, decls] of Object.entries(exactTarget)) {
            (buckets[`exact:${EXACT_MQ[bp]}`] ??= []).push(rule(SELECTOR[target as Target](node.id), decls));
        }
    }
}

function merge(into: Partial<Record<Target, Record<string, string>>>, add: Partial<Record<Target, Record<string, string>>>): void {
    for (const [target, decls] of Object.entries(add)) {
        into[target as Target] = { ...(into[target as Target] ?? {}), ...decls };
    }
}

function rule(selector: string, decls: Record<string, string>): string {
    const body = Object.entries(decls)
        .map(([prop, value]) => `${prop}:${value}`)
        .join(';');

    return `${selector}{${body}}`;
}

export function documentCss(doc: StudioDocument): string {
    const buckets: Record<string, string[]> = {};
    for (const section of doc.sections) {
        nodeCss(section, buckets);
        for (const children of Object.values(section.slots ?? {})) {
            children.forEach((child) => nodeCss(child, buckets));
        }
    }

    const out: string[] = [...(buckets[''] ?? [])];
    for (const bp of ['laptop', 'tablet', 'mobile'] as Breakpoint[]) {
        const mq = CASCADE_MQ[bp]!;
        if (buckets[mq]?.length) out.push(`@media ${mq}{${buckets[mq].join('')}}`);
    }
    for (const bp of BP_ORDER) {
        const key = `exact:${EXACT_MQ[bp]}`;
        if (buckets[key]?.length) out.push(`@media ${EXACT_MQ[bp]}{${buckets[key].join('')}}`);
    }

    return out.join('\n');
}

/** Fuentes no incluidas en el bundle que usa el documento. */
export function documentFonts(doc: StudioDocument): string[] {
    const fonts = new Set<string>();
    for (const section of doc.sections) {
        for (const children of Object.values(section.slots ?? {})) {
            for (const child of children) {
                for (const bp of BP_ORDER) {
                    const f = child.style?.[bp]?.fontFamily;
                    if (typeof f === 'string' && f !== 'display' && f !== 'body') fonts.add(f);
                }
            }
        }
    }

    return [...fonts];
}
