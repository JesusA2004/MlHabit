<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Camera,
    FileText,
    Film,
    ImageOff,
    Images,
    LayoutGrid,
    List,
    Lock,
    PenTool,
    Upload,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataPagination from '@/components/ds/DataPagination.vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FileDropzone from '@/components/ds/FileDropzone.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatBytes } from '@/components/web/admin/media/format';
import MediaDetailSheet from '@/components/web/admin/media/MediaDetailSheet.vue';
import { cn } from '@/lib/utils';
import multimedia from '@/routes/admin/multimedia';
import type {
    MediaItem,
    SimplePaginator,
    ToneOption,
    UploadLimits,
} from '@/types/web';

type Filters = {
    q: string;
    tipo: string | null;
    coleccion: string | null;
    visibilidad: string;
    origen: string | null;
    orden: string;
    vista: 'cuadricula' | 'lista';
};

const props = defineProps<{
    media: SimplePaginator<MediaItem>;
    filters: Filters;
    collections: {
        id: number;
        parentId: number | null;
        name: string;
        path: string;
        count: number;
    }[];
    stats: { storageBytes: number; count: number; activeProvider: string };
    limits: UploadLimits;
    originOptions: ToneOption[];
    can: { upload: boolean; viewPrivate: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Multimedia', href: '/admin/multimedia' },
        ],
    },
});

const search = ref(props.filters.q);
const type = ref(props.filters.tipo ?? 'todos');
const collection = ref<string | null>(props.filters.coleccion);
const origin = ref(props.filters.origen ?? 'todos');
const visibility = ref(props.filters.visibilidad);
const sort = ref(props.filters.orden);
const view = ref<'cuadricula' | 'lista'>(props.filters.vista);

function reload() {
    const query: Record<string, string> = {};
    if (search.value) query.q = search.value;
    if (type.value !== 'todos') query.tipo = type.value;
    if (collection.value) query.coleccion = collection.value;
    if (origin.value !== 'todos') query.origen = origin.value;
    if (visibility.value === 'private') query.visibilidad = 'private';
    if (sort.value !== 'reciente') query.orden = sort.value;
    if (view.value === 'lista') query.vista = 'lista';
    router.get(multimedia.index().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['media', 'filters'],
    });
}
watch([search, type, collection, origin, visibility, sort, view], reload);

const activeFilters = computed(
    () =>
        (type.value !== 'todos' ? 1 : 0) +
        (collection.value ? 1 : 0) +
        (origin.value !== 'todos' ? 1 : 0) +
        (visibility.value === 'private' ? 1 : 0),
);

function clearFilters() {
    type.value = 'todos';
    collection.value = null;
    origin.value = 'todos';
    visibility.value = 'public';
}

const collectionOptions = computed(() =>
    props.collections.map((c) => ({
        value: c.path,
        label: c.path.split('/').join(' / '),
        description: `${c.count} archivos`,
    })),
);

// Subida
const uploadOpen = ref(false);
const uploadCollection = ref<string | null>('sitio/general');
const uploadOptions = computed(() =>
    collectionOptions.value.filter((c) => !c.value.startsWith('privado')),
);
const acceptList = computed(() => props.limits.extensions.map((e) => `.${e}`));

function onUploaded() {
    router.reload({ only: ['media', 'stats', 'collections'] });
}

// Detalle
const selected = ref<MediaItem | null>(null);
const detailOpen = ref(false);
function openDetail(item: MediaItem) {
    selected.value = item;
    detailOpen.value = true;
}
function onChanged() {
    router.reload({
        only: ['media'],
        onSuccess: () => {
            if (selected.value)
                selected.value =
                    props.media.data.find((m) => m.id === selected.value?.id) ??
                    selected.value;
        },
    });
}

const columns: DataColumn<MediaItem>[] = [
    { key: 'filename', label: 'Archivo', primary: true },
    { key: 'type', label: 'Tipo' },
    { key: 'size', label: 'Tamaño', align: 'right' },
    {
        key: 'collection',
        label: 'Colección',
        value: (r) => r.collection?.path ?? '—',
        hideOnMobile: true,
    },
    { key: 'createdAt', label: 'Subido', hideOnMobile: true },
];

const typeLabel = (t: string) =>
    ({ image: 'Imagen', video: 'Video', document: 'Documento' })[t] ?? 'Otro';
