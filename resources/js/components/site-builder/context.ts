import type { ComputedRef, InjectionKey } from 'vue';
import type {
    Block,
    BlockData,
    LibraryMap,
    SectionSettings,
    VideoData,
} from './types';
import type { ResponsiveImageData } from '@/types/web';

/** Datos de la sección que los bloques leen al renderizarse. */
export type SectionContext = {
    key: string;
    settings: SectionSettings;
    dark: boolean;
    /** Primera sección de la página: sus imágenes cargan con prioridad. */
    priority: boolean;
    images: Record<string, ResponsiveImageData | null>;
    videos: Record<string, VideoData>;
    data: Record<string, BlockData>;
};

export const sectionContextKey: InjectionKey<ComputedRef<SectionContext>> =
    Symbol('sb-section');

/**
 * Puente del editor: solo existe dentro del editor visual. En el sitio
 * público `inject(editorBridgeKey, null)` devuelve null y los bloques se
 * comportan como contenido estático.
 */
export type EditorBridge = {
    library: ComputedRef<LibraryMap>;
    isSelected: (sectionKey: string, blockId: string) => boolean;
    isPrimary: (sectionKey: string, blockId: string) => boolean;
    textEditing: ComputedRef<string | null>;
    cropping: ComputedRef<string | null>;
    canInteract: ComputedRef<boolean>;
    /** Desplazamiento/tamaño temporal mientras se arrastra (px del lienzo). */
    transient: (blockId: string) => {
        dx: number;
        dy: number;
        width: number | null;
        height: number | null;
    } | null;
    dropTarget: ComputedRef<string | null>;
    pointerDown: (
        event: PointerEvent,
        sectionKey: string,
        block: Block,
        el: HTMLElement,
    ) => void;
    resizeStart: (
        event: PointerEvent,
        sectionKey: string,
        block: Block,
        handle: string,
        el: HTMLElement,
    ) => void;
    doubleClick: (sectionKey: string, block: Block) => void;
    focusBlock: (sectionKey: string, block: Block, additive: boolean) => void;
    commitText: (
        sectionKey: string,
        blockId: string,
        prop: string,
        value: string,
    ) => void;
    endTextEdit: () => void;
    updateCrop: (
        sectionKey: string,
        blockId: string,
        patch: { focalX?: number; focalY?: number; zoom?: number },
        record: boolean,
    ) => void;
    endCrop: () => void;
    zoom: ComputedRef<number>;
};

export const editorBridgeKey: InjectionKey<EditorBridge> = Symbol('sb-editor');
