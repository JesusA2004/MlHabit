<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Camera, FileText, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import FileDropzone from '@/components/ds/FileDropzone.vue';
import FileUploadItem from '@/components/ds/FileUploadItem.vue';
import { useUploadQueue } from '@/components/ds/useUploadQueue';
import { Button } from '@/components/ui/button';
import type { UploadedMediaResult } from '@/composables/useChunkedUpload';
import archivos from '@/routes/tecnico/mediciones/archivos';
import type { FileItem } from '@/types/projects';

/**
 * Fotos o croquis de una medición: cámara directa (capture) en móvil o
 * arrastrar/elegir archivos. Cada archivo subido (privado) se vincula a la
 * medición en el servidor, que valida que sea del técnico.
 */
const props = defineProps<{
    measurementId: number;
    role: 'photo' | 'sketch';
    files: FileItem[];
    editable: boolean;
}>();

const ACCEPT = ['image/*', 'application/pdf'];
const MAX_SIZE = 25 * 1024 * 1024;

const cameraInput = ref<HTMLInputElement | null>(null);
const toRemove = ref<FileItem | null>(null);
const removeOpen = computed({
    get: () => toRemove.value !== null,
    set: (v: boolean) => {
        if (!v) {
            toRemove.value = null;
        }
    },
});

const label = computed(() => (props.role === 'photo' ? 'foto' : 'croquis'));

function attach(media: UploadedMediaResult) {
    router.post(
        archivos.store(props.measurementId).url,
        { media_id: media.id, role: props.role },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['measurement'],
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo guardar el archivo.',
                ),
        },
    );
}

const camera = useUploadQueue({
    visibility: 'private',
    accept: ['image/*'],
    maxSize: MAX_SIZE,
    onUploaded: (media) => attach(media),
    onError: (message) => toast.error(message),
});

const cameraActive = computed(() =>
    camera.items.value.filter((i) => i.status !== 'completed'),
);

function onCamera(event: Event) {
    const input = event.target as HTMLInputElement;

    if (input.files?.length) {
        camera.add(input.files);
    }

    input.value = '';
}

function remove(): Promise<void> {
    const file = toRemove.value;

    if (!file) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        router.delete(
            archivos.destroy({
                measurement: props.measurementId,
                attachment: file.id,
            }).url,
            {
                preserveScroll: true,
                only: ['measurement'],
                onSuccess: () => resolve(),
                onError: () =>
                    reject(new Error('No se pudo quitar el archivo.')),
            },
        );
    });
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <ul
            v-if="props.files.length"
            class="grid grid-cols-3 gap-2 sm:grid-cols-4"
            role="list"
        >
            <li
                v-for="file in props.files"
                :key="file.id"
                class="group relative aspect-square overflow-hidden rounded-lg border bg-muted"
            >
                <a
                    :href="file.url ?? '#'"
                    target="_blank"
                    rel="noopener"
                    class="block size-full"
                    :aria-label="`Abrir ${file.name}`"
                >
                    <img
                        v-if="file.is_image && file.url"
                        :src="file.url"
                        :alt="file.caption ?? file.name"
                        class="size-full object-cover"
                        loading="lazy"
                    />
                    <span
                        v-else
                        class="flex size-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-muted-foreground"
                    >
                        <FileText class="size-6" aria-hidden="true" />
                        <span class="line-clamp-2 break-all">{{
                            file.name
                        }}</span>
                    </span>
                </a>
                <button
                    v-if="props.editable"
                    type="button"
                    class="absolute top-1 right-1 grid size-9 place-items-center rounded-full bg-background/90 text-foreground shadow-sm ring-1 ring-border hover:text-danger focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="`Quitar ${file.name}`"
                    @click="toRemove = file"
                >
                    <Trash2 class="size-4" />
                </button>
            </li>
        </ul>
        <p v-else-if="!props.editable" class="text-sm text-muted-foreground">
            Sin {{ props.role === 'photo' ? 'fotos' : 'croquis' }}.
        </p>

        <template v-if="props.editable">
            <Button
                type="button"
                variant="outline"
                size="lg"
                class="h-12 text-base"
                @click="cameraInput?.click()"
            >
                <Camera class="size-5" />
                {{
                    props.role === 'photo'
                        ? 'Tomar foto'
                        : 'Fotografiar croquis'
                }}
            </Button>
            <input
                ref="cameraInput"
                type="file"
                accept="image/*"
                capture="environment"
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
                @change="onCamera"
            />
            <ul
                v-if="cameraActive.length"
                class="flex flex-col gap-2"
                aria-live="polite"
            >
                <li v-for="item in cameraActive" :key="item.id">
                    <FileUploadItem
                        :item="item"
                        @cancel="camera.cancel(item)"
                        @retry="camera.retry(item)"
                        @remove="camera.remove(item)"
                    />
                </li>
            </ul>
            <FileDropzone
                compact
                visibility="private"
                :accept="ACCEPT"
                :max-size="MAX_SIZE"
                :label="`O elige ${label === 'foto' ? 'fotos' : 'archivos'} desde el teléfono`"
                hint="Imágenes o PDF, hasta 25 MB"
                @uploaded="attach"
            />
        </template>
    </div>

    <ConfirmDialog
        v-model:open="removeOpen"
        :title="`¿Quitar este ${label}?`"
        description="Se quita de la medición. Puedes volver a subirlo mientras la medición siga en borrador."
        confirm-label="Quitar"
        loading-label="Quitando…"
        tone="destructive"
        :confirm="remove"
    />
</template>
