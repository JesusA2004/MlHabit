import { contrastRatio, resolveHex, TONES } from './color';
import { BP_ORDER, effectiveStyle } from './css';
import { nodeDef } from './schema';
import { nodeLabel } from './tree';
import type { StudioDocument, StudioNode, StudioResolved } from './types';

export interface CheckIssue {
    level: 'error' | 'warning';
    id: string;
    message: string;
}

/**
 * Revisión previa a publicar. Los errores bloquean la publicación; las
 * advertencias se muestran pero permiten continuar.
 */
export function runChecks(doc: StudioDocument, resolved: StudioResolved): CheckIssue[] {
    const issues: CheckIssue[] = [];
    const has = (id: unknown) => id !== null && id !== undefined && !!resolved.media[String(id)]?.url;

    if (doc.sections.filter((s) => !s.hidden).length === 0) {
        issues.push({ level: 'error', id: '', message: 'La página no tiene secciones visibles.' });
    }

    for (const section of doc.sections) {
        if (!nodeDef(section.type)) {
            issues.push({ level: 'error', id: section.id, message: `Sección no compatible «${section.type}»: elimínala.` });
            continue;
        }

        const tone = TONES[String(section.props.tone ?? 'ink')] ?? TONES.ink;
        const bg = String(section.style?.base?.sectionBackground ?? tone.bg);

        if (section.type === 'gallery') {
            const items = (section.props.items as { media: unknown }[]) ?? [];
            if (!section.hidden && items.filter((i) => has(i.media)).length === 0) {
                issues.push({ level: 'warning', id: section.id, message: 'La galería no tiene fotografías: no se mostrará.' });
            }
        }

        for (const child of Object.values(section.slots ?? {}).flat()) {
            checkElement(child, section, bg, has, issues);
        }
    }

    return issues;
}

function checkElement(
    node: StudioNode,
    section: StudioNode,
    bg: string,
    has: (id: unknown) => boolean,
    issues: CheckIssue[],
): void {
    const label = nodeLabel(node);
    if (!nodeDef(node.type)) {
        issues.push({ level: 'error', id: node.id, message: `Bloque no compatible «${node.type}»: elimínalo.` });

        return;
    }
    if (node.hidden || section.hidden) return;

    if (node.type === 'image' && node.props.bind !== 'cover') {
        if (!node.props.media) issues.push({ level: 'warning', id: node.id, message: `${label}: no tiene fotografía (no se mostrará).` });
        else if (!has(node.props.media)) issues.push({ level: 'error', id: node.id, message: `${label}: el archivo ya no existe o se está procesando.` });
        else if (!String(node.props.alt ?? '').trim()) issues.push({ level: 'warning', id: node.id, message: `${label}: falta el texto alternativo (accesibilidad y SEO).` });
    }

    if (node.type === 'video') {
        if (!node.props.media) issues.push({ level: 'warning', id: node.id, message: 'Video sin archivo (no se mostrará).' });
        else if (!has(node.props.media)) issues.push({ level: 'error', id: node.id, message: 'El video ya no existe o se está procesando.' });
    }

    if (node.type === 'heading' && node.props.bind !== 'title' && !String(node.props.text ?? '').trim()) {
        issues.push({ level: 'warning', id: node.id, message: 'Hay un título vacío.' });
    }

    // Contraste de colores de texto personalizados contra el fondo.
    for (const bp of BP_ORDER) {
        const color = effectiveStyle(node, 'color', bp).value;
        if (typeof color !== 'string') continue;
        const ownBg = effectiveStyle(node, 'background', bp).value;
        const back = typeof ownBg === 'string' ? ownBg : bg;
        const size = Number(effectiveStyle(node, 'fontSize', bp).value ?? 18);
        const ratio = contrastRatio(resolveHex(color), resolveHex(back));
        const min = size >= 24 ? 3 : 4.5;
        if (ratio < min) {
            issues.push({
                level: ratio < 2 ? 'error' : 'warning',
                id: node.id,
                message: `${label}: contraste ${ratio.toFixed(1)}:1 (mínimo ${min}:1). El texto puede ser ilegible.`,
            });
            break;
        }
    }
}
