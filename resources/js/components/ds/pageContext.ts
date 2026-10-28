import type { InjectionKey } from 'vue';

export type PageWidth = 'narrow' | 'default' | 'wide' | 'fluid' | 'full';

/**
 * Contexto que un layout ofrece a sus `PageContainer`: el ancho por
 * defecto y si las pantallas entran con animación (solo el panel).
 */
export type PageContainerContext = {
    defaultWidth: PageWidth;
    animate: boolean;
};

export const pageContainerContextKey: InjectionKey<PageContainerContext> =
    Symbol('pageContainerContext');
