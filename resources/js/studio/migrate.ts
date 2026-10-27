import { schema } from './schema';
import type { StudioDocument, StudioNode } from './types';

/**
 * Normaliza y migra documentos que llegan al editor.
 *
 * - PHP serializa los mapas vacíos como `[]`; aquí se convierten en `{}`
 *   para que asignar `style.base`, `props.x` o `slots.x` no se pierda.
 * - Migraciones por tipo (`v`): deben reflejar las de
 *   app/Studio/StudioMigrator.php. Hoy todos los tipos están en v1.
 */

type NodeMigration = (node: StudioNode) => StudioNode;

const NODE_MIGRATIONS: Record<string, Record<number, NodeMigration>> = {
    // hero: { 1: (node) => ({ ...node, v: 2 }) },
};

function asObject<T extends object>(value: unknown): T {
    return value && typeof value === 'object' && !Array.isArray(value) ? (value as T) : ({} as T);
}

function normalizeNode(raw: StudioNode): StudioNode {
    let node: StudioNode = {
        ...raw,
        props: asObject(raw.props),
        style: asObject(raw.style),
        motion: raw.motion && typeof raw.motion === 'object' && !Array.isArray(raw.motion) ? raw.motion : null,
        hidden: Boolean(raw.hidden),
    };
    for (const bp of Object.keys(node.style) as (keyof typeof node.style)[]) {
        node.style[bp] = asObject(node.style[bp]);
    }

    const def = schema.sections[node.type] ?? schema.elements[node.type];
    if (def) {
        const steps = NODE_MIGRATIONS[node.type] ?? {};
        let v = node.v ?? 1;
        while (v < def.version && steps[v]) {
            node = steps[v](node);
            v++;
        }
        node.v = v;
    }

    if (raw.slots !== undefined || schema.sections[node.type]) {
        const slots = asObject<Record<string, StudioNode[]>>(raw.slots);
        for (const slot of Object.keys(schema.sections[node.type]?.slots ?? {})) {
            slots[slot] = Array.isArray(slots[slot]) ? slots[slot].map(normalizeNode) : [];
        }
        node.slots = slots;
    }

    return node;
}

export function normalizeDocument(doc: StudioDocument): StudioDocument {
    return {
        schema: schema.schemaVersion,
        sections: Array.isArray(doc?.sections) ? doc.sections.map(normalizeNode) : [],
    };
}
