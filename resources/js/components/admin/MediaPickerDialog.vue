<script setup lang="ts">
import { Check, Clapperboard, FileText, ImageIcon, Loader2, Search, UploadCloud } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { apiFetch } from '@/lib/http';
import { cn } from '@/lib/utils';
import multimedia from '@/routes/admin/multimedia';

/**
 * Biblioteca multimedia en diálogo: buscar, filtrar, subir (clic o
 * arrastrar) y elegir. Reutiliza los endpoints existentes de /admin/multimedia
 * (búsqueda y subida); las URLs vienen siempre del backend.
 */
export interface PickedMedia {
    id: number;
    url: string | null;
    filename: string;
    type: string;
    altText: string | null;
    mimeType?: string;
    status?: string;
    width?: number | null;
    height?: number | null;
    durationSeconds?: number | null;
    posterUrl?: string | null;
}

const open = defineModel<boolean>('open', { default: false });

const { type } = defineProps<{ type?: 'image' | 'video' | 'captions' }>();

const emit = defineEmits<{ select: [media: PickedMedia] }>();

const query = ref('');
const results = ref<PickedMedia[]>([]);
const loading = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);
const uploadError = ref<string | null>(null);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const filter = ref<'image' | 'video' | 'all'>(type === 'video' ? 'video' : type === 'image' ? 'image' : 'all');

const accept = computed(() =>
    type === 'video' ? 'video/*' : type === 'image' ? 'image/*' : type === 'captions' ? '.vtt,text/vtt' : 'image/*,video/*',
);

const visible = computed(() =>
    type === 'captions'
        ? results.value.filter((m) => m.filename.toLowerCase().endsWith('.vtt'))
        : results.value,
);

async function search() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (query.value) params.set('q', query.value);
        if (type !== 'captions' && filter.value !== 'all') params.set('tipo', filter.value);

        const response = await apiFetch(`${multimedia.buscar().url}?${params.toString()}`);
        const payload = await response.json();
        results.value = payload.data;
    } finally {
        loading.value = false;
    }
}

watch(open, (value) => {
    if (value) {
        uploadError.value = null;
        void search();
    }
});
watch(filter, () => void search());

let debounceTimer: ReturnType<typeof setTimeout> | undefined;
watch(query, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(search, 300);
});

function pick(media: PickedMedia) {
    emit('select', media);
    open.value = false;
}

function upload(file: File) {
    uploading.value = true;
    uploadProgress.value = 0;
    uploadError.value = null;

    const formData = new FormData();
    formData.append('file', file);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', multimedia.store().url);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('Accept', 'application/json');
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match) xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(match[1]));

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) uploadProgress.value = Math.round((e.loaded / e.total) * 100);
    };
    xhr.onload = () => {
        uploading.value = false;
        if (xhr.status === 201) {
            const payload = JSON.parse(xhr.responseText);
            results.value = [payload.media, ...results.value];
            pick(payload.media);
        } else {
            try {
                uploadError.value = JSON.parse(xhr.responseText).message ?? 'No se pudo subir el archivo.';
            } catch {
                uploadError.value = 'No se pudo subir el archivo.';
            }
        }
        if (fileInput.value) fileInput.value.value = '';
    };
    xhr.onerror = () => {
        uploading.value = false;
        uploadError.value = 'Error de conexión durante la subida.';
    };
    xhr.send(formData);
}

function onFileChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) upload(file);
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) upload(file);
}

