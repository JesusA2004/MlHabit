import type { InjectionKey } from 'vue';
import { inject } from 'vue';
import type { StudioStore } from '@/studio/store';

export interface EditorOptions {
    categories: { value: string; label: string }[];
    cmsPages: { value: string; label: string }[];
    social: string[];
    links: { value: string; label: string }[];
    currentCmsPage: string | null;
}

export interface EditorContext {
    store: StudioStore;
    options: EditorOptions;
    /** Abre el diálogo para agregar un bloque en un slot. */
    openAdd: (target: { sectionId: string; slot: string; index?: number } | null) => void;
}

export const EDITOR_CTX: InjectionKey<EditorContext> = Symbol('studio-editor');

export function useEditor(): EditorContext {
    const ctx = inject(EDITOR_CTX);
    if (!ctx) throw new Error('useEditor() fuera del editor de Studio');

    return ctx;
}
