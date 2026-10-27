<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    FilePen,
    ImageIcon,
    MoreHorizontal,
    Pencil,
    Plus,
    Rocket,
    Search,
    SearchX,
    Star,
    StarOff,
    Trash2,
    X,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import SegmentedControl from '@/components/studio/controls/SegmentedControl.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import publicaciones from '@/routes/admin/publicaciones';
import type { BreadcrumbItem } from '@/types';

interface PostRow {
    id: number;
    title: string;
    slug: string;
    status: string;
    statusLabel: string;
    isFeatured: boolean;
    category: string | null;
    author: string | null;
    coverUrl: string | null;
    publishedAt: string | null;
    scheduledAt: string | null;
    updatedAt: string | null;
}

const props = defineProps<{
    posts: {
        data: PostRow[];
        currentPage: number;
        lastPage: number;
        nextPageUrl: string | null;
        prevPageUrl: string | null;
        total: number;
    };
    filters: { status?: string; categoria?: string; buscar?: string };
    categories: { id: number; name: string; slug: string }[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Publicaciones', href: publicaciones.index() },
        ] as BreadcrumbItem[],
    },
});

const STATUS_OPTIONS = [
    { value: 'todos', label: 'Todas' },
    { value: 'draft', label: 'Borradores' },
    { value: 'scheduled', label: 'Programadas' },
    { value: 'published', label: 'Publicadas' },
    { value: 'archived', label: 'Archivadas' },
];

const search = ref(props.filters.buscar ?? '');
const status = ref<string | null | undefined>(props.filters.status ?? 'todos');
const category = ref(props.filters.categoria ?? 'todos');
const loading = ref(false);

const hasFilters = computed(
    () =>
        !!props.filters.buscar ||
        !!props.filters.status ||
        !!props.filters.categoria,
);

