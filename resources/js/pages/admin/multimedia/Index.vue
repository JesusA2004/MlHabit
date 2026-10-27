<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clapperboard,
    Copy,
    ExternalLink,
    FileText,
    Folder,
    FolderOpen,
    ImageIcon,
    Info,
    LayoutGrid,
    Library,
    List,
    Loader2,
    Play,
    RotateCcw,
    Search,
    Trash2,
    UploadCloud,
    X,
    XCircle,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import DestinationPicker, { type CollectionNode } from '@/components/admin/DestinationPicker.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import SegmentedControl from '@/components/studio/controls/SegmentedControl.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useChunkedUpload } from '@/composables/useChunkedUpload';
import { apiFetch } from '@/lib/http';
import { cn } from '@/lib/utils';
import multimedia from '@/routes/admin/multimedia';
import type { BreadcrumbItem } from '@/types';

interface MediaRow {
    id: number;
    filename: string;
    type: string;
    status: string;
    mimeType: string;
    size: number;
    width: number | null;
    height: number | null;
    durationSeconds: number | null;
    url: string | null;
    posterUrl: string | null;
    altText: string | null;
    destinationPath: string;
    disk: string;
    createdAt: string | null;
}

interface MediaUsage {
    type: string;
    label: string;
    href: string;
}

const props = defineProps<{
    media: {
        data: MediaRow[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
    filters: { tipo?: string; coleccion?: string; q?: string; orden?: string };
    collections: CollectionNode[];
    storageBytes: number;
    activeProvider: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Multimedia', href: multimedia.index() },
        ] as BreadcrumbItem[],
    },
});

// Clases de tamaño táctil: densidad de escritorio, 44 px con puntero grueso (iPad/móvil).
const TOUCH = 'pointer-coarse:h-11';
const TOUCH_ICON = 'pointer-coarse:size-11';
const TOUCH_SELECT = 'pointer-coarse:data-[size=default]:h-11';

const ALL = '__todo__';

// ---------------------------------------------------------------- Formato

const TYPE_LABELS: Record<string, string> = {
    image: 'Imagen',
    video: 'Video',
    document: 'Documento',
    other: 'Archivo',
};

const TYPE_ICONS: Record<string, Component> = {
    image: ImageIcon,
    video: Clapperboard,
    document: FileText,
    other: FileText,
};

function formatBytes(bytes: number): string {
    if (bytes <= 0) return '0 KB';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const exp = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    return `${(bytes / 1024 ** exp).toFixed(exp <= 1 ? 0 : 1)} ${units[exp]}`;
}

function formatDuration(seconds: number | null): string {
    if (!seconds) return '';
    const m = Math.floor(seconds / 60);
    const s = Math.round(seconds % 60);

    return `${m}:${s.toString().padStart(2, '0')}`;
}

function formatDate(iso: string | null, withTime = false): string {
    if (!iso) return '—';

    return new Intl.DateTimeFormat('es-MX', withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(new Date(iso));
}

function dimensions(item: MediaRow): string {
    return item.width && item.height ? `${item.width} × ${item.height}` : '';
}

function extension(item: MediaRow): string {
    return item.filename.split('.').pop()?.toUpperCase() ?? '';
}

function thumbOf(item: MediaRow): string | null {
    if (item.type === 'image') return item.url;
    if (item.type === 'video') return item.posterUrl;

    return null;
}

function cardMeta(item: MediaRow): string {
    return [item.type === 'video' ? formatDuration(item.durationSeconds) : dimensions(item), formatBytes(item.size)]
        .filter(Boolean)
        .join(' · ');
}

function statusOverlay(item: MediaRow): string | null {
    if (item.status === 'processing') return 'Procesando…';
    if (item.status === 'uploading') return 'Subiendo…';
    if (item.status === 'failed') return 'Error al procesar';

    return null;
}

// ------------------------------------------------- Colecciones y carpetas

const byPath = computed(() => new Map(props.collections.map((c) => [c.path, c])));
const libraryRoot = computed(() => props.collections.find((c) => c.parent_id === null && c.path === 'mr-insight'));

function childrenOf(parentId: number | null) {
    return props.collections.filter((c) => c.parent_id === parentId).sort((a, b) => a.name.localeCompare(b.name));
}

const tree = computed(() =>
    (libraryRoot.value ? childrenOf(libraryRoot.value.id) : childrenOf(null)).map((node) => ({
        node,
        children: childrenOf(node.id),
    })),
);

/** «Colección › Carpeta» a partir de la ruta lógica, sin mostrar slugs ni el nombre del archivo en disco. */
function folderLabel(collectionPath: string): string {
    const segments = collectionPath.split('/').filter(Boolean);
    const names: string[] = [];
    for (let i = 1; i <= segments.length; i++) {
        const node = byPath.value.get(segments.slice(0, i).join('/'));
        if (node && node.id === libraryRoot.value?.id) continue;
        names.push(node?.name ?? segments[i - 1]);
    }

    return names.join(' › ') || 'Biblioteca';
}

function itemFolder(item: MediaRow): string {
    return folderLabel(item.destinationPath.split('/').slice(0, -1).join('/'));
}

// --------------------------------------------------------------- Filtros

const items = ref<MediaRow[]>(props.media.data);
watch(
    () => props.media.data,
    (data) => {
        items.value = data;
    },
);

const search = ref(props.filters.q ?? '');
const tipo = ref<string>(props.filters.tipo ?? 'todos');
const orden = ref<string>(props.filters.orden ?? 'reciente');
const coleccion = ref<string>(props.filters.coleccion ?? ALL);

const TYPE_OPTIONS = [
    { value: 'todos', label: 'Todo' },
    { value: 'image', label: 'Imágenes' },
    { value: 'video', label: 'Videos' },
    { value: 'document', label: 'Documentos' },
];

const hasActiveFilters = computed(
    () => !!props.filters.q || !!props.filters.tipo || !!props.filters.coleccion,
);

function query(page?: number) {
    return {
        q: search.value || undefined,
        tipo: tipo.value === 'todos' ? undefined : tipo.value,
        coleccion: coleccion.value === ALL ? undefined : coleccion.value,
        orden: orden.value === 'reciente' ? undefined : orden.value,
        page: page && page > 1 ? page : undefined,
    };
}

const loadingList = ref(false);
let lastQueried = search.value;

function visit(page?: number) {
    clearSelection();
    lastQueried = search.value;
    router.get(multimedia.index().url, query(page), {
        preserveState: true,
        preserveScroll: page === undefined,
        replace: true,
        onStart: () => (loadingList.value = true),
        onFinish: () => (loadingList.value = false),
    });
}

function applyFilters() {
    clearTimeout(searchTimer);
    visit();
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    if (value === lastQueried) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(visit, 350);
});

