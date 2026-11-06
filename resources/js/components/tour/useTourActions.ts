import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePermissions } from '@/lib/permissions';
import { findTour, generalTour, helpModules, sectionTourFor } from './tours';
import type { TourArea, TourContext, TourDefinition } from './types';
import { useTour } from './useTour';

/**
 * Lo que necesitan el botón de ayuda, el aviso de bienvenida y los centros
 * de ayuda: contexto de permisos y atajos para iniciar recorridos.
 */
export function useTourActions(area: TourArea) {
    const page = usePage();
    const { user, can, canAny } = usePermissions();
    const tour = useTour();

    const ctx = computed<TourContext>(() => ({
        user: user.value,
        can: (permission?: string | string[]) =>
            !permission ||
            (Array.isArray(permission) ? canAny(permission) : can(permission)),
    }));

    const general = computed(() => generalTour(area));
    const section = computed(() => sectionTourFor(area, page.url, ctx.value));
    const modules = computed(() => helpModules(area, ctx.value));

    function start(definition: TourDefinition | null): void {
        if (definition) {
            void tour.start(definition, ctx.value);
        }
    }

    return {
        ctx,
        general,
        section,
        modules,
        start,
        startGeneral: () => start(general.value),
        startSection: () => start(section.value),
        startById: (id: string) => start(findTour(id)),
    };
}
