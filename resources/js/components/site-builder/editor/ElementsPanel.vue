<script setup lang="ts">
import { ImageOff, Loader2, Search, Upload } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, inject, onMounted, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { Input } from '@/components/ui/input';
import { apiFetch } from '@/lib/http';
import multimedia from '@/routes/admin/multimedia';
import type { MediaItem, SimplePaginator } from '@/types/web';
import { BLOCKS } from '../registry';
import type { BlockDefinition } from '../registry';
import type { BlockType } from '../types';
import { editorKey } from './keys';

/**
 * Elementos para arrastrar al lienzo (o tocar para agregarlos a la sección
 * seleccionada) e imágenes de la biblioteca multimedia.
 */
const props = defineProps<{ canLibrary: boolean; canUpload: boolean }>();
const emit = defineEmits<{ upload: [files: File[]] }>();

const editor = inject(editorKey)!;
const payload =
    inject<
        Ref<
            | { kind: 'block'; type: BlockType }
            | { kind: 'media'; id: number }
            | null
        >
    >('sb-drag-payload')!;

const GROUPS: { id: BlockDefinition['group']; label: string }[] = [
    { id: 'basic', label: 'Texto y botones' },
    { id: 'media', label: 'Fotos y video' },
    { id: 'decor', label: 'Decoración' },
    { id: 'dynamic', label: 'Bloques de ML Habitat' },
];

const entries = Object.entries(BLOCKS) as [BlockType, BlockDefinition][];
const byGroup = (group: BlockDefinition['group']) =>
    entries.filter(([, def]) => def.group === group);

function dragBlock(event: DragEvent, type: BlockType) {
    payload.value = { kind: 'block', type };
    event.dataTransfer?.setData('text/plain', BLOCKS[type].label);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy';
}

function dragMedia(event: DragEvent, item: MediaItem) {
    payload.value = { kind: 'media', id: item.id };
    editor.library.value = { ...editor.library.value, [String(item.id)]: item };
    event.dataTransfer?.setData('text/plain', item.filename);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy';
}

function dragEnd() {
    payload.value = null;
    editor.ghost.value = null;
    editor.dropTarget.value = null;
}

const targetSection = computed(
    () => editor.selection.section ?? editor.sections.value[0]?.key ?? null,
);

function add(type: BlockType) {
    if (!targetSection.value) return;
    editor.device.value = 'desktop';
    editor.addBlock(targetSection.value, type);
}

function useImage(item: MediaItem) {
    editor.library.value = { ...editor.library.value, [String(item.id)]: item };
    const selected = editor.primaryBlock.value;
    if (selected?.type === 'image' && editor.selection.section) {
        editor.updateProps(editor.selection.section, selected.id, {
            mediaId: item.id,
            focalX: 50,
            focalY: 50,
            zoom: 1,
        });

        return;
    }
    if (!targetSection.value) return;
    editor.addBlock(targetSection.value, 'image', undefined, {
        mediaId: item.id,
    });
}

// ----- Biblioteca ------------------------------------------------------
const query = ref('');
const items = ref<MediaItem[]>([]);
const loading = ref(false);
const failed = ref(false);