watch(tipo, () => applyFilters());

function selectFolder(path: string) {
    coleccion.value = path;
    if (path !== ALL && path.startsWith('mr-insight/')) destinationPath.value = path;
    applyFilters();
}

function clearFilters() {
    search.value = '';
    coleccion.value = ALL;
    // Cambiar `tipo` dispara su propio watcher; si ya era «todos», visitamos aquí.
    if (tipo.value !== 'todos') tipo.value = 'todos';
    else applyFilters();
}

function goToPage(page: number) {
    visit(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Preferencia de vista por navegador (comodidad local, no estado crítico).
const VIEW_KEY = 'mrinsight.multimedia.vista';
const view = ref<'grid' | 'list'>('grid');
try {
    if (localStorage.getItem(VIEW_KEY) === 'list') view.value = 'list';
} catch {
    // Almacenamiento bloqueado: se usa la cuadrícula.
}
watch(view, (v) => {
    try {
        localStorage.setItem(VIEW_KEY, v);
    } catch {
        // Ignorado a propósito.
    }
});

// ---------------------------------------------------------------- Subida

const destinationPath = ref(
    props.filters.coleccion?.startsWith('mr-insight/') ? props.filters.coleccion : 'mr-insight/general',
);
const fileInput = ref<HTMLInputElement | null>(null);
const { progress, upload, cancel } = useChunkedUpload();
const ACCEPT = 'image/*,video/*,application/pdf,.vtt,.txt';

type QueueStatus = 'queued' | 'uploading' | 'done' | 'error' | 'aborted';
interface QueueItem {
    key: number;
    file: File;
    destination: string;
    status: QueueStatus;
    percent: number;
    error: string | null;
}

const queue = ref<QueueItem[]>([]);
let queueKey = 0;
let currentKey: number | null = null;
let processing = false;

const queueActive = computed(() => queue.value.some((q) => q.status === 'queued' || q.status === 'uploading'));
const queueDone = computed(() => queue.value.filter((q) => q.status === 'done').length);

watch(progress, (p) => {
    const item = queue.value.find((q) => q.key === currentKey);
    if (item && p) item.percent = p.percent;
});

function triggerUpload() {
    fileInput.value?.click();
}

function enqueue(files: File[]) {
    if (files.length === 0) return;
    for (const file of files) {
        queue.value.push({ key: ++queueKey, file, destination: destinationPath.value, status: 'queued', percent: 0, error: null });
    }
    void processQueue();
}

function onFileInput(event: Event) {
    const input = event.target as HTMLInputElement;
    enqueue(Array.from(input.files ?? []));
    input.value = '';
}

async function processQueue() {
    if (processing) return;
    processing = true;
    let ok = 0;
    let failed = 0;

    let next: QueueItem | undefined;
    while ((next = queue.value.find((q) => q.status === 'queued'))) {
        const item = next;
        currentKey = item.key;
        item.status = 'uploading';
        item.percent = 0;
        try {
            await upload(item.file, item.destination);
            item.status = 'done';
            item.percent = 100;
            ok++;
            router.reload({ only: ['media', 'storageBytes'] });
        } catch (error) {
            if (progress.value?.status === 'aborted') {
                item.status = 'aborted';
            } else {
                item.status = 'error';
                item.error = error instanceof Error ? error.message : 'No se pudo subir el archivo.';
                failed++;
            }
        }
    }

    currentKey = null;
    processing = false;

    if (ok > 0 && failed === 0) toast.success(ok === 1 ? 'Archivo subido a la biblioteca.' : `${ok} archivos subidos a la biblioteca.`);
    else if (failed > 0) toast.error(failed === 1 ? 'Un archivo no se pudo subir.' : `${failed} archivos no se pudieron subir.`);
}

function cancelUpload(item: QueueItem) {
    if (item.status === 'uploading') void cancel(item.file);
    else if (item.status === 'queued') item.status = 'aborted';
}

function retryUpload(item: QueueItem) {
    item.status = 'queued';
    item.error = null;
    item.percent = 0;
    void processQueue();
}

function clearFinished() {
    queue.value = queue.value.filter((q) => q.status === 'queued' || q.status === 'uploading');
}

// Arrastrar y soltar en toda la página.
const dragDepth = ref(0);
const dragging = computed(() => dragDepth.value > 0);

function hasFiles(event: DragEvent) {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files');
}

function onDragEnter(event: DragEvent) {
    if (!hasFiles(event)) return;
    event.preventDefault();
    dragDepth.value++;
}

function onDragOver(event: DragEvent) {
    if (!hasFiles(event)) return;
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
}

function onDragLeave(event: DragEvent) {
    if (!hasFiles(event)) return;
    dragDepth.value = Math.max(0, dragDepth.value - 1);
}

function onDrop(event: DragEvent) {
    if (!hasFiles(event)) return;
    event.preventDefault();
    dragDepth.value = 0;
    enqueue(Array.from(event.dataTransfer?.files ?? []));
}

// ------------------------------------------------------------- Selección

const selected = ref<Set<number>>(new Set());
const selectionMode = computed(() => selected.value.size > 0);
let lastSelectedIndex: number | null = null;

function toggleSelect(id: number, value?: boolean) {
    const next = new Set(selected.value);
    const shouldSelect = value ?? !next.has(id);
    if (shouldSelect) next.add(id);
    else next.delete(id);
    selected.value = next;
    lastSelectedIndex = items.value.findIndex((m) => m.id === id);
}

function selectRange(toIndex: number) {
    const from = lastSelectedIndex ?? toIndex;
    const [a, b] = from < toIndex ? [from, toIndex] : [toIndex, from];
    const next = new Set(selected.value);
    items.value.slice(a, b + 1).forEach((m) => next.add(m.id));
    selected.value = next;
    lastSelectedIndex = toIndex;
}

function clearSelection() {
    selected.value = new Set();
    lastSelectedIndex = null;
}

const allOnPageSelected = computed(() => items.value.length > 0 && items.value.every((m) => selected.value.has(m.id)));

function toggleAllOnPage() {
    selected.value = allOnPageSelected.value ? new Set() : new Set(items.value.map((m) => m.id));
}

/** Clic en una tarjeta: abre el detalle, o selecciona si ya hay selección / Ctrl·Cmd / Mayús. */
function onCardClick(item: MediaRow, index: number, event: MouseEvent) {
    if (event.shiftKey && selectionMode.value) {
        selectRange(index);
    } else if (selectionMode.value || event.metaKey || event.ctrlKey) {
        toggleSelect(item.id);
    } else {
        openDetail(item);
    }
}

// --------------------------------------------------------------- Detalle

const activeItem = ref<MediaRow | null>(null);
const altTextDraft = ref('');
const altState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
const altDirty = computed(() => !!activeItem.value && altTextDraft.value !== (activeItem.value.altText ?? ''));

const usages = ref<MediaUsage[] | null>(null);
const usagesLoading = ref(false);

async function loadUsages(id: number) {
    usages.value = null;
    usagesLoading.value = true;
    try {
        const response = await apiFetch(multimedia.uso(id).url);
        if (response.ok && activeItem.value?.id === id) usages.value = (await response.json()).usages;
    } finally {
        usagesLoading.value = false;
    }
}

function openDetail(item: MediaRow) {
    activeItem.value = item;
    altTextDraft.value = item.altText ?? '';
    altState.value = 'idle';
    void loadUsages(item.id);
}

function closeDetail() {
    activeItem.value = null;
}

async function saveAltText() {
    const item = activeItem.value;
    if (!item || !altDirty.value || altState.value === 'saving') return;

    altState.value = 'saving';
    const value = altTextDraft.value.trim();
    try {
        const response = await apiFetch(multimedia.update(item.id).url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ alt_text: value }),
        });
        if (!response.ok) throw new Error();

        item.altText = value;
        const row = items.value.find((m) => m.id === item.id);
        if (row) row.altText = value;
        altTextDraft.value = value;
        altState.value = 'saved';
        toast.success('Texto alternativo guardado.');
    } catch {
        altState.value = 'error';
        toast.error('No se pudo guardar el texto alternativo.');
    }
}

