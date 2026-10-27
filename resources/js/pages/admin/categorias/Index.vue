<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, FolderOpen, Pencil, Plus, Search, Tags, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import StatCard from '@/components/admin/StatCard.vue';
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
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import categorias from '@/routes/admin/categorias';
import publicaciones from '@/routes/admin/publicaciones';
import type { BreadcrumbItem } from '@/types';

interface CategoryRow {
    id: number;
    name: string;
    slug: string;
    color: string;
    icon: string | null;
    status: string;
    order: number;
    postsCount: number;
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Categorías', href: categorias.index() },
        ] as BreadcrumbItem[],
    },
});

const props = defineProps<{
    categories: CategoryRow[];
    can: { create: boolean };
}>();

// --- Filtros ---
const query = ref('');
const statusFilter = ref<string>('all');

const counts = computed(() => ({
    all: props.categories.length,
    active: props.categories.filter((c) => c.status === 'active').length,
    inactive: props.categories.filter((c) => c.status !== 'active').length,
}));

const statusOptions = computed(() => [
    { value: 'all', label: `Todas · ${counts.value.all}` },
    { value: 'active', label: `Activas · ${counts.value.active}` },
    { value: 'inactive', label: `Inactivas · ${counts.value.inactive}` },
]);

const totalPosts = computed(() => props.categories.reduce((sum, c) => sum + c.postsCount, 0));
const maxPosts = computed(() => Math.max(1, ...props.categories.map((c) => c.postsCount)));

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    return props.categories.filter((c) => {
        if (statusFilter.value === 'active' && c.status !== 'active') return false;
        if (statusFilter.value === 'inactive' && c.status === 'active') return false;

        return !q || c.name.toLowerCase().includes(q) || c.slug.toLowerCase().includes(q);
    });
});

function clearFilters() {
    query.value = '';
    statusFilter.value = 'all';
}

// --- Eliminación ---
const pendingDelete = ref<CategoryRow | null>(null);

function destroy() {
    if (!pendingDelete.value) return;
    router.delete(categorias.destroy(pendingDelete.value.id).url, {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    });
}

function postsLabel(n: number): string {
    return n === 1 ? '1 publicación' : `${n} publicaciones`;
}

/** Botones de acción de fila: 36 px con ratón, 44 px en pantallas táctiles. */
const rowAction = 'size-9 pointer-coarse:size-11';
</script>

