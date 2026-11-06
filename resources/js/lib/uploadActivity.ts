import { computed, ref } from 'vue';

/**
 * Registro global de subidas en curso. `useChunkedUpload` avisa aquí cuando
 * empieza y termina cada archivo; `UploadGuard` (montado en los layouts del
 * panel, portal y técnicos) lo usa para advertir antes de salir de la
 * página o navegar con una subida a medias.
 */
const active = ref(0);

export const uploadsInProgress = computed(() => active.value);
export const hasActiveUploads = computed(() => active.value > 0);

/** Marca una subida como activa; devuelve la función para liberarla (idempotente). */
export function trackUpload(): () => void {
    active.value += 1;
    let released = false;

    return () => {
        if (!released) {
            released = true;
            active.value = Math.max(0, active.value - 1);
        }
    };
}
