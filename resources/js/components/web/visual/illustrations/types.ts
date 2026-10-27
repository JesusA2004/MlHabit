/**
 * Ilustraciones de línea de ML Habitat: alzados de muebles dibujados como
 * en un plano de taller. Mismo lienzo (160×120), mismo trazo, mismas tintas.
 *
 * - `lines`  trazos que se "dibujan" al revelarse (orden = orden de dibujo).
 * - `fills`  planos de color suaves que aparecen después del trazo.
 */
export type IllustrationTone =
    | 'ink'
    | 'muted'
    | 'oak'
    | 'eucalyptus'
    | 'stone'
    | 'linen'
    | 'espresso';

export type IllustrationDef = {
    title: string;
    fills: { d: string; tone: IllustrationTone; opacity?: number }[];
    lines: { d: string; tone?: IllustrationTone }[];
};

export type IllustrationName =
    | 'kitchen'
    | 'closet'
    | 'bedroom'
    | 'tv'
    | 'shoes'
    | 'tape'
    | 'blueprint'
    | 'toolbox';
