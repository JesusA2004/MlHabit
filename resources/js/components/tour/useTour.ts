import { router } from '@inertiajs/vue3';
import { computed, reactive, shallowRef } from 'vue';
import { normalizePath } from '@/lib/navigation';
import { useTourProgress } from './progress';
import { resolveTarget, waitForTarget } from './targets';
import type { TourContext, TourDefinition, TourStep } from './types';

/**
 * Motor del recorrido guiado (un solo recorrido a la vez, estado global).
 * Filtra los pasos por permiso, navega con Inertia cuando un paso vive en
 * otra pantalla, espera a que aparezca su elemento y, si no aparece, lo
 * omite (`optional`) o muestra la tarjeta centrada. Lo dibuja
 * `TourOverlay.vue`.
 */
type Phase = 'idle' | 'loading' | 'shown';

const state = reactive({
    tour: null as TourDefinition | null,
    steps: [] as TourStep[],
    index: 0,
    phase: 'idle' as Phase,
    userId: null as number | null,
});

/** Elemento resaltado del paso actual (null = tarjeta centrada). */
const target = shallowRef<HTMLElement | null>(null);

let ownNavigation = false;
let runId = 0;
let listening = false;

const progress = useTourProgress();

function currentPath(): string {
    return normalizePath(window.location.pathname);
}

function visit(url: string): Promise<boolean> {
    return new Promise((resolve) => {
        let ok = false;
        ownNavigation = true;
        router.visit(url, {
            preserveScroll: false,
            onSuccess: () => {
                ok = true;
            },
            onFinish: () => {
                // El evento `navigate` llega antes que `finish`.
                window.setTimeout(() => {
                    ownNavigation = false;
                }, 0);
                resolve(ok);
            },
        });
    });
}

function onRoute(route: string): boolean {
    return currentPath() === normalizePath(route);
}

async function show(index: number, direction: 1 | -1): Promise<void> {
    const run = ++runId;
    const step = state.steps[index];

    if (!step) {
        if (direction === 1) {
            await finish();
        } else {
            await show(0, 1);
        }

        return;
    }

    state.phase = 'loading';
    state.index = index;

    if (step.route && !onRoute(step.route)) {
        const ok = await visit(step.route);

        if (run !== runId) {
            return;
        }

        if (!ok) {
            // Sin acceso o error: pasa al siguiente.
            await show(index + direction, direction);

            return;
        }
    }

    const el = step.target
        ? await waitForTarget(step.target, step.route ? 3000 : 1200)
        : null;

    if (run !== runId || !state.tour) {
        return;
    }

    if (step.target && !el && step.optional) {
        await show(index + direction, direction);

        return;
    }

    target.value = el;
    state.phase = 'shown';
}

function filterSteps(tour: TourDefinition, ctx: TourContext): TourStep[] {
    return tour.steps.filter((step) => ctx.can(step.permission));
}

function listen(): void {
    if (listening || typeof window === 'undefined') {
        return;
    }

    listening = true;
    // Si la persona navega por su cuenta (atrás del navegador), se cierra.
    router.on('navigate', () => {
        if (state.tour && !ownNavigation) {
            stop();
        }
    });
}

async function start(
    tour: TourDefinition,
    ctx: TourContext,
    options: { at?: number } = {},
): Promise<void> {
    listen();

    const steps = filterSteps(tour, ctx);

    if (!steps.length) {
        return;
    }

    // Si se inicia desde otra pantalla, el primer paso vive en la suya.
    const path = currentPath();
    const inPlace = tour.match
        ? tour.match.test(path)
        : tour.startRoute
          ? onRoute(tour.startRoute)
          : true;

    if (!inPlace && tour.startRoute && !steps[0].route) {
        steps[0] = { ...steps[0], route: tour.startRoute };
    }

    state.tour = tour;
    state.steps = steps;
    state.userId = ctx.user?.id ?? null;
    target.value = null;
    await show(Math.min(options.at ?? 0, steps.length - 1), 1);
}

function next(): void {
    if (!state.tour || state.phase !== 'shown') {
        return;
    }

    void show(state.index + 1, 1);
}

function prev(): void {
    if (!state.tour || state.phase !== 'shown' || state.index === 0) {
        return;
    }

    void show(state.index - 1, -1);
}

function reset(): void {
    runId++;
    state.tour = null;
    state.steps = [];
    state.index = 0;
    state.phase = 'idle';
    target.value = null;
}

/** Cierra sin guardar nada (p. ej. al navegar fuera). */
function stop(): void {
    reset();
}

async function finish(): Promise<void> {
    const tour = state.tour;
    const userId = state.userId;
    const step = state.index;
    reset();

    if (tour && userId) {
        await progress.mark(userId, tour.id, 'completed', step);
    }
}

/** "Saltar": se recuerda como omitido para no volver a ofrecerlo. */
async function skip(): Promise<void> {
    const tour = state.tour;
    const userId = state.userId;
    const step = state.index;
    reset();

    if (tour && userId) {
        await progress.mark(userId, tour.id, 'dismissed', step);
    }
}

/** Vuelve a buscar el elemento (si el DOM cambió y se perdió). */
function refreshTarget(): void {
    const step = state.steps[state.index];

    if (!step?.target || (target.value && target.value.isConnected)) {
        return;
    }

    target.value = resolveTarget(step.target);
}

export function useTour() {
    return {
        state,
        target,
        active: computed(() => state.tour !== null),
        step: computed(() => state.steps[state.index] ?? null),
        total: computed(() => state.steps.length),
        isFirst: computed(() => state.index === 0),
        isLast: computed(() => state.index === state.steps.length - 1),
        start,
        next,
        prev,
        skip,
        finish,
        stop,
        refreshTarget,
    };
}
