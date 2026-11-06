import type { VisitOptions } from '@inertiajs/core';
import { computed, onScopeDispose, ref } from 'vue';

export type LongTaskOptions = {
    /** Milisegundos antes de mostrar la capa (evita parpadeos en acciones rápidas). */
    delay?: number;
    /** Tiempo mínimo visible una vez mostrada, para que no "destelle". */
    minVisible?: number;
};

type LongTaskMessage = { title?: string; description?: string };

/**
 * Estado de una acción larga para `<BusyOverlay>`:
 *
 * ```ts
 * const task = useLongTask();
 * form.put(url, task.visit({ title: 'Guardando…' }, { onSuccess }));
 * await task.run(() => fetchAlgo(), { title: 'Probando conexión…' });
 * ```
 * ```vue
 * <BusyOverlay :open="task.visible.value" :title="task.title.value" … />
 * ```
 *
 * `running` es verdadero de inmediato (para deshabilitar botones);
 * `visible` solo si la acción tarda más de `delay` (400 ms por defecto).
 */
export function useLongTask(options: LongTaskOptions = {}) {
    const delay = options.delay ?? 400;
    const minVisible = options.minVisible ?? 450;

    const running = ref(false);
    const visible = ref(false);
    const title = ref('Un momento…');
    const description = ref<string | undefined>(undefined);
    const progress = ref<number | null>(null);
    const detail = ref<string | undefined>(undefined);

    let showTimer: ReturnType<typeof setTimeout> | undefined;
    let hideTimer: ReturnType<typeof setTimeout> | undefined;
    let shownAt = 0;

    function start(message: LongTaskMessage = {}): void {
        clearTimeout(hideTimer);
        title.value = message.title ?? 'Un momento…';
        description.value = message.description;
        progress.value = null;
        detail.value = undefined;
        running.value = true;

        if (!visible.value) {
            clearTimeout(showTimer);
            showTimer = setTimeout(() => {
                if (running.value) {
                    visible.value = true;
                    shownAt = Date.now();
                }
            }, delay);
        }
    }

    function finish(): void {
        running.value = false;
        clearTimeout(showTimer);

        if (!visible.value) {
            return;
        }

        const remaining = Math.max(0, minVisible - (Date.now() - shownAt));
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            if (!running.value) {
                visible.value = false;
            }
        }, remaining);
    }

    /** Ejecuta una promesa mostrando la capa si tarda. */
    async function run<T>(
        fn: () => Promise<T>,
        message: LongTaskMessage = {},
    ): Promise<T> {
        start(message);

        try {
            return await fn();
        } finally {
            finish();
        }
    }

    /**
     * Opciones de visita de Inertia (router.post / form.put…) que encienden y
     * apagan la capa; encadena los `onStart` / `onFinish` que pases.
     */
    function visit<T extends Partial<VisitOptions>>(
        message: LongTaskMessage = {},
        extra?: T,
    ): T {
        const base = (extra ?? {}) as Partial<VisitOptions>;

        return {
            ...base,
            onStart: (
                event: Parameters<NonNullable<VisitOptions['onStart']>>[0],
            ) => {
                start(message);
                base.onStart?.(event);
            },
            onProgress: (
                event: Parameters<NonNullable<VisitOptions['onProgress']>>[0],
            ) => {
                if (event?.percentage !== undefined) {
                    progress.value = event.percentage;
                }

                base.onProgress?.(event);
            },
            onFinish: (
                event: Parameters<NonNullable<VisitOptions['onFinish']>>[0],
            ) => {
                finish();
                base.onFinish?.(event);
            },
        } as T;
    }

    onScopeDispose(() => {
        clearTimeout(showTimer);
        clearTimeout(hideTimer);
    });

    return {
        running: computed(() => running.value),
        visible: computed(() => visible.value),
        title,
        description,
        progress,
        detail,
        start,
        finish,
        run,
        visit,
    };
}
