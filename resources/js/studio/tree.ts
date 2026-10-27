import {
    activeSlots,
    defaultProps,
    elementDef,
    propDefsFor,
    schema,
    sectionDef,
} from './schema';
import type { NodeLocation, StudioDocument, StudioNode } from './types';

/** Operaciones puras sobre el árbol de un documento de Studio. */

export function newId(): string {
    const bytes = new Uint8Array(4);
    crypto.getRandomValues(bytes);

    return `n_${Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')}`;
}

export function locate(doc: StudioDocument, id: string): NodeLocation | null {
    for (let i = 0; i < doc.sections.length; i++) {
        const section = doc.sections[i];
        if (section.id === id) {
            return { node: section, list: doc.sections, index: i, section, slot: null };
        }
        for (const [slot, children] of Object.entries(section.slots ?? {})) {
            const index = children.findIndex((child) => child.id === id);
            if (index !== -1) {
                return { node: children[index], list: children, index, section, slot };
            }
        }
    }

    return null;
}

export function walk(doc: StudioDocument, visit: (node: StudioNode, depth: number) => void): void {
    for (const section of doc.sections) {
        visit(section, 0);
        for (const children of Object.values(section.slots ?? {})) {
            children.forEach((child) => visit(child, 1));
        }
    }
}

/** Copia profunda con identificadores nuevos (para duplicar). */
export function cloneWithNewIds(node: StudioNode): StudioNode {
    // JSON (no structuredClone): el nodo puede ser un proxy reactivo de Vue.
    const copy = JSON.parse(JSON.stringify(node)) as StudioNode;
    copy.id = newId();
    if (copy.slots) {
        for (const slot of Object.keys(copy.slots)) {
            copy.slots[slot] = copy.slots[slot].map(cloneWithNewIds);
        }
    }

    return copy;
}

export function createElement(type: string, props: Record<string, unknown> = {}): StudioNode {
    const def = elementDef(type);

    return {
        id: newId(),
        type,
        v: def?.version ?? 1,
        props: { ...defaultProps(propDefsFor(type)), ...props },
        style: {},
        motion: null,
        hidden: false,
    };
}

export function createSection(
    type: string,
    props: Record<string, unknown> = {},
    slots: Record<string, StudioNode[]> = {},
): StudioNode {
    const def = sectionDef(type);
    const allSlots: Record<string, StudioNode[]> = {};
    for (const slot of Object.keys(def?.slots ?? {})) {
        allSlots[slot] = slots[slot] ?? [];
    }

    return {
        id: newId(),
        type,
        v: def?.version ?? 1,
        props: {
            ...defaultProps(propDefsFor(type)),
            ...(def?.defaults ?? {}),
            ...props,
        },
        style: {},
        motion: null,
        hidden: false,
        slots: allSlots,
    };
}

/**
 * Cambia la composición de una sección conservando los bloques: los que
 * quedan en slots que la nueva composición no usa pasan al último slot
 * visible, para que cambiar de 3 a 1 columna nunca borre contenido.
 */
export function applyLayout(section: StudioNode, layout: string): void {
    section.props.layout = layout;
    const visible = activeSlots(section);
    if (!section.slots || visible.length === 0) return;

    const target = visible[visible.length - 1];
    for (const slot of Object.keys(section.slots)) {
        if (!visible.includes(slot) && section.slots[slot].length) {
            section.slots[target].push(...section.slots[slot]);
            section.slots[slot] = [];
        }
    }
}

export function countNodes(doc: StudioDocument): number {
    let n = 0;
    walk(doc, () => n++);

    return n;
}

export function nodeLabel(node: StudioNode): string {
    const def = schema.sections[node.type] ?? schema.elements[node.type];
    const base = def?.label ?? `Bloque no compatible (${node.type})`;
    const text = typeof node.props.text === 'string' ? node.props.text : typeof node.props.title === 'string' ? node.props.title : '';
    const snippet = text.replace(/\s+/g, ' ').trim();

    return snippet ? `${base} · ${snippet.slice(0, 32)}${snippet.length > 32 ? '…' : ''}` : base;
}
