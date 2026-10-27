import raw from './schema.json';
import type { Breakpoint } from './types';

/**
 * Acceso tipado a schema.json — la única fuente de verdad de tipos de
 * bloque, propiedades, rangos y grupos (también la lee el backend en
 * app/Studio/StudioSchema.php).
 */

export type PropKind =
    | 'string'
    | 'text'
    | 'enum'
    | 'layout'
    | 'bool'
    | 'int'
    | 'number'
    | 'media'
    | 'category'
    | 'page'
    | 'link'
    | 'list';

export interface PropDef {
    kind: PropKind;
    label: string;
    default?: unknown;
    max?: number;
    min?: number;
    step?: number;
    options?: Record<string, string>;
    mediaType?: 'image' | 'video' | 'captions';
    item?: Record<string, PropDef>;
    itemLabel?: string;
    inline?: boolean;
    nullable?: boolean;
    pattern?: string;
    tab?: 'content' | 'advanced';
    showWhen?: Record<string, string[]>;
}

export type StyleKind = 'number' | 'enum' | 'bool' | 'color' | 'font';

export interface StyleDef {
    kind: StyleKind;
    label: string;
    group: string;
    min?: number;
    max?: number;
    step?: number;
    unit?: string;
    options?: Record<string, string>;
    zeroLabel?: string;
}

export interface SlotDef {
    label: string;
    accepts: string[] | '*elements';
}

export interface LayoutDef {
    label: string;
    slots: string[];
}

export interface SectionDef {
    label: string;
    icon: string;
    version: number;
    description: string;
    slots: Record<string, SlotDef>;
    layouts: Record<string, LayoutDef>;
    props: Record<string, PropDef>;
    styleGroups: string[];
    defaults: Record<string, unknown>;
}

export interface ElementDef {
    label: string;
    icon: string;
    version: number;
    props: Record<string, PropDef>;
    styleGroups: string[];
}

interface Schema {
    schemaVersion: number;
    limits: { maxSections: number; maxElementsPerSlot: number; maxNodes: number };
    breakpoints: Record<Breakpoint, { label: string; preview: number; max: number | null }>;
    colorTokens: Record<string, string>;
    fonts: Record<string, { label: string; theme?: boolean; fallback?: string }>;
    styleProps: Record<string, StyleDef>;
    styleTabs: Record<string, 'style' | 'layout' | 'responsive'>;
    motion: {
        presets: Record<string, string>;
        fields: Record<'duration' | 'delay' | 'distance', { min: number; max: number; step: number; default: number }>;
        triggers: Record<string, string>;
        mobile: Record<string, string>;
    };
    sharedSectionProps: Record<string, PropDef>;
    sections: Record<string, SectionDef>;
    elements: Record<string, ElementDef>;
}

export const schema = raw as unknown as Schema;

export const BREAKPOINTS: Breakpoint[] = ['base', 'laptop', 'tablet', 'mobile'];

export function isSectionType(type: string): boolean {
    return type in schema.sections;
}

export function sectionDef(type: string): SectionDef | undefined {
    return schema.sections[type];
}

export function elementDef(type: string): ElementDef | undefined {
    return schema.elements[type];
}

export function nodeDef(type: string): SectionDef | ElementDef | undefined {
    return schema.sections[type] ?? schema.elements[type];
}

export function propDefsFor(type: string): Record<string, PropDef> {
    const section = schema.sections[type];
    if (section) return { ...schema.sharedSectionProps, ...section.props };

    return schema.elements[type]?.props ?? {};
}

export function styleDefsFor(type: string): Record<string, StyleDef> {
    const groups = nodeDef(type)?.styleGroups ?? [];

    return Object.fromEntries(
        Object.entries(schema.styleProps).filter(([, def]) =>
            groups.includes(def.group),
        ),
    );
}

export function slotAccepts(sectionType: string, slot: string): string[] {
    const accepts = schema.sections[sectionType]?.slots[slot]?.accepts ?? [];

    return accepts === '*elements' ? Object.keys(schema.elements) : accepts;
}

export function activeSlots(section: { type: string; props: Record<string, unknown> }): string[] {
    const def = schema.sections[section.type];
    if (!def) return [];
    const layout = def.layouts[String(section.props.layout)];

    return layout?.slots ?? Object.keys(def.slots);
}

/** ¿Debe mostrarse una propiedad según `showWhen`? */
export function propVisible(def: PropDef, values: Record<string, unknown>): boolean {
    if (!def.showWhen) return true;

    return Object.entries(def.showWhen).every(([key, allowed]) =>
        allowed.includes(String(values[key])),
    );
}

export function defaultProps(defs: Record<string, PropDef>): Record<string, unknown> {
    return Object.fromEntries(
        Object.entries(defs).map(([key, def]) => [
            key,
            structuredClone(def.default ?? (def.kind === 'list' ? [] : null)),
        ]),
    );
}
