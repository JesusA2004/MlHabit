import { ref } from 'vue';
import { apiFetch } from '@/lib/http';
import multimediaSesiones from '@/routes/admin/multimedia-sesiones';

export interface UploadedMediaResult {
    id: number;
    filename: string;
    type: string;
    url: string | null;
    status: string;
}

export interface ChunkedUploadProgress {
    file: File;
    sentBytes: number;
    totalBytes: number;
    percent: number;
    status: 'uploading' | 'completed' | 'error' | 'aborted';
}

const RESUME_STORAGE_KEY = 'mrinsight.chunked-uploads';
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
    localStorage.setItem(RESUME_STORAGE_KEY, JSON.stringify(map));
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
 * Sube un archivo grande en fragmentos sucesivos, cada uno su propia
 * petición HTTP corta (no depende de una única request PHP de larga
 * duración). El progreso reportado es real: bytes efectivamente
 * confirmados por el servidor en cada fragmento, no simulado.
 *
 * Si la página se recarga a mitad de una subida, al volver a llamar a
 * `upload()` con el mismo archivo se detecta la sesión pendiente guardada
 * en localStorage y se reanuda desde el fragmento que el servidor confirme
 * como siguiente, en vez de reenviar bytes ya recibidos.
 */
export function useChunkedUpload() {
    const progress = ref<ChunkedUploadProgress | null>(null);
    let aborted = false;
    let currentUuid: string | null = null;

    async function upload(file: File, collectionPath?: string): Promise<UploadedMediaResult> {
        aborted = false;
        progress.value = { file, sentBytes: 0, totalBytes: file.size, percent: 0, status: 'uploading' };

        let uuid: string;
        let chunkSize: number;
        let startChunkIndex = 0;

        const resumeMap = readResumeMap();
        const existing = resumeMap[fingerprint(file)];

        if (existing) {
            const statusResponse = await apiFetch(multimediaSesiones.show(existing.uuid).url);
            if (statusResponse.ok) {
                const state = await statusResponse.json();
                if (state.status === 'uploading' && state.totalSize === file.size) {
                    uuid = existing.uuid;
                    chunkSize = existing.chunkSize;
                    startChunkIndex = state.nextChunkIndex;
                    progress.value = { ...progress.value, sentBytes: state.receivedSize, percent: state.progress };
                } else {
                    forgetSession(file);
                    ({ uuid, chunkSize } = await startSession(file, collectionPath));
                }
            } else {
                forgetSession(file);
                ({ uuid, chunkSize } = await startSession(file, collectionPath));
            }
        } else {
            ({ uuid, chunkSize } = await startSession(file, collectionPath));
        }

        currentUuid = uuid;
        rememberSession(file, { uuid, fileName: file.name, fileSize: file.size, lastModified: file.lastModified, chunkSize });

        let chunkIndex = startChunkIndex;
        let offset = startChunkIndex * chunkSize;

        while (offset < file.size) {
            if (aborted) {
                progress.value = { ...progress.value!, status: 'aborted' };
                throw new Error('Subida cancelada.');
            }

            const chunk = file.slice(offset, offset + chunkSize);
            const formData = new FormData();
            formData.append('chunk', chunk, file.name);
            formData.append('chunkIndex', String(chunkIndex));

            const chunkResponse = await apiFetch(multimediaSesiones.fragmentos(uuid).url, {
                method: 'POST',
                body: formData,
            });

            if (!chunkResponse.ok) {
                progress.value = { ...progress.value!, status: 'error' };
                throw new Error('Falló la subida de un fragmento.');
            }

            const result = await chunkResponse.json();
            offset += chunk.size;
            chunkIndex = result.nextChunkIndex;

            progress.value = { ...progress.value!, sentBytes: result.receivedSize, percent: result.progress };
        }

        const completeResponse = await apiFetch(multimediaSesiones.completar(uuid).url, { method: 'POST' });
        if (!completeResponse.ok) {
            const body = await completeResponse.json().catch(() => null);
            progress.value = { ...progress.value!, status: 'error' };
            throw new Error(body?.message ?? 'No se pudo finalizar la subida.');
        }

        forgetSession(file);
        const { media } = await completeResponse.json();
        progress.value = { ...progress.value!, percent: 100, status: 'completed' };

        return media;
    }

    async function startSession(file: File, collectionPath?: string): Promise<{ uuid: string; chunkSize: number }> {
        const checksumSha256 = await computeChecksum(file);

        const startResponse = await apiFetch(multimediaSesiones.store().url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                filename: file.name,
                mimeType: file.type || 'application/octet-stream',
                totalSize: file.size,
                collection: collectionPath,
                checksumSha256,
            }),
        });

        if (!startResponse.ok) {
            progress.value = { ...progress.value!, status: 'error' };
            throw new Error('No se pudo iniciar la sesión de subida.');
        }

        return startResponse.json();
    }

    /** Detiene la transferencia en curso y borra el archivo temporal del servidor. */
    async function cancel(file?: File) {
        aborted = true;
        if (currentUuid) {
            await apiFetch(multimediaSesiones.destroy(currentUuid).url, { method: 'DELETE' }).catch(() => undefined);
        }
        if (file) forgetSession(file);
    }

    return { progress, upload, cancel };
}
