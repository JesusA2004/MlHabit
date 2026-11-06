import { ref } from 'vue';
import { apiFetch, xsrfToken } from '@/lib/http';
import { createRateMeter, etaSeconds } from '@/lib/transferRate';
import { trackUpload } from '@/lib/uploadActivity';
import subidas from '@/routes/subidas';

/**
 * Endpoints de sesiones de subida: /subidas (módulo web). Cualquier usuario
 * activo puede subir archivos privados; los públicos requieren media.manage.
 */
export interface UploadedMediaResult {
    id: number;
    uuid?: string;
    visibility?: 'public' | 'private';
    filename: string;
    type: string;
    url: string | null;
    status: string;
}

/** Opciones extra que viajan en el payload de inicio de sesión de subida. */
export interface ChunkedUploadOptions {
    visibility?: 'public' | 'private';
}

/**
 * Fase de la subida: `preparing` (huella SHA-256 del archivo), `uploading`
 * (enviando fragmentos), `finalizing` (el servidor ensambla y valida) y
 * `completed`.
 */
export type ChunkedUploadPhase =
    | 'preparing'
    | 'uploading'
    | 'finalizing'
    | 'completed';

export interface ChunkedUploadProgress {
    file: File;
    sentBytes: number;
    totalBytes: number;
    percent: number;
    status: 'uploading' | 'completed' | 'error' | 'aborted';
    phase?: ChunkedUploadPhase;
    /** Velocidad estimada en bytes/s (0 mientras no hay datos). */
    bytesPerSecond?: number;
    /** Segundos restantes estimados. */
    etaSeconds?: number | null;
}

export type MediaProcessingState = {
    status: string;
    url: string | null;
    posterUrl: string | null;
};

const RESUME_STORAGE_KEY = 'mlhabitat.chunked-uploads';
// Por encima de este tamaño no se calcula el checksum SHA-256 en el
// navegador: leer el archivo completo a memoria para hashear deja de ser
// razonable y el tamaño exacto (verificado en el servidor) ya protege la
// integridad de la subida.
const MAX_CHECKSUM_BYTES = 500 * 1024 * 1024;

interface ResumeEntry {
    uuid: string;
    fileName: string;
    fileSize: number;
    lastModified: number;
    chunkSize: number;
}

function readResumeMap(): Record<string, ResumeEntry> {
    try {
        return JSON.parse(localStorage.getItem(RESUME_STORAGE_KEY) ?? '{}');
    } catch {
        return {};
    }
}

function writeResumeMap(map: Record<string, ResumeEntry>) {
    try {
        localStorage.setItem(RESUME_STORAGE_KEY, JSON.stringify(map));
    } catch {
        // Sin almacenamiento local (modo privado): no se puede reanudar.
    }
}

function fingerprint(file: File): string {
    return `${file.name}:${file.size}:${file.lastModified}`;
}

function rememberSession(file: File, entry: ResumeEntry) {
    const map = readResumeMap();
    map[fingerprint(file)] = entry;
    writeResumeMap(map);
}

function forgetSession(file: File) {
    const map = readResumeMap();
    delete map[fingerprint(file)];
    writeResumeMap(map);
}

async function computeChecksum(file: File): Promise<string | null> {
    if (file.size > MAX_CHECKSUM_BYTES || !window.crypto?.subtle) return null;

    const buffer = await file.arrayBuffer();
    const digest = await window.crypto.subtle.digest('SHA-256', buffer);

    return Array.from(new Uint8Array(digest))
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('');
}

/**
 * Espera a que el servidor termine de procesar un archivo (p. ej. un video
 * mientras FFmpeg genera la miniatura) consultando su estado cada pocos
 * segundos. Resuelve con el último estado (`ready` / `failed`) o con `null`
 * si se agotó el tiempo, se canceló con `signal` o no aplica.
 */
export async function waitForMediaProcessing(
    media: Pick<UploadedMediaResult, 'uuid' | 'status'>,
    options: {
        signal?: AbortSignal;
        intervalMs?: number;
        timeoutMs?: number;
    } = {},
): Promise<MediaProcessingState | null> {
    if (!media.uuid || media.status !== 'processing') {
        return null;
    }

    const interval = options.intervalMs ?? 2500;
    const deadline = Date.now() + (options.timeoutMs ?? 15 * 60 * 1000);

    while (Date.now() < deadline && !options.signal?.aborted) {
        await new Promise((resolve) => setTimeout(resolve, interval));

        if (options.signal?.aborted) {
            return null;
        }

        try {
            const response = await apiFetch(subidas.estado(media.uuid).url);

            if (!response.ok) {
                return null;
            }

            const state = (await response.json()) as MediaProcessingState;

            if (state.status !== 'processing') {
                return state;
            }
        } catch {
            // Error de red pasajero: se reintenta en el siguiente ciclo.
        }
    }

    return null;
}