</script>

<template>
    <Head title="Multimedia" />

    <PageContainer width="wide" class="flex flex-col gap-6">
        <PageHeader
            title="Multimedia"
            description="Biblioteca de imágenes, videos y documentos del sitio. Las imágenes se optimizan automáticamente en varios tamaños."
        >
            <template #meta>
                <span
                    >{{ props.stats.count }} archivos públicos ·
                    {{ formatBytes(props.stats.storageBytes) }}</span
                >
                <span>· {{ props.stats.activeProvider }}</span>
            </template>
            <template v-if="props.can.upload" #actions>
                <Button @click="uploadOpen = !uploadOpen">
                    <X v-if="uploadOpen" class="size-4" /><Upload
                        v-else
                        class="size-4"
                    />
                    {{ uploadOpen ? 'Cerrar' : 'Subir archivos' }}
                </Button>
            </template>
        </PageHeader>

        <section
            v-if="uploadOpen"
            class="grid gap-3 rounded-xl border border-border bg-card p-4"
            aria-label="Subir archivos"
        >
            <div class="grid gap-1.5 sm:max-w-sm">
                <Label for="upload-collection">Guardar en la colección</Label>
                <SearchSelect
                    id="upload-collection"
                    v-model="uploadCollection"
                    :options="uploadOptions"
                    placeholder="sitio / general"
                />
            </div>
            <FileDropzone
                :accept="acceptList"
                :max-size="props.limits.maxUploadMb * 1024 * 1024"
                :collection="uploadCollection ?? 'sitio/general'"
                visibility="public"
                :hint="`JPG, PNG, WebP, AVIF, GIF, PDF, MP4, WebM o MOV. Imágenes hasta ${props.limits.maxImageMb} MB, PDF hasta ${props.limits.maxDocumentMb} MB y video hasta ${props.limits.maxUploadMb} MB.`"
                @all-completed="onUploaded"
            />
        </section>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <FilterBar
                v-model:search="search"
                class="flex-1"
                placeholder="Buscar por nombre, título o texto alternativo"
                :active-count="activeFilters"
                @clear="clearFilters"
            >
                <template #filters>
                    <Select v-model="type">
                        <SelectTrigger class="w-full md:w-36" aria-label="Tipo"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos"
                                >Todos los tipos</SelectItem
                            >
                            <SelectItem value="image">Imágenes</SelectItem>
                            <SelectItem value="video">Videos</SelectItem>
                            <SelectItem value="document">Documentos</SelectItem>
                        </SelectContent>
                    </Select>
                    <SearchSelect
                        v-model="collection"
                        :options="collectionOptions"
                        clearable
                        placeholder="Todas las colecciones"
                        class="w-full md:w-56"
                    />
                    <Select v-model="origin">
                        <SelectTrigger
                            class="w-full md:w-44"
                            aria-label="Origen"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos"
                                >Cualquier origen</SelectItem
                            >
                            <SelectItem value="render"
                                >Render conceptual</SelectItem
                            >
                            <SelectItem value="real_photo"
                                >Proyecto real</SelectItem
                            >
                            <SelectItem value="sin">Sin indicar</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-if="props.can.viewPrivate" v-model="visibility">
                        <SelectTrigger
                            class="w-full md:w-36"
                            aria-label="Visibilidad"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="public">Públicos</SelectItem>
                            <SelectItem value="private">Privados</SelectItem>
                        </SelectContent>
                    </Select>
                </template>
            </FilterBar>
            <div class="flex items-center gap-2">
                <Select v-model="sort">
                    <SelectTrigger class="w-40" aria-label="Ordenar"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="reciente">Más recientes</SelectItem>
                        <SelectItem value="antiguo">Más antiguos</SelectItem>
                        <SelectItem value="nombre">Nombre</SelectItem>
                        <SelectItem value="tamano">Tamaño</SelectItem>
                    </SelectContent>
                </Select>
                <ToggleGroup
                    v-model="view"
                    type="single"
                    variant="outline"
                    aria-label="Vista"
                >
                    <ToggleGroupItem value="cuadricula" aria-label="Cuadrícula"
                        ><LayoutGrid class="size-4"
                    /></ToggleGroupItem>
                    <ToggleGroupItem value="lista" aria-label="Lista"
                        ><List class="size-4"
                    /></ToggleGroupItem>
                </ToggleGroup>
            </div>
        </div>

        <p
            v-if="visibility === 'private'"
            class="flex items-center gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning-ink"
        >
            <Lock class="size-4" aria-hidden="true" /> Estás viendo archivos
            privados de clientes. Trátalos con confidencialidad.
        </p>

        <template v-if="view === 'cuadricula'">
            <ul
                v-if="props.media.data.length"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6"
            >
                <li v-for="item in props.media.data" :key="item.id">
                    <button
                        type="button"
                        class="group flex w-full flex-col overflow-hidden rounded-xl border border-border bg-card text-left outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                        @click="openDetail(item)"
                    >
                        <span class="relative block aspect-square bg-muted">
                            <img
                                v-if="item.thumbnailUrl"
                                :src="item.thumbnailUrl"
                                :alt="item.altText ?? ''"
                                loading="lazy"
                                class="h-full w-full object-cover transition-transform group-hover:scale-[1.03]"
                            />
                            <span
                                v-else
                                class="grid h-full place-items-center text-muted-foreground"
                            >
                                <Film
                                    v-if="item.type === 'video'"
                                    class="size-6"
                                /><FileText
                                    v-else-if="item.type === 'document'"
                                    class="size-6"
                                /><ImageOff v-else class="size-6" />
                            </span>
                            <span
                                v-if="item.origin"
                                class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-black/55 px-2 py-0.5 text-[11px] text-white"
                            >
                                <Camera
                                    v-if="item.origin === 'real_photo'"
                                    class="size-3"
                                /><PenTool v-else class="size-3" />
                                {{ item.originLabel }}
                            </span>
                            <span
                                v-if="item.status !== 'ready'"
                                class="absolute right-2 bottom-2"
                                ><StatusBadge
                                    tone="warning"
                                    label="Procesando"
                                    size="sm"
                            /></span>
                        </span>
                        <span class="block px-3 py-2">
                            <span class="block truncate text-sm font-medium">{{
                                item.title || item.filename
                            }}</span>
                            <span
                                :class="
                                    cn(
                                        'block text-xs',
                                        item.altText
                                            ? 'text-muted-foreground'
                                            : 'text-warning-ink',
                                    )
                                "
                            >
                                {{ formatBytes(item.size) }} ·
                                {{
                                    item.altText
                                        ? 'Con texto alternativo'
                                        : 'Falta texto alternativo'
                                }}
                            </span>
                        </span>
                    </button>
                </li>
            </ul>
            <EmptyState
                v-else
                :icon="Images"
                title="No hay archivos con estos filtros"
                description="Cambia los filtros o sube nuevos archivos a la biblioteca."
            >
                <Button v-if="props.can.upload" @click="uploadOpen = true"
                    ><Upload class="size-4" /> Subir archivos</Button
                >
            </EmptyState>
        </template>

        <DataView
            v-else
            :columns="columns"
            :rows="props.media.data"
            empty-title="No hay archivos con estos filtros"
        >
            <template #cell-filename="{ row }">
                <button
                    type="button"
                    class="flex items-center gap-3 text-left"
                    @click="openDetail(row)"
                >
                    <span
                        class="size-10 shrink-0 overflow-hidden rounded-md bg-muted"
                    >
                        <img
                            v-if="row.thumbnailUrl"
                            :src="row.thumbnailUrl"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block truncate font-medium hover:underline"
                            >{{ row.title || row.filename }}</span
                        >
                        <span class="block text-xs text-muted-foreground">{{
                            row.originLabel ?? 'Origen sin indicar'
                        }}</span>
                    </span>
                </button>
            </template>
            <template #cell-type="{ row }">{{ typeLabel(row.type) }}</template>
            <template #cell-size="{ row }">{{
                formatBytes(row.size)
            }}</template>
            <template #cell-createdAt="{ row }"
                ><DateText :value="row.createdAt" relative
            /></template>
        </DataView>

        <DataPagination
            v-if="props.media.last_page > 1"
            :paginator="props.media"
            :only="['media', 'filters']"
            item-label="archivos"
        />

        <MediaDetailSheet
            v-model:open="detailOpen"
            :media="selected"
            :accept="props.limits.accept"
            @changed="onChanged"
        />
    </PageContainer>
</template>