async function load() {
    if (!props.canLibrary) return;
    loading.value = true;
    failed.value = false;
    try {
        const params: Record<string, string> = { tipo: 'image' };
        if (query.value.trim()) params.q = query.value.trim();
        const response = await apiFetch(
            multimedia.buscar({ query: params }).url,
        );
        if (!response.ok) throw new Error();
        const json = (await response.json()) as SimplePaginator<MediaItem>;
        items.value = json.data.filter(
            (m) => m.visibility === 'public' && m.type === 'image',
        );
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

const search = useDebounceFn(load, 300);
watch(query, () => void search());
onMounted(load);

const fileInput = ref<HTMLInputElement | null>(null);
function onFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = [...(input.files ?? [])].filter((f) =>
        f.type.startsWith('image/'),
    );
    if (files.length) emit('upload', files);
    input.value = '';
}

defineExpose({ reload: load });
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-4 pt-4 pb-6">
        <p class="text-xs text-muted-foreground">
            Arrastra un elemento al lienzo o tócalo para agregarlo a la sección
            seleccionada.
        </p>

        <section v-for="group in GROUPS" :key="group.id" class="mt-5">
            <h3 class="mb-2 text-xs font-medium text-muted-foreground">
                {{ group.label }}
            </h3>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="[type, def] in byGroup(group.id)"
                    :key="type"
                    type="button"
                    draggable="true"
                    class="group flex aspect-square flex-col items-center justify-center gap-1.5 rounded-xl border border-border bg-card p-2 text-center text-[11px] leading-tight font-medium text-foreground shadow-xs transition-[box-shadow,border-color,transform] duration-150 hover:-translate-y-px hover:border-primary/40 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none active:translate-y-0"
                    :aria-label="`Agregar ${def.label}`"
                    @dragstart="dragBlock($event, type)"
                    @dragend="dragEnd"
                    @click="add(type)"
                >
                    <span
                        class="grid size-8 place-items-center rounded-lg transition-colors"
                        :class="
                            group.id === 'dynamic'
                                ? 'bg-primary-soft text-primary'
                                : 'bg-muted text-foreground/80 group-hover:bg-primary-soft group-hover:text-primary'
                        "
                    >
                        <component
                            :is="def.icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </span>
                    {{ def.label }}
                </button>
            </div>
        </section>

        <section class="mt-6">
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-xs font-medium text-muted-foreground">
                    Imágenes de la biblioteca
                </h3>
                <button
                    v-if="props.canUpload"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-xs font-medium text-primary hover:bg-primary-soft"
                    @click="fileInput?.click()"
                >
                    <Upload class="size-3.5" aria-hidden="true" /> Subir
                </button>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    multiple
                    class="sr-only"
                    tabindex="-1"
                    @change="onFiles"
                />
            </div>
            <template v-if="props.canLibrary">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="query"
                        placeholder="Buscar imágenes"
                        class="h-9 pl-8"
                        aria-label="Buscar imágenes"
                    />
                </div>
                <div
                    v-if="loading && !items.length"
                    class="grid place-items-center py-8 text-muted-foreground"
                >
                    <Loader2
                        class="size-5 animate-spin"
                        aria-label="Cargando"
                    />
                </div>
                <p
                    v-else-if="failed"
                    class="py-6 text-center text-sm text-danger-ink"
                >
                    No se pudo cargar la biblioteca.
                </p>
                <p
                    v-else-if="!items.length"
                    class="flex flex-col items-center gap-2 py-6 text-center text-sm text-muted-foreground"
                >
                    <ImageOff class="size-5" aria-hidden="true" />
                    {{
                        query
                            ? 'Sin resultados.'
                            : 'Aún no hay imágenes públicas. Arrastra fotos desde tu computadora al lienzo.'
                    }}
                </p>
                <div v-else class="mt-3 grid grid-cols-2 gap-2">
                    <button
                        v-for="item in items"
                        :key="item.id"
                        type="button"
                        draggable="true"
                        class="group relative aspect-[4/3] overflow-hidden rounded-lg bg-muted ring-1 ring-border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :aria-label="`Usar imagen ${item.altText || item.filename}`"
                        :title="item.filename"
                        @dragstart="dragMedia($event, item)"
                        @dragend="dragEnd"
                        @click="useImage(item)"
                    >
                        <img
                            v-if="item.thumbnailUrl || item.url"
                            :src="item.thumbnailUrl ?? item.url ?? ''"
                            :alt="item.altText ?? ''"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                            draggable="false"
                        />
                        <span
                            v-if="item.originLabel"
                            class="absolute bottom-1 left-1 rounded-full bg-black/55 px-1.5 py-0.5 text-[10px] text-white"
                            >{{ item.originLabel }}</span
                        >
                    </button>
                </div>
            </template>
            <p v-else class="text-sm text-muted-foreground">
                Necesitas permiso de multimedia para usar la biblioteca.
            </p>
        </section>
    </div>
</template>