watch(altTextDraft, () => {
    if (altState.value === 'saved' || altState.value === 'error') altState.value = 'idle';
});

function absoluteUrl(url: string): string {
    try {
        return new URL(url, window.location.origin).href;
    } catch {
        return url;
    }
}

async function copyUrl(item: MediaRow) {
    if (!item.url) return;
    try {
        await navigator.clipboard.writeText(absoluteUrl(item.url));
        toast.success('URL pública copiada.');
    } catch {
        toast.error('No se pudo copiar. Abre el archivo y copia la dirección desde el navegador.');
    }
}

const DISK_LABELS: Record<string, string> = {
    media_local: 'Almacenamiento local',
    media_synology: 'Synology NAS',
};

// ------------------------------------------------------------ Eliminación

const deleteTargetIds = ref<number[]>([]);
const deleteUsages = ref<MediaUsage[] | null>(null);
const deleteConfirmOpen = ref(false);
const deleteChecking = ref(false);
const deleting = ref(false);

async function requestDelete(ids: number[]) {
    if (ids.length === 0 || deleteChecking.value) return;
    deleteChecking.value = true;
    deleteTargetIds.value = ids;

    const found: MediaUsage[] = [];
    try {
        for (const id of ids) {
            const response = await apiFetch(multimedia.uso(id).url);
            if (response.ok) {
                const payload = await response.json();
                found.push(...payload.usages);
            }
        }
    } finally {
        deleteChecking.value = false;
    }

    deleteUsages.value = found;
    deleteConfirmOpen.value = true;
}

async function finishDelete(ids: number[], force: boolean) {
    deleting.value = true;
    const deleted: number[] = [];
    try {
        for (const id of ids) {
            const response = await apiFetch(multimedia.destroy(id).url, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ force }),
            });
            if (response.ok) deleted.push(id);
        }
    } finally {
        deleting.value = false;
    }

    items.value = items.value.filter((m) => !deleted.includes(m.id));
    clearSelection();
    if (activeItem.value && deleted.includes(activeItem.value.id)) activeItem.value = null;
    deleteConfirmOpen.value = false;

    if (deleted.length === ids.length) {
        toast.success(deleted.length === 1 ? 'Archivo eliminado.' : `${deleted.length} archivos eliminados.`);
    } else {
        toast.error(`No se pudieron eliminar ${ids.length - deleted.length} de ${ids.length} archivos.`);
    }
    router.reload({ only: ['media', 'storageBytes'] });
}

function destroySelected() {
    void requestDelete(Array.from(selected.value));
}

function destroyOne(id: number) {
    void requestDelete([id]);
}

// --------------------------------------------------------------- Teclado

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && selectionMode.value && !activeItem.value && !deleteConfirmOpen.value) {
        clearSelection();
    }
}

onMounted(() => {
    window.addEventListener('dragenter', onDragEnter);
    window.addEventListener('dragover', onDragOver);
    window.addEventListener('dragleave', onDragLeave);
    window.addEventListener('drop', onDrop);
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('dragenter', onDragEnter);
    window.removeEventListener('dragover', onDragOver);
    window.removeEventListener('dragleave', onDragLeave);
    window.removeEventListener('drop', onDrop);
    window.removeEventListener('keydown', onKeydown);
    clearTimeout(searchTimer);
});
</script>

