<script setup lang="ts">
import { FileText, ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Selector de archivos para formularios públicos (sin sesión de subida):
 * los archivos viajan con el formulario (multipart). Valida en el
 * navegador cantidad, peso y tipo; el servidor vuelve a validar el
 * contenido real. v-model = File[].
 */
const files = defineModel<File[]>({ default: () => [] });

const props = withDefaults(
    defineProps<{
        id?: string;
        /** MIME permitidos, p. ej. ['image/jpeg', 'application/pdf']. */
        accept: string[];
        maxFiles: number;
        maxSizeMb: number;
        label?: string;
        hint?: string;
        invalid?: boolean;
        describedBy?: string;
    }>(),
    {
        label: 'Elige archivos o arrástralos aquí',
        hint: undefined,
        invalid: false,
        describedBy: undefined,
    },
);

const generatedId = useId();
const inputId = computed(() => props.id ?? generatedId);
const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const message = ref<string | null>(null);
const previews = ref<Map<File, string>>(new Map());

const remaining = computed(() => props.maxFiles - files.value.length);

const typeLabel = computed(() =>
    props.accept
        .map(
            (t) =>
                ({
                    'image/jpeg': 'JPG',
                    'image/png': 'PNG',
                    'image/webp': 'WebP',
                    'application/pdf': 'PDF',
                })[t] ?? t,
        )
        .join(', '),
);

function previewFor(file: File): string | null {
    if (!file.type.startsWith('image/')) {
        return null;
    }

    let url = previews.value.get(file);

    if (!url) {
        url = URL.createObjectURL(file);
        previews.value.set(file, url);
    }

    return url;
}

function add(list: FileList | File[] | null) {
    message.value = null;

    if (!list) {
        return;
    }

    const incoming = Array.from(list);
    const accepted: File[] = [];
    const problems: string[] = [];

    for (const file of incoming) {
        if (files.value.length + accepted.length >= props.maxFiles) {
            problems.push(`Puedes adjuntar hasta ${props.maxFiles} archivos.`);
            break;
        }

        if (!props.accept.includes(file.type)) {
            problems.push(
                `«${file.name}» no es un formato permitido (${typeLabel.value}).`,
            );
            continue;
        }

        if (file.size > props.maxSizeMb * 1024 * 1024) {
            problems.push(`«${file.name}» pesa más de ${props.maxSizeMb} MB.`);
            continue;
        }

        accepted.push(file);
    }

    files.value = [...files.value, ...accepted];
    message.value = problems.length ? problems.join(' ') : null;

    if (input.value) {
        input.value.value = '';
    }
}

function remove(file: File) {
    const url = previews.value.get(file);

    if (url) {
        URL.revokeObjectURL(url);
        previews.value.delete(file);
    }

    files.value = files.value.filter((f) => f !== file);
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    add(event.dataTransfer?.files ?? null);
}

watch(files, (current) => {
    for (const [file, url] of previews.value) {
        if (!current.includes(file)) {
            URL.revokeObjectURL(url);
            previews.value.delete(file);
        }
    }
});

onBeforeUnmount(() => {
    previews.value.forEach((url) => URL.revokeObjectURL(url));
});
</script>

<template>
    <div class="space-y-3">
        <label
            v-if="remaining > 0"
            :for="inputId"
            :class="
                cn(
                    'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors duration-150',
                    'focus-within:ring-3 focus-within:ring-ring/40',
                    dragging
                        ? 'border-primary bg-primary-soft'
                        : 'border-input bg-card hover:border-primary/50 hover:bg-primary-soft/40',
                    invalid && 'border-danger',
                )
            "
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <ImagePlus class="size-6 text-primary" aria-hidden="true" />
            <span class="text-sm font-medium text-foreground">{{ label }}</span>
            <span class="text-xs text-muted-foreground">
                {{
                    hint ??
                    `${typeLabel} · hasta ${maxSizeMb} MB cada uno · máximo ${maxFiles}`
                }}
            </span>
            <input
                :id="inputId"
                ref="input"
                type="file"
                multiple
                class="sr-only"
                :accept="accept.join(',')"
                :aria-describedby="describedBy"
                :aria-invalid="invalid || undefined"
                @change="add(($event.target as HTMLInputElement).files)"
            />
        </label>

        <p v-if="message" class="text-sm text-danger-ink" role="alert">
            {{ message }}
        </p>

        <ul
            v-if="files.length"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            aria-label="Archivos elegidos"
        >
            <li
                v-for="file in files"
                :key="file.name + file.size + file.lastModified"
                class="group relative overflow-hidden rounded-lg border bg-card"
            >
                <img
                    v-if="previewFor(file)"
                    :src="previewFor(file) ?? undefined"
                    :alt="`Vista previa de ${file.name}`"
                    class="aspect-[4/3] w-full object-cover"
                />
                <div
                    v-else
                    class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-1 bg-muted p-2"
                >
                    <FileText
                        class="size-6 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
                <div
                    class="flex items-center justify-between gap-2 px-2 py-1.5"
                >
                    <span class="min-w-0">
                        <span class="block truncate text-xs font-medium">{{
                            file.name
                        }}</span>
                        <span
                            class="block text-[11px] text-muted-foreground tabular-nums"
                            >{{ formatBytes(file.size) }}</span
                        >
                    </span>
                    <button
                        type="button"
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none"
                        :aria-label="`Quitar ${file.name}`"
                        @click="remove(file)"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
