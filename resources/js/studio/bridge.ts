import type { StudioDocument, StudioResolved } from './types';

/**
 * Protocolo postMessage entre el editor (ventana padre) y el canvas
 * (iframe del mismo origen que renderiza la página real).
 */

export type ToCanvas =
    | { type: 'studio:render'; document: StudioDocument; resolved: StudioResolved; interactive: boolean; label: string | null }
    | { type: 'studio:select'; id: string | null; scroll: boolean }
    | { type: 'studio:replay'; id: string | null }
    | { type: 'studio:mode'; interact: boolean; spacing: boolean };

export type CanvasAction = 'select-parent' | 'up' | 'down' | 'duplicate' | 'hide' | 'delete' | 'media' | 'add-after';

export type FromCanvas =
    | { type: 'studio:ready' }
    | { type: 'studio:select'; id: string | null }
    | { type: 'studio:inline'; id: string; value: string }
    | { type: 'studio:add'; sectionId: string; slot: string }
    | { type: 'studio:action'; action: CanvasAction; id: string }
    | { type: 'studio:key'; key: string; ctrl: boolean; shift: boolean; alt: boolean };

export function isStudioMessage<T extends { type: string }>(event: MessageEvent): event is MessageEvent<T> {
    return event.origin === window.location.origin && typeof event.data?.type === 'string' && event.data.type.startsWith('studio:');
}
