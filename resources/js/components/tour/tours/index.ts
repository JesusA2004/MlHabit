import { normalizePath } from '@/lib/navigation';
import type {
    HelpModule,
    TourArea,
    TourContext,
    TourDefinition,
} from '../types';
import { adminGeneralTour, adminModules } from './admin';
import { portalDetailTours, portalGeneralTour, portalModules } from './portal';
import {
    technicianDetailTours,
    technicianGeneralTour,
    technicianModules,
} from './technician';

/**
 * Registro de recorridos por área. Todo lo que muestra el menú de ayuda y
 * el centro de ayuda sale de aquí, filtrado por los permisos de la persona.
 */
const generalTours: Record<TourArea, TourDefinition> = {
    admin: adminGeneralTour,
    portal: portalGeneralTour,
    tecnico: technicianGeneralTour,
};

const modulesByArea: Record<TourArea, HelpModule[]> = {
    admin: adminModules,
    portal: portalModules,
    tecnico: technicianModules,
};

const detailTours: Record<TourArea, TourDefinition[]> = {
    admin: adminModules.flatMap((m) => m.extraTours ?? []),
    portal: portalDetailTours,
    tecnico: technicianDetailTours,
};

export function generalTour(area: TourArea): TourDefinition {
    return generalTours[area];
}

export function helpModules(area: TourArea, ctx: TourContext): HelpModule[] {
    return modulesByArea[area].filter((m) => ctx.can(m.permission));
}

function allTours(area: TourArea): TourDefinition[] {
    return [
        // Primero los más específicos (detalle, editor, política…).
        ...detailTours[area],
        ...modulesByArea[area]
            .map((m) => m.tour)
            .filter((t): t is TourDefinition => !!t),
    ];
}

export function findTour(id: string): TourDefinition | null {
    for (const area of Object.keys(generalTours) as TourArea[]) {
        if (generalTours[area].id === id) {
            return generalTours[area];
        }

        const found = allTours(area).find((t) => t.id === id);

        if (found) {
            return found;
        }
    }

    return null;
}

/** El recorrido de la pantalla actual (o del módulo al que pertenece). */
export function sectionTourFor(
    area: TourArea,
    url: string,
    ctx: TourContext,
): TourDefinition | null {
    const path = normalizePath(url);
    const allowed = allTours(area).filter((t) => ctx.can(t.permission));
    const exactMatch = allowed.find((t) => t.match?.test(path));

    if (exactMatch) {
        return exactMatch;
    }

    // Una subpantalla sin recorrido propio usa el de su módulo.
    let best: { tour: TourDefinition; length: number } | null = null;

    for (const module of modulesByArea[area]) {
        if (!module.tour || !ctx.can(module.permission)) {
            continue;
        }

        const base = normalizePath(module.href);

        // La raíz del área (/admin, /portal, /tecnico) no cuenta como módulo padre.
        if (base.split('/').length <= 2) {
            continue;
        }

        if (
            path.startsWith(`${base}/`) &&
            (!best || base.length > best.length)
        ) {
            best = { tour: module.tour, length: base.length };
        }
    }

    return best?.tour ?? null;
}

export { adminModules, portalModules, technicianModules };