type ChunkResponse = {
    ok: boolean;
    status: number;
    body: {
        message?: string;
        receivedSize?: number;
        nextChunkIndex?: number;
        progress?: number;
    } | null;
};

/**
 * Envía un fragmento con XMLHttpRequest para tener progreso real dentro del
 * fragmento (fetch no expone el avance de la subida).
 */
function sendChunk(
    url: string,
    data: FormData,
    onProgress: (loaded: number) => void,
    register: (xhr: XMLHttpRequest | null) => void,
): Promise<ChunkResponse> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        register(xhr);
        xhr.open('POST', url);
        xhr.withCredentials = true;
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken());
        xhr.upload.onprogress = (event) => onProgress(event.loaded);
        xhr.onload = () => {
            register(null);
            let body: ChunkResponse['body'] = null;

            try {
                body = JSON.parse(xhr.responseText);
            } catch {
                body = null;
            }

            resolve({
                ok: xhr.status >= 200 && xhr.status < 300,
                status: xhr.status,
                body,
            });
        };
        xhr.onerror = () => {
            register(null);
            reject(new Error('Se perdió la conexión al subir el archivo.'));
        };
        xhr.onabort = () => {
            register(null);
            reject(new Error('Subida cancelada.'));
        };
        xhr.send(data);
    });
}

/**
 * Sube un archivo grande en fragmentos sucesivos, cada uno su propia
 * petición HTTP corta (no depende de una única request PHP de larga
 * duración). El progreso reportado es real: bytes enviados dentro del
 * fragmento en curso y bytes confirmados por el servidor al terminar cada
 * uno, con velocidad y tiempo restante estimados.
 *
 * Si la página se recarga a mitad de una subida, al volver a llamar a
 * `upload()` con el mismo archivo se detecta la sesión pendiente guardada
 * en localStorage y se reanuda desde el fragmento que el servidor confirme
 * como siguiente, en vez de reenviar bytes ya recibidos.
 *
 * Mientras sube, la subida cuenta en `lib/uploadActivity` para que
 * `UploadGuard` advierta antes de salir de la página.
 */