<template>
    <Head title="Multimedia" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Contenido"
            title="Biblioteca multimedia"
            description="Fotografías, videos y documentos que usan tus publicaciones, páginas y Studio. Arrastra archivos a cualquier parte de esta pantalla para subirlos."
        >
            <template #meta>
                <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    <span><span class="font-semibold text-foreground tabular-nums">{{ media.total }}</span> archivos</span>
                    <span aria-hidden="true">·</span>
                    <span><span class="font-semibold text-foreground tabular-nums">{{ formatBytes(storageBytes) }}</span> utilizados</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ activeProvider }}</span>
                </p>
            </template>
            <template #actions>
                <Button :class="TOUCH" @click="triggerUpload">
                    <UploadCloud class="size-4" />
                    Subir archivos
                </Button>
                <input ref="fileInput" type="file" multiple class="hidden" :accept="ACCEPT" @change="onFileInput" />
            </template>
        </AdminPageHeader>

        <div class="grid gap-6 lg:grid-cols-[232px_minmax(0,1fr)]">
            <!-- Barra lateral: carpetas + destino de subida (escritorio) -->
            <aside class="hidden flex-col gap-5 lg:flex">
                <nav aria-label="Carpetas de la biblioteca" class="flex flex-col gap-0.5">
                    <p class="mb-1 px-2 text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">Carpetas</p>
                    <button
                        type="button"
                        :aria-current="coleccion === ALL ? 'page' : undefined"
                        :class="
                            cn(
                                'flex min-h-10 items-center gap-2 rounded-md px-2 text-left text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none pointer-coarse:min-h-11',
                                coleccion === ALL && 'bg-card font-medium text-foreground shadow-xs ring-1 ring-border',
                            )
                        "
                        @click="selectFolder(ALL)"
                    >
                        <Library class="size-4 shrink-0" />
                        Toda la biblioteca
                    </button>
                    <template v-for="branch in tree" :key="branch.node.id">
                        <button
                            type="button"
                            :aria-current="coleccion === branch.node.path ? 'page' : undefined"
                            :class="
                                cn(
                                    'flex min-h-10 items-center gap-2 rounded-md px-2 text-left text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none pointer-coarse:min-h-11',
                                    coleccion === branch.node.path && 'bg-card font-medium text-foreground shadow-xs ring-1 ring-border',
                                )
                            "
                            @click="selectFolder(branch.node.path)"
                        >
                            <component :is="coleccion === branch.node.path ? FolderOpen : Folder" class="size-4 shrink-0" :class="coleccion === branch.node.path && 'text-brand'" />
                            <span class="truncate">{{ branch.node.name }}</span>
                        </button>
                        <button
                            v-for="child in branch.children"
                            :key="child.id"
                            type="button"
                            :aria-current="coleccion === child.path ? 'page' : undefined"
                            :class="
                                cn(
                                    'ml-4 flex min-h-9 items-center gap-2 rounded-md border-l px-2 text-left text-[13px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none pointer-coarse:min-h-11',
                                    coleccion === child.path && 'border-brand bg-card font-medium text-foreground shadow-xs',
                                )
                            "
                            @click="selectFolder(child.path)"
                        >
                            <span class="truncate">{{ child.name }}</span>
                        </button>
                    </template>
                </nav>

                <section class="rounded-xl border bg-card p-4" aria-labelledby="destino-titulo">
                    <h2 id="destino-titulo" class="mb-3 text-sm font-semibold">Destino de subida</h2>
                    <DestinationPicker
                        v-model:path="destinationPath"
                        :collections="collections"
                        :provider-label="activeProvider"
                        stacked
                        id-prefix="destino-lateral"
                    />
                </section>
            </aside>

            <div class="flex min-w-0 flex-col gap-4">
                <!-- Barra de filtros -->
                <div class="flex flex-col gap-3 rounded-xl border bg-card p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[200px] flex-1">
                            <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="search"
                                type="search"
                                aria-label="Buscar archivos por nombre"
                                placeholder="Buscar por nombre…"
                                :class="cn('pl-8', TOUCH)"
                                @keydown.enter.prevent="applyFilters"
                            />
                        </div>
                        <Select v-model="orden" @update:model-value="applyFilters">
                            <SelectTrigger aria-label="Ordenar" :class="cn('w-[168px]', TOUCH_SELECT)"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="reciente">Más recientes</SelectItem>
                                <SelectItem value="tamano">Mayor tamaño</SelectItem>
                                <SelectItem value="nombre">Nombre (A-Z)</SelectItem>
                            </SelectContent>
                        </Select>
                        <div role="radiogroup" aria-label="Vista" class="flex rounded-md border p-0.5">
                            <Button
                                v-for="option in [{ v: 'grid', l: 'Cuadrícula', i: LayoutGrid }, { v: 'list', l: 'Lista', i: List }] as const"
                                :key="option.v"
                                role="radio"
                                :aria-checked="view === option.v"
                                :aria-label="option.l"
                                :title="option.l"
                                :variant="view === option.v ? 'secondary' : 'ghost'"
                                size="icon-sm"
                                :class="TOUCH_ICON"
                                @click="view = option.v"
                            >
                                <component :is="option.i" class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <SegmentedControl
                            v-model="tipo"
                            :options="TYPE_OPTIONS"
                            label="Tipo de archivo"
                            class="sm:max-w-md pointer-coarse:[&>button]:min-h-11"
                        />
                        <!-- Carpetas en móvil/tablet -->
                        <Select :model-value="coleccion" @update:model-value="(v) => selectFolder(String(v))">
                            <SelectTrigger aria-label="Carpeta" :class="cn('w-full sm:w-56 lg:hidden', TOUCH_SELECT)">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL">Toda la biblioteca</SelectItem>
                                <template v-for="branch in tree" :key="branch.node.id">
                                    <SelectItem :value="branch.node.path">{{ branch.node.name }}</SelectItem>
                                    <SelectItem v-for="child in branch.children" :key="child.id" :value="child.path">
                                        <span class="pl-3 text-muted-foreground">{{ branch.node.name }} ›</span> {{ child.name }}
                                    </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
                        <Button v-if="hasActiveFilters" variant="ghost" size="sm" :class="TOUCH" @click="clearFilters">
                            <X class="size-4" /> Limpiar filtros
                        </Button>
                        <Loader2 v-if="loadingList" class="ml-auto size-4 animate-spin text-muted-foreground" aria-label="Cargando" />
                    </div>
                    <!-- Destino de subida en móvil/tablet -->
                    <details class="group rounded-lg border bg-muted/30 lg:hidden">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 px-3 text-sm font-medium [&::-webkit-details-marker]:hidden">
                            <FolderOpen class="size-4 text-brand" /> Destino de subida
                            <ChevronRight class="ml-auto size-4 text-muted-foreground transition-transform group-open:rotate-90" />
                        </summary>
                        <div class="border-t p-3">
                            <DestinationPicker v-model:path="destinationPath" :collections="collections" :provider-label="activeProvider" id-prefix="destino-movil" />
                        </div>
                    </details>
                </div>

                <!-- Cola de subida -->
                <section v-if="queue.length" class="rounded-xl border bg-card" aria-labelledby="cola-titulo" aria-live="polite">
                    <header class="flex items-center justify-between gap-3 border-b px-4 py-2.5">
                        <h2 id="cola-titulo" class="flex items-center gap-2 text-sm font-semibold">
                            <Loader2 v-if="queueActive" class="size-4 animate-spin text-brand" />
                            <CheckCircle2 v-else class="size-4 text-success" />
                            {{ queueActive ? 'Subiendo archivos' : 'Subida terminada' }}
                            <span class="font-normal text-muted-foreground tabular-nums">{{ queueDone }}/{{ queue.length }}</span>
                        </h2>
                        <Button v-if="!queueActive" variant="ghost" size="sm" :class="TOUCH" @click="clearFinished">Cerrar</Button>
                    </header>
                    <ul class="max-h-72 divide-y overflow-y-auto">
                        <li v-for="q in queue" :key="q.key" class="flex items-center gap-3 px-4 py-2.5">
                            <span
                                class="grid size-8 shrink-0 place-items-center rounded-md"
                                :class="{
                                    'bg-muted text-muted-foreground': q.status === 'queued' || q.status === 'aborted',
                                    'bg-brand/15 text-brand': q.status === 'uploading',
                                    'bg-success/12 text-success': q.status === 'done',
                                    'bg-destructive/10 text-destructive': q.status === 'error',
                                }"
                            >
                                <Loader2 v-if="q.status === 'uploading'" class="size-4 animate-spin" />
                                <Check v-else-if="q.status === 'done'" class="size-4" />
                                <XCircle v-else-if="q.status === 'error'" class="size-4" />
                                <UploadCloud v-else class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="truncate text-sm font-medium">{{ q.file.name }}</p>
                                    <span class="shrink-0 text-xs text-muted-foreground tabular-nums">
                                        <template v-if="q.status === 'uploading'">{{ q.percent }}%</template>
                                        <template v-else-if="q.status === 'queued'">En cola</template>
                                        <template v-else-if="q.status === 'done'">Listo</template>
                                        <template v-else-if="q.status === 'aborted'">Cancelado</template>
                                        <template v-else>Error</template>
                                    </span>
                                </div>
                                <div
                                    class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted"
                                    role="progressbar"
                                    :aria-label="`Progreso de ${q.file.name}`"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    :aria-valuenow="q.percent"
                                >
                                    <div
                                        class="h-full rounded-full transition-[width] duration-300"
                                        :class="q.status === 'error' ? 'bg-destructive' : q.status === 'done' ? 'bg-success' : 'bg-brand'"
                                        :style="{ width: `${q.status === 'error' ? 100 : q.percent}%` }"
                                    />
                                </div>
                                <p class="mt-1 truncate text-xs" :class="q.status === 'error' ? 'text-destructive' : 'text-muted-foreground'">
                                    {{ q.status === 'error' ? q.error : `${formatBytes(q.file.size)} · ${folderLabel(q.destination)}` }}
                                </p>
                            </div>
                            <Button
                                v-if="q.status === 'uploading' || q.status === 'queued'"
                                variant="ghost"
                                size="icon-sm"
                                :class="TOUCH_ICON"
                                :aria-label="`Cancelar ${q.file.name}`"
                                @click="cancelUpload(q)"
                            >
                                <X class="size-4" />
                            </Button>
                            <Button
                                v-else-if="q.status === 'error' || q.status === 'aborted'"
                                variant="ghost"
                                size="icon-sm"
                                :class="TOUCH_ICON"
                                :aria-label="`Reintentar ${q.file.name}`"
                                @click="retryUpload(q)"
                            >
                                <RotateCcw class="size-4" />
                            </Button>
                        </li>
                    </ul>
                </section>

                <!-- Barra de selección -->
                <div
                    v-if="selectionMode"
                    class="sticky top-2 z-20 flex flex-wrap items-center gap-2 rounded-xl border border-brand/40 bg-card px-3 py-2 shadow-sm"
                    role="region"
                    aria-label="Acciones de selección"
                >
                    <span class="text-sm font-medium tabular-nums">{{ selected.size }} {{ selected.size === 1 ? 'seleccionado' : 'seleccionados' }}</span>
                    <span class="hidden text-xs text-muted-foreground md:inline">Toca otros archivos para añadirlos · Mayús + clic selecciona un rango · Esc para salir</span>
                    <div class="ml-auto flex items-center gap-1">
                        <Button variant="ghost" size="sm" :class="TOUCH" @click="toggleAllOnPage">
                            {{ allOnPageSelected ? 'Quitar página' : 'Seleccionar página' }}
                        </Button>
                        <Button variant="ghost" size="sm" :class="TOUCH" @click="clearSelection">Cancelar</Button>
                        <Button variant="destructive" size="sm" :class="TOUCH" :disabled="deleteChecking" @click="destroySelected">
                            <Loader2 v-if="deleteChecking" class="size-4 animate-spin" />
                            <Trash2 v-else class="size-4" />
                            Eliminar
                        </Button>
                    </div>
                </div>

                <!-- Estados vacíos -->
                <EmptyState
                    v-if="items.length === 0 && hasActiveFilters"
                    :icon="Search"
                    title="No hay archivos con estos filtros"
                    description="Prueba con otro nombre, tipo o carpeta."
                >
                    <Button variant="outline" :class="TOUCH" @click="clearFilters">Limpiar filtros</Button>
                </EmptyState>
                <EmptyState
                    v-else-if="items.length === 0"
                    :icon="UploadCloud"
                    title="Tu biblioteca está vacía"
                    description="Sube fotografías, videos o documentos. También puedes arrastrarlos a cualquier parte de esta pantalla."
                >
                    <Button :class="TOUCH" @click="triggerUpload"><UploadCloud class="size-4" /> Subir archivos</Button>
                </EmptyState>

                <!-- Cuadrícula -->
                <ul
                    v-else-if="view === 'grid'"
                    class="grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 md:gap-4 xl:grid-cols-4 2xl:grid-cols-5"
                    :class="loadingList && 'opacity-60'"
                    aria-label="Archivos"
                >
                    <li
                        v-for="(item, index) in items"
                        :key="item.id"
                        :class="
                            cn(
                                'group relative overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-md',
                                selected.has(item.id) && 'border-brand ring-2 ring-brand',
                            )
                        "
                    >
                        <button
                            type="button"
                            class="block w-full text-left focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:ring-inset"
                            :aria-label="selectionMode ? `${selected.has(item.id) ? 'Quitar de' : 'Añadir a'} la selección: ${item.filename}` : `Ver detalles de ${item.filename}`"
                            @click="onCardClick(item, index, $event)"
                        >
                            <span class="relative block aspect-square overflow-hidden bg-muted">
                                <img
                                    v-if="thumbOf(item)"
                                    :src="thumbOf(item)!"
                                    :alt="item.altText ?? ''"
                                    loading="lazy"
                                    decoding="async"
                                    class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                                />
                                <span v-else class="flex size-full flex-col items-center justify-center gap-2 text-muted-foreground">
                                    <component :is="TYPE_ICONS[item.type] ?? FileText" class="size-8" />
                                    <span class="text-[11px] font-semibold tracking-wider">{{ extension(item) }}</span>
                                </span>
                                <span
                                    v-if="item.type === 'video' && !statusOverlay(item)"
                                    class="absolute right-2 bottom-2 inline-flex items-center gap-1 rounded-full bg-black/70 px-2 py-0.5 text-[11px] font-semibold text-white tabular-nums"
                                >
                                    <Play class="size-3 fill-current" />
                                    {{ formatDuration(item.durationSeconds) || 'Video' }}
                                </span>
                                <span
                                    v-if="statusOverlay(item)"
                                    class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/60 text-xs font-semibold text-white"
                                >
                                    <Loader2 v-if="item.status !== 'failed'" class="size-5 animate-spin" />
                                    <AlertTriangle v-else class="size-5" />
                                    {{ statusOverlay(item) }}
                                </span>
                                <span v-if="selected.has(item.id)" class="absolute inset-0 bg-brand/15" aria-hidden="true" />
                            </span>
                            <span class="flex flex-col gap-0.5 px-3 py-2.5">
                                <span class="truncate text-sm font-medium">{{ item.filename }}</span>
                                <span class="truncate text-xs text-muted-foreground tabular-nums">{{ cardMeta(item) }}</span>
                            </span>
                        </button>
                        <!-- Casilla de selección: área táctil de 44 px, visible con teclado, táctil o selección activa -->
                        <label
                            :class="
                                cn(
                                    'absolute top-0 left-0 grid size-11 cursor-pointer place-items-center transition-opacity',
                                    selectionMode || selected.has(item.id)
                                        ? 'opacity-100'
                                        : 'opacity-0 group-hover:opacity-100 focus-within:opacity-100 pointer-coarse:opacity-100',
                                )
                            "
                        >
                            <span class="grid size-6 place-items-center rounded-md bg-background/90 shadow-sm">
                                <Checkbox
                                    :model-value="selected.has(item.id)"
                                    :aria-label="`Seleccionar ${item.filename}`"
                                    @update:model-value="(v) => toggleSelect(item.id, v === true)"
                                />
                            </span>
                        </label>
                    </li>
                </ul>

                <!-- Lista -->
                <template v-else>
                    <div class="hidden overflow-hidden rounded-xl border bg-card md:block" :class="loadingList && 'opacity-60'">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">
                                        <Checkbox
                                            :model-value="allOnPageSelected"
                                            aria-label="Seleccionar todos los archivos de esta página"
                                            @update:model-value="toggleAllOnPage"
                                        />
                                    </TableHead>
                                    <TableHead>Archivo</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead class="hidden xl:table-cell">Carpeta</TableHead>
                                    <TableHead class="text-right">Tamaño</TableHead>
                                    <TableHead class="hidden lg:table-cell">Subido</TableHead>
                                    <TableHead class="w-28 text-right"><span class="sr-only">Acciones</span></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="item in items"
                                    :key="item.id"
                                    :data-state="selected.has(item.id) ? 'selected' : undefined"
                                    class="h-14"
                                >
                                    <TableCell>
                                        <Checkbox
                                            :model-value="selected.has(item.id)"
                                            :aria-label="`Seleccionar ${item.filename}`"
                                            @update:model-value="(v) => toggleSelect(item.id, v === true)"
                                        />
                                    </TableCell>
                                    <TableCell class="max-w-0 min-w-56">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 rounded-md text-left focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                            @click="openDetail(item)"
                                        >
                                            <span class="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-md bg-muted text-muted-foreground">
                                                <img v-if="thumbOf(item)" :src="thumbOf(item)!" alt="" loading="lazy" class="size-full object-cover" />
                                                <component :is="TYPE_ICONS[item.type] ?? FileText" v-else class="size-4" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium">{{ item.filename }}</span>
                                                <span class="block truncate text-xs text-muted-foreground">
                                                    {{ statusOverlay(item) ?? (item.type === 'video' ? formatDuration(item.durationSeconds) : dimensions(item)) }}
                                                </span>
                                            </span>
                                        </button>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">{{ TYPE_LABELS[item.type] ?? item.type }}</TableCell>
                                    <TableCell class="hidden max-w-48 truncate text-muted-foreground xl:table-cell">{{ itemFolder(item) }}</TableCell>
                                    <TableCell class="text-right tabular-nums">{{ formatBytes(item.size) }}</TableCell>
                                    <TableCell class="hidden text-muted-foreground lg:table-cell">{{ formatDate(item.createdAt) }}</TableCell>
                                    <TableCell class="text-right">
                                        <Button variant="ghost" size="icon-sm" :class="TOUCH_ICON" :aria-label="`Detalles de ${item.filename}`" @click="openDetail(item)">
                                            <Info class="size-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon-sm" :class="TOUCH_ICON" :aria-label="`Eliminar ${item.filename}`" @click="destroyOne(item.id)">
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <!-- Lista en móvil: tarjetas -->
                    <ul class="flex flex-col divide-y overflow-hidden rounded-xl border bg-card md:hidden" aria-label="Archivos">
                        <li v-for="(item, index) in items" :key="item.id" :class="cn('flex items-center gap-1 pr-2', selected.has(item.id) && 'bg-brand/8')">
                            <label class="grid size-11 shrink-0 cursor-pointer place-items-center">
                                <Checkbox
                                    :model-value="selected.has(item.id)"
                                    :aria-label="`Seleccionar ${item.filename}`"
                                    @update:model-value="(v) => toggleSelect(item.id, v === true)"
                                />
                            </label>
                            <button type="button" class="flex min-h-16 min-w-0 flex-1 items-center gap-3 py-2 text-left" @click="onCardClick(item, index, $event)">
                                <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-md bg-muted text-muted-foreground">
                                    <img v-if="thumbOf(item)" :src="thumbOf(item)!" alt="" loading="lazy" class="size-full object-cover" />
                                    <component :is="TYPE_ICONS[item.type] ?? FileText" v-else class="size-5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ item.filename }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">
                                        {{ TYPE_LABELS[item.type] ?? item.type }} · {{ statusOverlay(item) ?? cardMeta(item) }}
                                    </span>
                                </span>
                                <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                            </button>
                        </li>
                    </ul>
                </template>

                <!-- Paginación -->
                <nav
                    v-if="media.lastPage > 1"
                    class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
                    aria-label="Paginación"
                >
                    <span>Página {{ media.currentPage }} de {{ media.lastPage }} · {{ media.total }} archivos</span>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" :class="TOUCH" :disabled="media.currentPage <= 1" @click="goToPage(media.currentPage - 1)">
                            <ChevronLeft class="size-4" /> Anterior
                        </Button>
                        <Button variant="outline" size="sm" :class="TOUCH" :disabled="media.currentPage >= media.lastPage" @click="goToPage(media.currentPage + 1)">
                            Siguiente <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </nav>
            </div>
        </div>
    </div>

    <!-- Zona de soltar a pantalla completa -->
    <Transition
        enter-active-class="transition-opacity duration-150"
        leave-active-class="transition-opacity duration-150"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
    >
        <div v-if="dragging" class="pointer-events-none fixed inset-0 z-[60] grid place-items-center bg-background/85 p-6 backdrop-blur-sm" aria-hidden="true">
            <div class="flex w-full max-w-lg flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-brand bg-card px-8 py-14 text-center shadow-lg">
                <span class="grid size-14 place-items-center rounded-full bg-brand/15 text-brand"><UploadCloud class="size-6" /></span>
                <p class="font-display text-2xl font-semibold">Suelta para subir</p>
                <p class="text-sm text-muted-foreground">
                    Se guardará en <span class="font-medium text-foreground">{{ folderLabel(destinationPath) }}</span> · {{ activeProvider }}
                </p>
            </div>
        </div>
    </Transition>

    <!-- Detalle -->
    <Sheet :open="activeItem !== null" @update:open="(v: boolean) => !v && closeDetail()">
        <SheetContent v-if="activeItem" class="w-full gap-0 overflow-y-auto sm:max-w-xl">
            <SheetHeader class="border-b pr-12">
                <SheetTitle class="truncate">{{ activeItem.filename }}</SheetTitle>
                <SheetDescription>
                    {{ TYPE_LABELS[activeItem.type] ?? activeItem.type }} · subido el {{ formatDate(activeItem.createdAt, true) }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex flex-col gap-6 p-4">
                <!-- Vista previa -->
                <div class="relative grid min-h-48 place-items-center overflow-hidden rounded-xl border bg-muted">
                    <img
                        v-if="activeItem.type === 'image' && activeItem.url"
                        :src="activeItem.url"
                        :alt="activeItem.altText ?? ''"
                        class="max-h-[55vh] w-full object-contain"
                    />
                    <video
                        v-else-if="activeItem.type === 'video' && activeItem.url && activeItem.status === 'ready'"
                        :key="activeItem.id"
                        :src="activeItem.url"
                        :poster="activeItem.posterUrl ?? undefined"
                        controls
                        playsinline
                        preload="metadata"
                        class="max-h-[55vh] w-full bg-black"
                    />
                    <div v-else class="flex flex-col items-center gap-2 py-12 text-muted-foreground">
                        <component :is="TYPE_ICONS[activeItem.type] ?? FileText" class="size-10" />
                        <span class="text-xs font-semibold tracking-wider">{{ extension(activeItem) }}</span>
                    </div>
                    <span
                        v-if="statusOverlay(activeItem)"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/60 text-sm font-semibold text-white"
                    >
                        <Loader2 v-if="activeItem.status !== 'failed'" class="size-5 animate-spin" />
                        <AlertTriangle v-else class="size-5" />
                        {{ statusOverlay(activeItem) }}
                    </span>
                </div>

                <div v-if="activeItem.url" class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" :class="TOUCH" @click="copyUrl(activeItem)">
                        <Copy class="size-4" /> Copiar URL pública
                    </Button>
                    <Button as-child variant="outline" size="sm" :class="TOUCH">
                        <a :href="activeItem.url" target="_blank" rel="noopener"><ExternalLink class="size-4" /> Abrir original</a>
                    </Button>
                </div>

                <!-- Metadatos -->
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 rounded-xl border p-4 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Formato</dt>
                        <dd class="font-medium">{{ extension(activeItem) }} <span class="font-normal text-muted-foreground">· {{ activeItem.mimeType }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Tamaño</dt>
                        <dd class="font-medium tabular-nums">{{ formatBytes(activeItem.size) }}</dd>
                    </div>
                    <div v-if="activeItem.width">
                        <dt class="text-xs text-muted-foreground">Dimensiones</dt>
                        <dd class="font-medium tabular-nums">{{ dimensions(activeItem) }} px</dd>
                    </div>
                    <div v-if="activeItem.durationSeconds">
                        <dt class="text-xs text-muted-foreground">Duración</dt>
                        <dd class="font-medium tabular-nums">{{ formatDuration(activeItem.durationSeconds) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Carpeta</dt>
                        <dd class="font-medium">{{ itemFolder(activeItem) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Almacenamiento</dt>
                        <dd class="font-medium">{{ DISK_LABELS[activeItem.disk] ?? activeProvider }}</dd>
                    </div>
                </dl>

                <!-- Texto alternativo -->
                <form class="flex flex-col gap-2" @submit.prevent="saveAltText">
                    <div class="flex items-center justify-between gap-2">
                        <Label for="alt-text">Texto alternativo</Label>
                        <span class="text-xs text-muted-foreground tabular-nums">{{ altTextDraft.length }}/255</span>
                    </div>
                    <Textarea
                        id="alt-text"
                        v-model="altTextDraft"
                        rows="3"
                        maxlength="255"
                        placeholder="Ej.: Retrato de Mr. Insight frente a un librero, luz cálida de tarde."
                        aria-describedby="alt-text-help"
                        :aria-invalid="altState === 'error' || undefined"
                        @blur="saveAltText"
                        @keydown.enter.exact.prevent="saveAltText"
                    />
                    <p id="alt-text-help" class="text-xs text-muted-foreground">Describe lo que se ve para lectores de pantalla y buscadores. Enter para guardar.</p>
                    <div class="flex items-center gap-3">
                        <Button type="submit" size="sm" :class="TOUCH" :disabled="!altDirty || altState === 'saving'" :aria-busy="altState === 'saving'">
                            <Loader2 v-if="altState === 'saving'" class="size-4 animate-spin" />
                            Guardar
                        </Button>
                        <span v-if="altState === 'saved' && !altDirty" class="inline-flex items-center gap-1.5 text-xs text-success" role="status">
                            <Check class="size-3.5" /> Guardado
                        </span>
                        <span v-else-if="altState === 'error'" class="text-xs text-destructive" role="alert">No se guardó. Intenta de nuevo.</span>
                        <span v-else-if="altDirty" class="text-xs text-muted-foreground">Cambios sin guardar</span>
                    </div>
                </form>

                <!-- Usos -->
                <section class="flex flex-col gap-2" aria-labelledby="usos-titulo">
                    <h3 id="usos-titulo" class="text-sm font-semibold">Dónde se usa</h3>
                    <p v-if="usagesLoading" class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 class="size-4 animate-spin" /> Buscando usos…
                    </p>
                    <p v-else-if="usages && usages.length === 0" class="rounded-lg bg-muted/50 px-3 py-2.5 text-sm text-muted-foreground">
                        Ningún contenido usa este archivo todavía.
                    </p>
                    <ul v-else-if="usages" class="flex flex-col divide-y rounded-xl border">
                        <li v-for="(u, i) in usages" :key="i">
                            <Link :href="u.href" class="flex min-h-12 items-center gap-3 px-3 py-2 text-sm transition-colors hover:bg-muted/50 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ u.label }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ u.type }}</span>
                                </span>
                                <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">No se pudo consultar dónde se usa.</p>
                </section>

                <div class="border-t pt-4">
                    <Button
                        variant="outline"
                        :class="cn('w-full border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive', TOUCH)"
                        :disabled="deleteChecking"
                        @click="destroyOne(activeItem.id)"
                    >
                        <Loader2 v-if="deleteChecking" class="size-4 animate-spin" />
                        <Trash2 v-else class="size-4" />
                        Eliminar archivo
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <!-- Confirmación de eliminación (con aviso de usos) -->
    <AlertDialog v-model:open="deleteConfirmOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    <template v-if="deleteUsages && deleteUsages.length > 0">¿Eliminar de todas formas?</template>
                    <template v-else>{{ deleteTargetIds.length > 1 ? `¿Eliminar ${deleteTargetIds.length} archivos?` : '¿Eliminar este archivo?' }}</template>
                </AlertDialogTitle>
                <AlertDialogDescription as-child>
                    <div class="flex flex-col gap-3 text-sm text-muted-foreground">
                        <p v-if="!deleteUsages || deleteUsages.length === 0">Esta acción no se puede deshacer.</p>
                        <template v-else>
                            <p class="flex items-start gap-2 rounded-lg bg-warning/12 px-3 py-2 text-foreground">
                                <AlertTriangle class="mt-0.5 size-4 shrink-0 text-warning" />
                                <span>
                                    {{ deleteTargetIds.length > 1 ? 'Algunos de estos archivos están en uso.' : 'Este archivo está en uso.' }}
                                    Si lo eliminas, estos contenidos quedarán sin imagen. Esta acción no se puede deshacer.
                                </span>
                            </p>
                            <ul class="flex max-h-56 flex-col divide-y overflow-y-auto rounded-lg border">
                                <li v-for="(u, i) in deleteUsages" :key="i" class="flex items-center gap-2 px-3 py-2">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium text-foreground">{{ u.label }}</span>
                                        <span class="block text-xs">{{ u.type }}</span>
                                    </span>
                                    <Link :href="u.href" class="shrink-0 text-xs font-medium text-foreground underline-offset-4 hover:underline">Abrir</Link>
                                </li>
                            </ul>
                        </template>
                    </div>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="deleting">Cancelar</AlertDialogCancel>
                <Button variant="destructive" :disabled="deleting" :aria-busy="deleting" @click="finishDelete(deleteTargetIds, true)">
                    <Loader2 v-if="deleting" class="size-4 animate-spin" />
                    {{ deleteUsages && deleteUsages.length > 0 ? 'Eliminar de todas formas' : 'Eliminar' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