<template>
    <Head title="Categorías" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Contenido"
            title="Categorías"
            description="Organizan tus tips en el sitio público. Cada una tiene un color editorial que aparece en etiquetas, filtros y tarjetas."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="categorias.create()"><Plus class="size-4" /> Nueva categoría</Link>
                </Button>
            </template>
        </AdminPageHeader>

        <EmptyState
            v-if="categories.length === 0"
            :icon="Tags"
            title="Aún no hay categorías"
            description="Crea la primera para agrupar tus tips. Por ejemplo: Negocios, Hábitos o Finanzas personales."
        >
            <Button v-if="can.create" as-child>
                <Link :href="categorias.create()"><Plus class="size-4" /> Crear la primera</Link>
            </Button>
        </EmptyState>

        <template v-else>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                <StatCard label="Categorías" :value="counts.all" :hint="`${counts.active} activas en el sitio`" :icon="Tags" tone="brand" />
                <StatCard label="Publicaciones clasificadas" :value="totalPosts" hint="Suma de todas las categorías" :icon="FileText" />
                <StatCard
                    class="col-span-2 lg:col-span-1"
                    label="Inactivas"
                    :value="counts.inactive"
                    hint="Ocultas en el sitio público"
                    :icon="FolderOpen"
                    :tone="counts.inactive ? 'warning' : 'default'"
                />
            </div>

            <!-- Filtros -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="relative w-full md:max-w-xs">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="query" type="search" placeholder="Buscar por nombre o slug" aria-label="Buscar categorías" class="pl-9" />
                </div>
                <div class="w-full md:w-auto md:min-w-[22rem]">
                    <SegmentedControl v-model="statusFilter" label="Filtrar por estado" :options="statusOptions" />
                </div>
            </div>

            <EmptyState
                v-if="filtered.length === 0"
                :icon="Search"
                title="Sin coincidencias"
                description="Ninguna categoría coincide con la búsqueda o el filtro de estado."
            >
                <Button variant="outline" @click="clearFilters">Limpiar filtros</Button>
            </EmptyState>

            <template v-else>
                <!-- Escritorio: tabla -->
                <div class="hidden overflow-hidden rounded-xl border bg-card md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-16 pl-5">Orden</TableHead>
                                <TableHead>Categoría</TableHead>
                                <TableHead class="w-[32%]">Publicaciones</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="pr-5 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="category in filtered" :key="category.id" class="h-16">
                                <TableCell class="pl-5 text-xs text-muted-foreground tabular-nums">{{ category.order }}</TableCell>
                                <TableCell>
                                    <Link :href="categorias.edit(category.id)" class="group flex items-center gap-3 rounded-md">
                                        <span
                                            class="grid size-10 shrink-0 place-items-center rounded-lg ring-1 ring-border ring-inset"
                                            :style="{ backgroundColor: category.color }"
                                            aria-hidden="true"
                                        />
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium group-hover:underline">{{ category.name }}</span>
                                            <span class="block truncate text-xs text-muted-foreground">/{{ category.slug }}</span>
                                        </span>
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <Link
                                            :href="publicaciones.index({ query: { categoria: category.slug } }).url"
                                            class="w-28 shrink-0 text-sm tabular-nums hover:underline"
                                        >
                                            {{ postsLabel(category.postsCount) }}
                                        </Link>
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                                            <div
                                                class="h-full rounded-full"
                                                :style="{ width: `${(category.postsCount / maxPosts) * 100}%`, background: category.color }"
                                            />
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-[11px] font-semibold"
                                        :class="category.status === 'active' ? 'bg-success/12 text-success' : 'bg-muted text-muted-foreground'"
                                    >
                                        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
                                        {{ category.status === 'active' ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </TableCell>
                                <TableCell class="pr-5">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child variant="ghost" size="icon" :class="rowAction">
                                            <Link :href="categorias.edit(category.id)" :aria-label="`Editar ${category.name}`">
                                                <Pencil class="size-4" />
                                            </Link>
                                        </Button>
                                        <Tooltip v-if="category.postsCount > 0">
                                            <TooltipTrigger as-child>
                                                <span tabindex="0" class="inline-flex rounded-md" :aria-label="`No se puede eliminar ${category.name}: tiene publicaciones`">
                                                    <Button variant="ghost" size="icon" :class="rowAction" disabled aria-hidden="true">
                                                        <Trash2 class="size-4" />
                                                    </Button>
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>Mueve sus publicaciones a otra categoría para poder eliminarla.</TooltipContent>
                                        </Tooltip>
                                        <Button
                                            v-else
                                            variant="ghost"
                                            size="icon"
                                            :class="rowAction"
                                            :aria-label="`Eliminar ${category.name}`"
                                            @click="pendingDelete = category"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Móvil: tarjetas -->
                <ul class="grid gap-3 md:hidden">
                    <li v-for="category in filtered" :key="category.id" class="overflow-hidden rounded-xl border bg-card">
                        <div class="h-1.5" :style="{ backgroundColor: category.color }" aria-hidden="true" />
                        <div class="flex items-center gap-3 p-4">
                            <Link :href="categorias.edit(category.id)" class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ category.name }}</span>
                                <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                    <span>{{ postsLabel(category.postsCount) }}</span>
                                    <span>· {{ category.status === 'active' ? 'Activa' : 'Inactiva' }}</span>
                                    <span>· Orden {{ category.order }}</span>
                                </span>
                            </Link>
                            <Button as-child variant="ghost" size="icon" class="size-11">
                                <Link :href="categorias.edit(category.id)" :aria-label="`Editar ${category.name}`"><Pencil class="size-4" /></Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-11"
                                :disabled="category.postsCount > 0"
                                :aria-label="category.postsCount > 0 ? `No se puede eliminar ${category.name}: tiene publicaciones` : `Eliminar ${category.name}`"
                                @click="pendingDelete = category"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </li>
                </ul>

                <p class="text-xs text-muted-foreground">
                    Solo puedes eliminar categorías sin publicaciones. El orden define cómo aparecen en los filtros del sitio.
                </p>
            </template>
        </template>
    </div>

    <AlertDialog :open="pendingDelete !== null" @update:open="(v: boolean) => !v && (pendingDelete = null)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Eliminar «{{ pendingDelete?.name }}»?</AlertDialogTitle>
                <AlertDialogDescription>
                    Esta acción no se puede deshacer. Solo puedes eliminar categorías sin publicaciones asociadas.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="destroy">Eliminar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