function dims(media: PickedMedia): string {
    if (media.width && media.height) return `${media.width}×${media.height}`;
    if (media.durationSeconds) return `${Math.floor(media.durationSeconds / 60)}:${String(media.durationSeconds % 60).padStart(2, '0')}`;

    return '';
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[88vh] flex-col gap-4 sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>Biblioteca multimedia</DialogTitle>
                <DialogDescription>
                    Elige un archivo existente o arrastra uno nuevo aquí.
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[200px] flex-1">
                    <Search class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="query" placeholder="Buscar por nombre…" class="pl-8" />
                </div>
                <div v-if="!type" class="flex rounded-md border p-0.5" role="radiogroup" aria-label="Tipo de archivo">
                    <button
                        v-for="option in [{ v: 'all', l: 'Todo' }, { v: 'image', l: 'Imágenes' }, { v: 'video', l: 'Videos' }] as const"
                        :key="option.v"
                        type="button"
                        role="radio"
                        :aria-checked="filter === option.v"
                        :class="cn('h-8 rounded px-3 text-xs font-medium text-muted-foreground', filter === option.v && 'bg-primary text-primary-foreground')"
                        @click="filter = option.v"
                    >
                        {{ option.l }}
                    </button>
                </div>
                <Button variant="default" :disabled="uploading" @click="fileInput?.click()">
                    <Loader2 v-if="uploading" class="size-4 animate-spin" />
                    <UploadCloud v-else class="size-4" />
                    Subir {{ type === 'video' ? 'video' : type === 'captions' ? 'subtítulos' : 'archivo' }}
                </Button>
                <input ref="fileInput" type="file" class="hidden" :accept="accept" @change="onFileChange" />
            </div>

            <div
                class="relative min-h-[320px] flex-1 overflow-y-auto rounded-lg border border-dashed p-3 transition-colors"
                :class="dragging ? 'border-brand bg-brand/5' : 'border-transparent'"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <div v-if="uploading" class="mb-3 flex items-center gap-3 rounded-md bg-muted px-3 py-2 text-sm">
                    <Loader2 class="size-4 animate-spin text-brand" />
                    Subiendo… {{ uploadProgress }}%
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-border">
                        <div class="h-full bg-brand transition-[width]" :style="{ width: `${uploadProgress}%` }" />
                    </div>
                </div>
                <p v-if="uploadError" class="mb-3 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
                    {{ uploadError }}
                </p>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    <button
                        v-for="media in visible"
                        :key="media.id"
                        type="button"
                        class="group flex flex-col overflow-hidden rounded-lg border bg-card text-left transition-shadow hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring"
                        :disabled="!!media.status && media.status !== 'ready'"
                        @click="pick(media)"
                    >
                        <span class="relative block aspect-[4/3] overflow-hidden bg-muted">
                            <img
                                v-if="media.type === 'image' && media.url"
                                :src="media.url"
                                :alt="media.altText ?? media.filename"
                                loading="lazy"
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <img
                                v-else-if="media.type === 'video' && media.posterUrl"
                                :src="media.posterUrl"
                                alt=""
                                loading="lazy"
                                class="size-full object-cover"
                            />
                            <span v-else class="grid size-full place-items-center text-muted-foreground">
                                <Clapperboard v-if="media.type === 'video'" class="size-8" />
                                <FileText v-else-if="media.type !== 'image'" class="size-8" />
                                <ImageIcon v-else class="size-8" />
                            </span>
                            <span
                                v-if="media.type === 'video'"
                                class="absolute top-2 left-2 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                >VIDEO</span
                            >
                            <span
                                v-if="media.status && media.status !== 'ready'"
                                class="absolute inset-0 grid place-items-center bg-black/60 text-xs font-semibold text-white"
                            >
                                {{ media.status === 'processing' ? 'Procesando…' : 'No disponible' }}
                            </span>
                            <span class="absolute right-2 bottom-2 grid size-7 place-items-center rounded-full bg-brand text-brand-foreground opacity-0 transition-opacity group-hover:opacity-100">
                                <Check class="size-4" />
                            </span>
                        </span>
                        <span class="flex flex-col gap-0.5 px-2.5 py-2">
                            <span class="truncate text-xs font-medium">{{ media.filename }}</span>
                            <span class="text-[11px] text-muted-foreground">{{ dims(media) }}</span>
                        </span>
                    </button>
                </div>

                <div
                    v-if="!loading && visible.length === 0"
                    class="flex flex-col items-center justify-center gap-2 py-16 text-center text-sm text-muted-foreground"
                >
                    <UploadCloud class="size-8" />
                    <p>No hay archivos que coincidan. Arrastra uno aquí para subirlo.</p>
                </div>
                <div v-if="loading" class="absolute inset-x-0 top-3 flex justify-center">
                    <Loader2 class="size-5 animate-spin text-muted-foreground" />
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
