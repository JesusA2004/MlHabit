<script setup lang="ts">
import { UploadCloud } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import type { HTMLAttributes } from 'vue';
import { toast } from 'vue-sonner';
import type { UploadedMediaResult } from '@/composables/useChunkedUpload';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';
import FileUploadItem from './FileUploadItem.vue';
import { useUploadQueue } from './useUploadQueue';

/**
 * Zona para arrastrar o elegir varios archivos. Sube cada uno por
 * fragmentos (useChunkedUpload → /admin/multimedia-sesiones) con progreso
 * real, cancelar y reintentar. `collection` y `visibility` viajan en el
 * payload de inicio de la sesión de subida.
 */
const props = withDefaults(
    defineProps<{
        /** Tipos permitidos: mime (`image/jpeg`), comodín (`image/*`) o extensión (`.pdf`). */
        accept?: string[];
        /** Peso máximo por archivo en bytes. */
        maxSize?: number;
        maxFiles?: number;
        multiple?: boolean;
        collection?: string;
        visibility?: 'public' | 'private';
        disabled?: boolean;
        label?: string;
        hint?: string;
        compact?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        accept: () => [],
        multiple: true,
        visibility: 'private',
        disabled: false,
        label: 'Arrastra archivos aquí o elige desde tu equipo',
        compact: false,
    },
);

const emit = defineEmits<{
    uploaded: [media: UploadedMediaResult];
    error: [message: string];
    'all-completed': [media: UploadedMediaResult[]];
}>();

const inputId = useId();
const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
let dragDepth = 0;

const queue = useUploadQueue({
    collection: props.collection,
    visibility: props.visibility,
    accept: props.accept,
    maxSize: props.maxSize,
    maxFiles: props.maxFiles,
    onUploaded: (media) => {
        emit('uploaded', media);

        const all = queue.items.value;

        if (
            all.every((i) => i.status === 'completed' || i.status === 'aborted')
        ) {
            emit(
                'all-completed',
                all
                    .filter((i) => i.media)
                    .map((i) => i.media as UploadedMediaResult),
            );
        }
    },
    onError: (message) => {
        emit('error', message);
        toast.error(message);
    },
});

const acceptAttr = computed(() => props.accept.join(','));

const hintText = computed(() => {
    if (props.hint) {
        return props.hint;
    }

    const parts: string[] = [];

    if (props.accept.length) {
        parts.push(
            props.accept
                .map((a) =>
                    a.replace('/*', '').replace(/^\./, '').toUpperCase(),
                )
                .join(', '),
        );
    }

    if (props.maxSize) {
        parts.push(`hasta ${formatBytes(props.maxSize)} por archivo`);
    }

    return parts.join(' · ');
});

function onFiles(files: FileList | null | undefined) {
    if (!files || files.length === 0 || props.disabled) {
        return;
    }

    queue.add(props.multiple ? files : [files[0]]);

    if (input.value) {
        input.value.value = '';
    }
}

function onDragEnter() {
    dragDepth += 1;
    dragging.value = true;
}

function onDragLeave() {
    dragDepth = Math.max(0, dragDepth - 1);
    dragging.value = dragDepth > 0;
}

function onDrop(event: DragEvent) {
    dragDepth = 0;
    dragging.value = false;
    onFiles(event.dataTransfer?.files);
}

defineExpose({ items: queue.items, clearFinished: queue.clearFinished });
</script>

<template>
    <div
        data-slot="file-dropzone"
        :class="cn('flex flex-col gap-3', props.class)"
    >
        <label
            :for="inputId"
            :class="
                cn(
                    'group relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-input bg-surface/60 px-6 text-center transition-colors duration-150',
                    props.compact ? 'py-6' : 'py-10',
                    'hover:border-primary/50 hover:bg-primary-soft/40',
                    'has-[input:focus-visible]:border-primary has-[input:focus-visible]:ring-3 has-[input:focus-visible]:ring-ring/40',
                    dragging && 'border-primary bg-primary-soft/60',
                    props.disabled && 'pointer-events-none opacity-50',
                )
            "
            @dragenter.prevent="onDragEnter"
            @dragover.prevent
            @dragleave.prevent="onDragLeave"
            @drop.prevent="onDrop"
        >
            <span
                class="grid size-11 place-items-center rounded-xl bg-card text-primary shadow-xs ring-1 ring-border transition-transform duration-200 group-hover:-translate-y-0.5"
                aria-hidden="true"
            >
                <UploadCloud class="size-5" />
            </span>
            <span class="text-sm font-medium text-foreground">
                {{ dragging ? 'Suelta para subir' : props.label }}
            </span>
            <span v-if="hintText" class="text-xs text-muted-foreground">{{
                hintText
            }}</span>
            <input
                :id="inputId"
                ref="input"
                type="file"
                class="sr-only"
                :accept="acceptAttr || undefined"
                :multiple="props.multiple"
                :disabled="props.disabled"
                @change="onFiles(($event.target as HTMLInputElement).files)"
            />
        </label>

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
                @remove="queue.remove(item)"
            />
        </ul>
    </div>
</template>
