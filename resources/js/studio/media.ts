import type { StudioStore } from './store';
import { locate } from './tree';
import type { ResolvedMedia } from './types';

const RATIOS = ['1/1', '4/5', '3/4', '2/3', '4/3', '3/2', '16/9', '21/9', '9/16'];

/** Proporción de la lista permitida más cercana a la del archivo. */
export function nearestRatio(width: number, height: number): string {
    const target = width / height;

    return RATIOS.reduce((best, r) => {
        const [a, b] = r.split('/').map(Number);
        const [ba, bb] = best.split('/').map(Number);

        return Math.abs(a / b - target) < Math.abs(ba / bb - target) ? r : best;
    }, '1/1');
}

/**
 * Automatización al elegir una fotografía: si el bloque aún no tiene
 * proporción, se usa la más cercana a la del archivo (sin distorsión).
 */
export function suggestAspect(store: StudioStore, nodeId: string, media: ResolvedMedia): void {
    const node = locate(store.state.doc, nodeId)?.node;
    if (!node || node.type !== 'image' || node.style?.base?.aspectRatio || !media.width || !media.height) return;
    store.setStyle(nodeId, 'aspectRatio', nearestRatio(media.width, media.height), 'base', false);
}
