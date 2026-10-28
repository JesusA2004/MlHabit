import type { InjectionKey } from 'vue';

export type PageWidth = 'narrow' | 'default' | 'wide' | 'fluid' | 'full';

/**
 * Contexto que un layout ofrece a sus `PageContainer` y `PageHeader`: el
 * ancho por defecto, si las pantallas entran con animación y si el
 * encabezado muestra el icono de la sección (solo el panel).
 */
export type PageContainerContext = {
    defaultWidth: PageWidth;
    animate: boolean;
    /** El encabezado toma el icono de la navegación del panel. */
    sectionIcons?: boolean;
};

export const pageContainerContextKey: InjectionKey<PageContainerContext> =
    Symbol('pageContainerContext');
