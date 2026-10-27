import { reactive, ref, watch } from 'vue';
import { useChunkedUpload } from '@/composables/useChunkedUpload';
import type { UploadedMediaResult } from '@/composables/useChunkedUpload';
import { formatBytes } from '@/lib/format';

export type UploadStatus =
    | 'queued'
    | 'uploading'
    | 'completed'
    | 'error'
    | 'aborted';

export type UploadItem = {
    id: string;
    file: File;
    status: UploadStatus;
    percent: number;
    error: string | null;
    media: UploadedMediaResult | null;
};

export type UploadQueueOptions = {
    collection?: string;
    visibility?: 'public' | 'private';
    accept?: string[];
    maxSize?: number;
    maxFiles?: number;
    concurrency?: number;
    onUploaded?: (media: UploadedMediaResult, item: UploadItem) => void;
    onError?: (message: string, item: UploadItem | null) => void;
};

/** ¿El archivo coincide con la lista `accept` (mime exacto, comodín `image/*` o extensión `.pdf`)? */
export function matchesAccept(file: File, accept: string[] = []): boolean {
    if (accept.length === 0) {
        return true;
    }

    const name = file.name.toLowerCase();
    const type = (file.type || '').toLowerCase();

    return accept.some((rule) => {
        const r = rule.trim().toLowerCase();

        if (r.startsWith('.')) {
            return name.endsWith(r);
        }

        if (r.endsWith('/*')) {
            return type.startsWith(r.slice(0, -1));
        }

        return type === r;
    });
}

/**
 * Cola de subidas por fragmentos (reutiliza useChunkedUpload): una
 * instancia por archivo, progreso real por archivo, cancelar y reintentar
 * (la subida se reanuda desde el último fragmento confirmado).
 */
export function useUploadQueue(options: UploadQueueOptions) {
    const items = ref<UploadItem[]>([]);
    const uploaders = new Map<string, ReturnType<typeof useChunkedUpload>>();
    const concurrency = options.concurrency ?? 2;

    function validate(file: File): string | null {
        if (!matchesAccept(file, options.accept)) {
            return `"${file.name}" no es un tipo de archivo permitido.`;
        }

        if (options.maxSize && file.size > options.maxSize) {
            return `"${file.name}" pesa más de ${formatBytes(options.maxSize)}.`;
        }

        return null;
    }

    function add(files: FileList | File[]): void {
        for (const file of Array.from(files)) {
            if (options.maxFiles && items.value.length >= options.maxFiles) {
                options.onError?.(
                    `Puedes subir hasta ${options.maxFiles} archivos a la vez.`,
                    null,
                );
                break;
            }

            const problem = validate(file);

            if (problem) {
                options.onError?.(problem, null);
                continue;
            }

            items.value.push(
                reactive({
                    id: `${file.name}-${file.size}-${file.lastModified}-${Math.random().toString(36).slice(2, 7)}`,
                    file,
                    status: 'queued',
                    percent: 0,
                    error: null,
                    media: null,
                }) as UploadItem,
            );
        }

        pump();
    }

    function pump(): void {
        const active = items.value.filter(
            (i) => i.status === 'uploading',
        ).length;
        const next = items.value
            .filter((i) => i.status === 'queued')
            .slice(0, Math.max(0, concurrency - active));

        next.forEach((item) => void run(item));
    }

    async function run(item: UploadItem): Promise<void> {
        const uploader = uploaders.get(item.id) ?? useChunkedUpload();
        uploaders.set(item.id, uploader);
        item.status = 'uploading';
        item.error = null;

        const stop = watchProgress(item, uploader);

        try {
            const media = await uploader.upload(item.file, options.collection, {
                visibility: options.visibility,
            });
            item.media = media;
            item.percent = 100;
            item.status = 'completed';
            options.onUploaded?.(media, item);
        } catch (error) {
            if ((item.status as UploadStatus) !== 'aborted') {
                item.status = 'error';
                item.error =
                    error instanceof Error
                        ? error.message
                        : 'No se pudo subir el archivo.';
                options.onError?.(item.error, item);
            }
        } finally {
            stop();
            pump();
        }
    }

    function watchProgress(
        item: UploadItem,
        uploader: ReturnType<typeof useChunkedUpload>,
    ): () => void {
        return watch(uploader.progress, (p) => {
            if (p && item.status === 'uploading') {
                item.percent = Math.round(p.percent);
            }
        });
    }

    async function cancel(item: UploadItem): Promise<void> {
        if (item.status === 'queued') {
            item.status = 'aborted';

            return;
        }

        item.status = 'aborted';
        await uploaders.get(item.id)?.cancel(item.file);
    }

    function retry(item: UploadItem): void {
        item.status = 'queued';
        item.error = null;
        pump();
    }

    function remove(item: UploadItem): void {
        if (item.status === 'uploading') {
            void cancel(item);
        }

        uploaders.delete(item.id);
        items.value = items.value.filter((i) => i.id !== item.id);
    }

    function clearFinished(): void {
        items.value = items.value.filter((i) => i.status !== 'completed');
    }

    return { items, add, cancel, retry, remove, clearFinished };
}
