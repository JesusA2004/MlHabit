import type { Component } from 'vue';
import type { User } from '@/types/auth';

/** Dónde vive el recorrido: panel, portal del cliente o app de técnicos. */
export type TourArea = 'admin' | 'portal' | 'tecnico';

export type TourPlacement = 'auto' | 'top' | 'bottom' | 'left' | 'right';

/**
 * Elemento a resaltar. Formatos aceptados (ver `targets.ts`):
 * - `"page-header"` → `[data-tour="page-header"]`
 * - `"card:Agenda de hoy"` → tarjeta (SectionCard) con ese título
 * - `"tab:Pagos"` → pestaña con ese texto
 * - `"action:Nueva visita"` → botón o enlace con ese texto (primero en las
 *   acciones del encabezado, luego en el contenido)
 * - `"css:#contenido"` (o cualquier texto que empiece con `#`, `.` o `[`)
 *
 * Con una lista se usa el primero que esté visible (p. ej. el elemento del
 * menú lateral en escritorio y el encabezado de la pantalla en móvil).
 */
export type TourTarget = string | string[];

export type TourStep = {
    /** Elemento a resaltar; sin él, la tarjeta se muestra centrada. */
    target?: TourTarget;
    title: string;
    body: string;
    /** Ruta en la que vive el paso; el recorrido navega a ella si hace falta. */
    route?: string;
    placement?: TourPlacement;
    /** Solo se muestra si la persona tiene este permiso (o alguno de la lista). */
    permission?: string | string[];
    /** Si el elemento no aparece, se omite el paso (en lugar de centrarlo). */
    optional?: boolean;
    /** Margen alrededor del resaltado, en px. */
    padding?: number;
};

export type TourDefinition = {
    /** Clave estable (se guarda el avance con ella): `admin.prospectos`. */
    id: string;
    area: TourArea;
    title: string;
    /** Resumen de una línea para el menú y el centro de ayuda. */
    description?: string;
    /** Pantalla donde empieza (se navega a ella al iniciar desde otra). */
    startRoute?: string;
    /** Rutas donde este es "el recorrido de esta sección". */
    match?: RegExp;
    permission?: string | string[];
    steps: TourStep[];
};

/** Ficha del centro de ayuda: una por módulo, con su recorrido. */
export type HelpModule = {
    id: string;
    area: TourArea;
    group: string;
    title: string;
    href: string;
    icon?: Component;
    permission?: string | string[];
    /** Qué es, en una línea. */
    summary: string;
    /** ¿Para qué sirve? */
    purpose: string;
    /** Pasos clave del flujo típico. */
    keySteps: string[];
    /** Recorrido de la pantalla principal del módulo. */
    tour?: TourDefinition;
    /** Recorridos extra (p. ej. el detalle de un registro). */
    extraTours?: TourDefinition[];
};

export type TourContext = {
    user: User | null;
    can: (permission?: string | string[]) => boolean;
};

export type TourStatus = 'completed' | 'dismissed';

export type TourProgressMap = Record<
    string,
    { status: TourStatus; step: number | null; updatedAt: string | null }
>;