function applyFilters() {
    router.get(
        publicaciones.index().url,
        {
            buscar: search.value || undefined,
            status:
                !status.value || status.value === 'todos'
                    ? undefined
                    : status.value,
            categoria: category.value === 'todos' ? undefined : category.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

function clearFilters() {
    search.value = '';
    status.value = 'todos';
    category.value = 'todos';
    applyFilters();
}

watchDebounced(
    search,
    (value) => {
        if (value === (props.filters.buscar ?? '')) return;
        applyFilters();
    },
    { debounce: 400 },
);

function onStatusChange(value: string | null | undefined) {
    status.value = value;
    applyFilters();
}

/* ---------- Fechas ---------- */

const RTF = new Intl.RelativeTimeFormat('es-MX', { numeric: 'auto' });
const DATE_TIME = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

function relative(iso: string | null): string {
    if (!iso) return '—';
    const diff = (new Date(iso).getTime() - Date.now()) / 1000;
    const abs = Math.abs(diff);
    if (abs < 60) return 'justo ahora';
    if (abs < 3600) return RTF.format(Math.round(diff / 60), 'minute');
    if (abs < 86400) return RTF.format(Math.round(diff / 3600), 'hour');
    if (abs < 86400 * 30) return RTF.format(Math.round(diff / 86400), 'day');
    if (abs < 86400 * 365)
        return RTF.format(Math.round(diff / (86400 * 30)), 'month');

    return RTF.format(Math.round(diff / (86400 * 365)), 'year');
}

function dateInfo(post: PostRow): { label: string; value: string; title: string } {
    if (post.status === 'scheduled' && post.scheduledAt) {
        return {
            label: 'Sale',
            value: DATE_TIME.format(new Date(post.scheduledAt)),
            title: new Date(post.scheduledAt).toLocaleString('es-MX'),
        };
    }
    if (post.status === 'published' && post.publishedAt) {
        return {
            label: 'Publicada',
            value: relative(post.publishedAt),
            title: new Date(post.publishedAt).toLocaleString('es-MX'),
        };
    }

    return {
        label: 'Editada',
        value: relative(post.updatedAt),
        title: post.updatedAt
            ? new Date(post.updatedAt).toLocaleString('es-MX')
            : '',
    };
}

/* ---------- Acciones rápidas ---------- */

const busyId = ref<number | null>(null);

function postAction(
    url: string,
    post: PostRow,
    message: string,
): void {
    busyId.value = post.id;
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => toast.error('No se pudo completar la acción.'),
            onFinish: () => (busyId.value = null),
        },
    );
}

function publish(post: PostRow) {
    postAction(
        publicaciones.publicar(post.id).url,
        post,
        `«${post.title}» publicada.`,
    );
}

function archive(post: PostRow) {
    postAction(
        publicaciones.archivar(post.id).url,
        post,
        `«${post.title}» archivada.`,
    );
}

function toggleFeatured(post: PostRow) {
    postAction(
        publicaciones.destacar(post.id).url,
        post,
        post.isFeatured
            ? 'Ya no está destacada.'
            : `«${post.title}» destacada.`,
    );
}

function openPreview(post: PostRow) {
    window.open(publicaciones.preview(post.id).url, '_blank', 'noopener');
}

const pendingDelete = ref<PostRow | null>(null);

function destroy() {
    if (!pendingDelete.value) return;
    const title = pendingDelete.value.title;
    router.delete(publicaciones.destroy(pendingDelete.value.id).url, {
        preserveScroll: true,
        onError: () => toast.error('No se pudo eliminar la publicación.'),
        onFinish: () => (pendingDelete.value = null),
    });
}

const summary = computed(() => {
    const n = props.posts.total;

    return n === 1 ? '1 publicación' : `${n} publicaciones`;
});
</script>

<template>
    <Head title="Publicaciones" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Contenido"
            title="Publicaciones"
            description="Escribe, programa y publica tus tips. Todo lo que no publiques se queda como borrador."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="publicaciones.create()">
                        <Plus class="size-4" />
                        Nueva publicación
                    </Link>
                </Button>
            </template>
        </AdminPageHeader>

        <!-- Filtros -->
        <div
            class="flex flex-col gap-3 rounded-xl border bg-card p-3 lg:flex-row lg:items-center"
            role="search"
        >
            <div class="relative lg:w-72">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="search"
                    type="search"
                    aria-label="Buscar por título"
                    placeholder="Buscar por título…"
                    class="h-10 pl-9"
                    @keyup.enter="applyFilters"
                />
            </div>
            <div class="-mx-1 overflow-x-auto px-1 lg:flex-1">
                <div class="min-w-[26rem] lg:max-w-[30rem]">
                    <SegmentedControl
                        :model-value="status"
                        label="Filtrar por estado"
                        :options="STATUS_OPTIONS"
                        @update:model-value="onStatusChange"
                    />
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Select v-model="category" @update:model-value="applyFilters">
                    <SelectTrigger
                        class="h-10 w-full lg:w-52"
                        aria-label="Filtrar por categoría"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos">Todas las categorías</SelectItem>
                        <SelectItem
                            v-for="c in categories"
                            :key="c.slug"
                            :value="c.slug"
                            >{{ c.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Button
                    v-if="hasFilters"
                    variant="ghost"
                    size="sm"
                    class="h-10 shrink-0"
                    @click="clearFilters"
                >
                    <X class="size-4" />
                    Limpiar
                </Button>
            </div>
        </div>

        <!-- Vacío -->
        <template v-if="posts.data.length === 0">
            <EmptyState
                v-if="hasFilters"
                :icon="SearchX"
                title="Nada coincide con estos filtros"
                description="Prueba con otra palabra, otro estado o todas las categorías."
            >
                <Button variant="outline" size="sm" @click="clearFilters">
                    <X class="size-4" /> Limpiar filtros
                </Button>
            </EmptyState>
            <EmptyState
                v-else
                :icon="FilePen"
                title="Aún no hay publicaciones"
                description="Escribe tu primer tip; podrás guardarlo como borrador, programarlo o publicarlo cuando quieras."
            >
                <Button v-if="can.create" as-child size="sm">
                    <Link :href="publicaciones.create()">
                        <Plus class="size-4" /> Escribir el primero
                    </Link>
                </Button>
            </EmptyState>
        </template>

        <template v-else>
            <div
                class="flex flex-col gap-3 transition-opacity"
                :class="loading && 'pointer-events-none opacity-60'"
                :aria-busy="loading"
            >
                <p class="text-xs text-muted-foreground">
                    {{ summary }}
                    <template v-if="posts.lastPage > 1">
                        · página {{ posts.currentPage }} de
                        {{ posts.lastPage }}</template
                    >
                </p>

                <!-- Tabla (≥ 768 px) -->
                <div class="hidden overflow-hidden rounded-xl border bg-card md:block">
                    <Table>
                        <TableHeader>
                            <TableRow class="hover:bg-transparent">
                                <TableHead class="pl-4">Publicación</TableHead>
                                <TableHead class="hidden lg:table-cell">Autor</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="w-24 pr-4 text-right">
                                    <span class="sr-only">Acciones</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="post in posts.data"
                                :key="post.id"
                                class="group"
                                :class="busyId === post.id && 'opacity-60'"
                            >
                                <TableCell class="py-2.5 pl-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <Link
                                            :href="publicaciones.edit(post.id)"
                                            class="relative grid aspect-[4/3] w-16 shrink-0 place-items-center overflow-hidden rounded-md bg-muted text-muted-foreground"
                                            tabindex="-1"
                                            aria-hidden="true"
                                        >
                                            <img
                                                v-if="post.coverUrl"
                                                :src="post.coverUrl"
                                                alt=""
                                                loading="lazy"
                                                class="size-full object-cover"
                                            />
                                            <ImageIcon v-else class="size-4" />
                                        </Link>
                                        <div class="min-w-0">
                                            <Link
                                                :href="publicaciones.edit(post.id)"
                                                class="line-clamp-1 font-medium hover:underline"
                                            >
                                                {{ post.title }}
                                            </Link>
                                            <p
                                                class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground"
                                            >
                                                <span
                                                    class="size-1.5 shrink-0 rounded-full"
                                                    :class="post.category ? 'bg-brand' : 'bg-muted-foreground/40'"
                                                    aria-hidden="true"
                                                />
                                                <span class="truncate">{{
                                                    post.category ?? 'Sin categoría'
                                                }}</span>
                                                <span class="truncate lg:hidden" v-if="post.author">· {{ post.author }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="hidden text-sm text-muted-foreground lg:table-cell">
                                    {{ post.author ?? '—' }}
                                </TableCell>
                                <TableCell class="text-sm" :title="dateInfo(post).title">
                                    <span class="block text-xs text-muted-foreground">{{
                                        dateInfo(post).label
                                    }}</span>
                                    <span class="whitespace-nowrap">{{
                                        dateInfo(post).value
                                    }}</span>
                                </TableCell>
                                <TableCell>
                                    <StatusBadge :status="post.status" />
                                </TableCell>
                                <TableCell class="pr-4">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="pointer-coarse:size-11"
                                            :aria-label="post.isFeatured ? `Quitar destacado de ${post.title}` : `Destacar ${post.title}`"
                                            :aria-pressed="post.isFeatured"
                                            :title="post.isFeatured ? 'Destacada · clic para quitar' : 'Destacar'"
                                            :disabled="busyId === post.id"
                                            @click="toggleFeatured(post)"
                                        >
                                            <Star
                                                class="size-4"
                                                :class="post.isFeatured ? 'fill-brand text-brand' : 'text-muted-foreground/50'"
                                            />
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="pointer-coarse:size-11"
                                                    :aria-label="`Acciones de ${post.title}`"
                                                >
                                                    <MoreHorizontal class="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-52">
                                                <DropdownMenuItem as-child>
                                                    <Link :href="publicaciones.edit(post.id)">
                                                        <Pencil /> Editar
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @select="openPreview(post)">
                                                    <ExternalLink /> Vista previa
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    v-if="post.status !== 'published'"
                                                    @select="publish(post)"
                                                >
                                                    <Rocket /> Publicar ahora
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="post.status !== 'archived'"
                                                    @select="archive(post)"
                                                >
                                                    <Archive /> Archivar
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @select="toggleFeatured(post)">
                                                    <component :is="post.isFeatured ? StarOff : Star" />
                                                    {{ post.isFeatured ? 'Quitar destacado' : 'Destacar' }}
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    @select="pendingDelete = post"
                                                >
                                                    <Trash2 /> Eliminar
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Tarjetas (< 768 px) -->
                <ul class="flex flex-col gap-2 md:hidden">
                    <li
                        v-for="post in posts.data"
                        :key="post.id"
                        class="flex gap-3 rounded-xl border bg-card p-3"
                        :class="busyId === post.id && 'opacity-60'"
                    >
                        <Link
                            :href="publicaciones.edit(post.id)"
                            class="grid aspect-square w-16 shrink-0 place-items-center overflow-hidden rounded-lg bg-muted text-muted-foreground"
                            tabindex="-1"
                            aria-hidden="true"
                        >
                            <img
                                v-if="post.coverUrl"
                                :src="post.coverUrl"
                                alt=""
                                loading="lazy"
                                class="size-full object-cover"
                            />
                            <ImageIcon v-else class="size-4" />
                        </Link>
                        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                            <div class="flex items-start gap-2">
                                <Link
                                    :href="publicaciones.edit(post.id)"
                                    class="line-clamp-2 flex-1 text-sm leading-snug font-medium"
                                >
                                    <Star
                                        v-if="post.isFeatured"
                                        class="mr-1 inline size-3.5 -translate-y-px fill-brand text-brand"
                                        aria-label="Destacada"
                                    />{{ post.title }}
                                </Link>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="-mt-1 -mr-1 size-11 shrink-0"
                                            :aria-label="`Acciones de ${post.title}`"
                                        >
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-52">
                                        <DropdownMenuItem as-child>
                                            <Link :href="publicaciones.edit(post.id)">
                                                <Pencil /> Editar
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @select="openPreview(post)">
                                            <ExternalLink /> Vista previa
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            v-if="post.status !== 'published'"
                                            @select="publish(post)"
                                        >
                                            <Rocket /> Publicar ahora
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="post.status !== 'archived'"
                                            @select="archive(post)"
                                        >
                                            <Archive /> Archivar
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @select="toggleFeatured(post)">
                                            <component :is="post.isFeatured ? StarOff : Star" />
                                            {{ post.isFeatured ? 'Quitar destacado' : 'Destacar' }}
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            @select="pendingDelete = post"
                                        >
                                            <Trash2 /> Eliminar
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                <StatusBadge :status="post.status" />
                                <span class="inline-flex items-center gap-1">
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="post.category ? 'bg-brand' : 'bg-muted-foreground/40'"
                                        aria-hidden="true"
                                    />
                                    {{ post.category ?? 'Sin categoría' }}
                                </span>
                                <span :title="dateInfo(post).title"
                                    >· {{ dateInfo(post).label }} {{ dateInfo(post).value }}</span
                                >
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Paginación -->
            <nav
                v-if="posts.lastPage > 1"
                class="flex items-center justify-between gap-3"
                aria-label="Paginación"
            >
                <Button
                    v-if="posts.prevPageUrl"
                    as-child
                    variant="outline"
                    class="h-10"
                >
                    <Link :href="posts.prevPageUrl" preserve-scroll>
                        <ChevronLeft class="size-4" /> Anteriores
                    </Link>
                </Button>
                <Button v-else variant="outline" class="h-10" disabled>
                    <ChevronLeft class="size-4" /> Anteriores
                </Button>
                <span class="text-sm text-muted-foreground tabular-nums">
                    {{ posts.currentPage }} / {{ posts.lastPage }}
                </span>
                <Button
                    v-if="posts.nextPageUrl"
                    as-child
                    variant="outline"
                    class="h-10"
                >
                    <Link :href="posts.nextPageUrl" preserve-scroll>
                        Siguientes <ChevronRight class="size-4" />
                    </Link>
                </Button>
                <Button v-else variant="outline" class="h-10" disabled>
                    Siguientes <ChevronRight class="size-4" />
                </Button>
            </nav>
        </template>
    </div>

    <AlertDialog
        :open="pendingDelete !== null"
        @update:open="(v: boolean) => !v && (pendingDelete = null)"
    >
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle
                    >¿Eliminar «{{ pendingDelete?.title }}»?</AlertDialogTitle
                >
                <AlertDialogDescription>
                    La publicación desaparecerá del sitio y del panel. Esta
                    acción no se puede deshacer; si solo quieres ocultarla,
                    archívala.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-white hover:bg-destructive/90"
                    @click="destroy"
                    >Eliminar</AlertDialogAction
                >
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