export function useChunkedUpload() {
    const progress = ref<ChunkedUploadProgress | null>(null);
    let aborted = false;
    let currentUuid: string | null = null;
    let inflight: XMLHttpRequest | null = null;
    const meter = createRateMeter();

    function report(
        sentBytes: number,
        extra: Partial<ChunkedUploadProgress> = {},
    ) {
        const current = progress.value!;
        const total = current.totalBytes;
        const rate = meter.sample(sentBytes);
        progress.value = {
            ...current,
            ...extra,
            sentBytes,
            percent:
                extra.percent ??
                (total > 0 ? Math.min(100, (sentBytes / total) * 100) : 0),
            bytesPerSecond: rate,
            etaSeconds: etaSeconds(sentBytes, total, rate),
        };
    }

    async function upload(
        file: File,
        collectionPath?: string,
        options: ChunkedUploadOptions = {},
    ): Promise<UploadedMediaResult> {
        const release = trackUpload();

        try {
            return await run(file, collectionPath, options);
        } finally {
            release();
        }
    }

    async function run(
        file: File,
        collectionPath?: string,
        options: ChunkedUploadOptions = {},
    ): Promise<UploadedMediaResult> {
        aborted = false;
        meter.reset();
        progress.value = {
            file,
            sentBytes: 0,
            totalBytes: file.size,
            percent: 0,
            status: 'uploading',
            phase: 'preparing',
            bytesPerSecond: 0,
            etaSeconds: null,
        };

        let uuid: string;
        let chunkSize: number;
        let startChunkIndex = 0;

        const resumeMap = readResumeMap();
        const existing = resumeMap[fingerprint(file)];

        if (existing) {
            const statusResponse = await apiFetch(
                subidas.show(existing.uuid).url,
            );
            if (statusResponse.ok) {
                const state = await statusResponse.json();
                if (
                    state.status === 'uploading' &&
                    state.totalSize === file.size
                ) {
                    uuid = existing.uuid;
                    chunkSize = existing.chunkSize;
                    startChunkIndex = state.nextChunkIndex;
                    progress.value = {
                        ...progress.value,
                        sentBytes: state.receivedSize,
                        percent: state.progress,
                    };
                } else {
                    forgetSession(file);
                    ({ uuid, chunkSize } = await startSession(
                        file,
                        collectionPath,
                        options,
                    ));
                }
            } else {
                forgetSession(file);
                ({ uuid, chunkSize } = await startSession(
                    file,
                    collectionPath,
                    options,
                ));
            }
        } else {
            ({ uuid, chunkSize } = await startSession(
                file,
                collectionPath,
                options,
            ));
        }

        currentUuid = uuid;
        rememberSession(file, {
            uuid,
            fileName: file.name,
            fileSize: file.size,
            lastModified: file.lastModified,
            chunkSize,
        });

        let chunkIndex = startChunkIndex;
        let offset = startChunkIndex * chunkSize;
        progress.value = { ...progress.value!, phase: 'uploading' };
        meter.sample(offset);

        while (offset < file.size) {
            if (aborted) {
                progress.value = { ...progress.value!, status: 'aborted' };
                throw new Error('Subida cancelada.');
            }

            const chunk = file.slice(offset, offset + chunkSize);
            const formData = new FormData();
            formData.append('chunk', chunk, file.name);
            formData.append('chunkIndex', String(chunkIndex));

            const base = offset;
            let chunkResponse: ChunkResponse;

            try {
                chunkResponse = await sendChunk(
                    subidas.fragmentos(uuid).url,
                    formData,
                    (loaded) => report(Math.min(base + loaded, file.size)),
                    (xhr) => (inflight = xhr),
                );
            } catch (error) {
                progress.value = {
                    ...progress.value!,
                    status: aborted ? 'aborted' : 'error',
                };
                throw error;
            }

            if (!chunkResponse.ok || !chunkResponse.body) {
                progress.value = { ...progress.value!, status: 'error' };
                throw new Error(
                    chunkResponse.body?.message ??
                        'Falló la subida de un fragmento.',
                );
            }

            const result = chunkResponse.body;
            offset += chunk.size;
            chunkIndex = result.nextChunkIndex ?? chunkIndex + 1;
            report(result.receivedSize ?? offset, {
                percent: result.progress,
            });
        }

        progress.value = {
            ...progress.value!,
            phase: 'finalizing',
            percent: 100,
            etaSeconds: null,
        };

        const completeResponse = await apiFetch(subidas.completar(uuid).url, {
            method: 'POST',
        });
        if (!completeResponse.ok) {
            const body = await completeResponse.json().catch(() => null);
            progress.value = { ...progress.value!, status: 'error' };
            throw new Error(body?.message ?? 'No se pudo finalizar la subida.');
        }

        forgetSession(file);
        const { media } = await completeResponse.json();
        progress.value = {
            ...progress.value!,
            percent: 100,
            status: 'completed',
            phase: 'completed',
        };

        return media;
    }

    async function startSession(
        file: File,
        collectionPath?: string,
        options: ChunkedUploadOptions = {},
    ): Promise<{ uuid: string; chunkSize: number }> {
        const checksumSha256 = await computeChecksum(file);

        const startResponse = await apiFetch(subidas.store().url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                filename: file.name,
                mimeType: file.type || 'application/octet-stream',
                totalSize: file.size,
                collection: collectionPath,
                visibility: options.visibility,
                checksumSha256,
            }),
        });

        if (!startResponse.ok) {
            const body = await startResponse.json().catch(() => null);
            progress.value = { ...progress.value!, status: 'error' };
            throw new Error(body?.message ?? 'No se pudo iniciar la subida.');
        }

        return startResponse.json();
    }

    /** Detiene la transferencia en curso y borra el archivo temporal del servidor. */
    async function cancel(file?: File) {
        aborted = true;
        inflight?.abort();
        if (currentUuid) {
            await apiFetch(subidas.destroy(currentUuid).url, {
                method: 'DELETE',
            }).catch(() => undefined);
        }
        if (file) forgetSession(file);
    }

    return { progress, upload, cancel };
}
