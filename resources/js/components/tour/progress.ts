import { ref } from 'vue';
import { apiFetch } from '@/lib/http';
import {
    destroy as toursDestroy,
    index as toursIndex,
    update as tourUpdate,
} from '@/routes/recorridos';
import type { TourProgressMap, TourStatus } from './types';

/**
 * Avance de los recorridos por persona. La fuente de verdad es el
 * servidor (`/recorridos`); localStorage es solo respaldo si la petición
 * falla (o el navegador lo bloquea: todo va en try/catch).
 */
const progress = ref<TourProgressMap>({});
const loaded = ref(false);
let loading: Promise<TourProgressMap> | null = null;
let loadedFor: number | null = null;

function storageKey(userId: number): string {
    return `mlhabitat.tours.${userId}`;
}

function readLocal(userId: number): TourProgressMap {
    try {
        const raw = window.localStorage.getItem(storageKey(userId));
        const parsed: unknown = raw ? JSON.parse(raw) : {};

        return parsed && typeof parsed === 'object'
            ? (parsed as TourProgressMap)
            : {};
    } catch {
        return {};
    }
}

function writeLocal(userId: number, value: TourProgressMap): void {
    try {
        window.localStorage.setItem(storageKey(userId), JSON.stringify(value));
    } catch {
        // Sin almacenamiento local (modo privado, bloqueado): no pasa nada.
    }
}

async function parse(response: Response): Promise<TourProgressMap | null> {
    if (!response.ok) {
        return null;
    }

    try {
        const data = (await response.json()) as { tours?: TourProgressMap };

        return data.tours && typeof data.tours === 'object' ? data.tours : {};
    } catch {
        return null;
    }
}

export function useTourProgress() {
    async function load(userId: number): Promise<TourProgressMap> {
        if (loadedFor === userId && loaded.value) {
            return progress.value;
        }

        if (loading && loadedFor === userId) {
            return loading;
        }

        loadedFor = userId;
        loading = (async () => {
            let remote: TourProgressMap | null = null;

            try {
                remote = await parse(await apiFetch(toursIndex().url));
            } catch {
                remote = null;
            }

            const local = readLocal(userId);

            // Lo que solo quedó guardado aquí (falló la red) se sube ahora.
            if (remote) {
                for (const [tour, state] of Object.entries(local)) {
                    if (!remote[tour] && state?.status) {
                        void apiFetch(tourUpdate(tour).url, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                status: state.status,
                                step: state.step ?? null,
                            }),
                        }).catch(() => undefined);
                    }
                }
            }

            // Lo del servidor manda; lo local cubre escrituras que fallaron.
            progress.value = { ...local, ...remote };
            writeLocal(userId, progress.value);
            loaded.value = true;

            return progress.value;
        })();

        try {
            return await loading;
        } finally {
            loading = null;
        }
    }

    async function mark(
        userId: number,
        tour: string,
        status: TourStatus,
        step: number | null = null,
    ): Promise<void> {
        progress.value = {
            ...progress.value,
            [tour]: { status, step, updatedAt: new Date().toISOString() },
        };
        writeLocal(userId, progress.value);

        try {
            const remote = await parse(
                await apiFetch(tourUpdate(tour).url, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status, step }),
                }),
            );

            if (remote) {
                progress.value = { ...progress.value, ...remote };
                writeLocal(userId, progress.value);
            }
        } catch {
            // Queda guardado localmente; se reintenta en la próxima carga.
        }
    }

    async function reset(userId: number): Promise<boolean> {
        try {
            const response = await apiFetch(toursDestroy().url, {
                method: 'DELETE',
            });

            if (!response.ok) {
                return false;
            }
        } catch {
            return false;
        }

        progress.value = {};
        writeLocal(userId, {});

        return true;
    }

    function statusOf(tour: string): TourStatus | null {
        return progress.value[tour]?.status ?? null;
    }

    return { progress, loaded, load, mark, reset, statusOf };
}
