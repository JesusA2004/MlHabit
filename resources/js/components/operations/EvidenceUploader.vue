<script setup lang="ts">
import { Camera, ImagePlus, Video } from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
import { toast } from 'vue-sonner';
import FileUploadItem from '@/components/ds/FileUploadItem.vue';
import { useUploadQueue } from '@/components/ds/useUploadQueue';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Captura de evidencia para campo (móvil primero): botones grandes para
 * tomar foto o grabar video con la cámara del teléfono, o elegir de la
 * galería. Sube cada archivo como PRIVADO por fragmentos (reanudable) y
 * expone por v-model los ids de los archivos ya subidos.
 */
const model = defineModel<number[]>({ default: () => [] });

const props = withDefaults(
    defineProps<{
        /** Peso máximo por archivo (por defecto 60 MB). */
        maxSize?: number;
        maxFiles?: number;
        allowVideo?: boolean;
        disabled?: boolean;
        collection?: string;
        label?: string;
    }>(),
    {
        maxSize: 60 * 1024 * 1024,
        maxFiles: 12,
        allowVideo: true,
        disabled: false,
        collection: 'privado/evidencias',
        label: 'Evidencia',
    },
);

const emit = defineEmits<{ busy: [value: boolean] }>();

const photoId = useId();
const videoId = useId();
const galleryId = useId();
const inputs = ref<HTMLInputElement[]>([]);

const accept = computed(() =>
    props.allowVideo ? ['image/*', 'video/*'] : ['image/*'],
);

const queue = useUploadQueue({
    collection: props.collection,
    visibility: 'private',
    accept: accept.value,
    maxSize: props.maxSize,
    maxFiles: props.maxFiles,
    onUploaded: (media) => {
        if (!model.value.includes(media.id)) {
            model.value = [...model.value, media.id];
        }
    },
    onError: (message) => toast.error(message),
});

const uploading = computed(() =>
    queue.items.value.some(
        (i) => i.status === 'uploading' || i.status === 'queued',
    ),
);
watch(uploading, (value) => emit('busy', value));

function onFiles(event: Event) {
    const input = event.target as HTMLInputElement;

    if (input.files?.length) {
        queue.add(input.files);
    }

    input.value = '';
}

function remove(itemId: string) {
    const item = queue.items.value.find((i) => i.id === itemId);

    if (!item) {
        return;
    }

    if (item.media) {
        model.value = model.value.filter((id) => id !== item.media?.id);
    }

    queue.remove(item);
}

defineExpose({ uploading, clear: () => queue.items.value.splice(0) });

const buttonClass =
    'flex min-h-14 flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-input bg-surface/60 px-3 text-sm font-medium transition-colors hover:border-primary/50 hover:bg-primary-soft/40 has-[input:focus-visible]:ring-3 has-[input:focus-visible]:ring-ring/40';
</script>

<template>
    <div class="flex flex-col gap-3">
        <div
            :class="
                cn(
                    'flex flex-col gap-2 sm:flex-row',
                    disabled && 'pointer-events-none opacity-50',
                )
            "
            :aria-label="label"
            role="group"
        >
            <label :for="photoId" :class="buttonClass">
                <Camera class="size-5 text-primary" aria-hidden="true" />
                Tomar foto
                <input
                    :id="photoId"
                    ref="inputs"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="sr-only"
                    :disabled="disabled"
                    @change="onFiles"
                />
            </label>
            <label v-if="allowVideo" :for="videoId" :class="buttonClass">
                <Video class="size-5 text-primary" aria-hidden="true" />
                Grabar video
                <input
                    :id="videoId"
                    type="file"
                    accept="video/*"
                    capture="environment"
                    class="sr-only"
                    :disabled="disabled"
                    @change="onFiles"
                />
            </label>
            <label :for="galleryId" :class="buttonClass">
                <ImagePlus class="size-5 text-primary" aria-hidden="true" />
                Elegir de la galería
                <input
                    :id="galleryId"
                    type="file"
                    :accept="accept.join(',')"
                    multiple
                    class="sr-only"
                    :disabled="disabled"
                    @change="onFiles"
                />
            </label>
        </div>
        <p class="text-xs text-muted-foreground">
            Fotos{{ allowVideo ? ' o videos cortos' : '' }} de hasta
            {{ formatBytes(maxSize) }} cada uno. Se guardan de forma privada.
        </p>

        <ul
            v-if="queue.items.value.length"
            class="flex flex-col gap-2"
            aria-label="Archivos en subida"
        >
            <FileUploadItem
                v-for="item in queue.items.value"
                :key="item.id"
                :item="item"
                @cancel="queue.cancel(item)"
                @retry="queue.retry(item)"
                @remove="remove(item.id)"
            />
        </ul>
    </div>
</template>
