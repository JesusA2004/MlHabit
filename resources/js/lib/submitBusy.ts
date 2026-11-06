import { router } from '@inertiajs/vue3';

/**
 * Indicador global para envíos de formulario de Inertia (POST/PUT/PATCH/
 * DELETE): si tardan más de `delay` ms, marca `<html data-submitting>`.
 * El CSS (app.css) cambia el cursor a "en progreso" y evita dobles envíos
 * bloqueando los botones `type="submit"` mientras tanto. Las pantallas con
 * acciones especialmente largas añaden además `<BusyOverlay>` vía
 * `useLongTask`.
 */
export function installSubmitBusy(delay = 400): void {
    if (typeof window === 'undefined') {
        return;
    }

    let pending = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    const root = document.documentElement;

    const tracked = (visit: {
        method: string;
        prefetch?: boolean;
        async?: boolean;
    }) => visit.method !== 'get' && !visit.prefetch && !visit.async;

    router.on('start', (event) => {
        if (!tracked(event.detail.visit)) {
            return;
        }

        pending += 1;
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (pending > 0) {
                root.dataset.submitting = 'true';
            }
        }, delay);
    });

    router.on('finish', (event) => {
        if (!tracked(event.detail.visit) || pending === 0) {
            return;
        }

        pending -= 1;

        if (pending === 0) {
            clearTimeout(timer);
            delete root.dataset.submitting;
        }
    });
}
