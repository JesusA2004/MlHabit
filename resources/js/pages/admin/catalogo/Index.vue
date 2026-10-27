<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import type { DataColumn } from '@/components/ds/types';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import CategoriesPanel from '@/components/web/admin/catalog/CategoriesPanel.vue';
import type { CategoryRow } from '@/components/web/admin/catalog/CategoriesPanel.vue';
import FinishesPanel from '@/components/web/admin/catalog/FinishesPanel.vue';
import type { FinishRow } from '@/components/web/admin/catalog/FinishesPanel.vue';
import catalogo from '@/routes/admin/catalogo';
import productos from '@/routes/admin/catalogo/productos';
import type { MediaRef, SimplePaginator, ToneOption } from '@/types/web';

type ProductRow = {
    id: number;
    name: string;
    slug: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    isFeatured: boolean;
    category: { id: number; name: string; isPublished: boolean };
    cover: MediaRef | null;
    publicUrl: string | null;
    updatedAt: string | null;
};

type Tab = 'productos' | 'categorias' | 'acabados';

const props = defineProps<{
    tab: Tab;
    filters: { q: string; estado: string | null; categoria: number | null };
    products: SimplePaginator<ProductRow>;
    categories: CategoryRow[];
    finishes: FinishRow[];
    statusOptions: ToneOption[];
    can: { publish: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Catálogo', href: '/admin/catalogo' },
        ],
    },
});

const current = ref<Tab>(props.tab);
const search = ref(props.filters.q);
const status = ref(props.filters.estado ?? 'todos');
const category = ref(
    props.filters.categoria ? String(props.filters.categoria) : 'todas',
);

function reload(extra: Record<string, string | undefined> = {}) {
    const query: Record<string, string> = { pestana: current.value };
    if (search.value) query.q = search.value;
    if (status.value !== 'todos') query.estado = status.value;
    if (category.value !== 'todas') query.categoria = category.value;
    router.get(
        catalogo.index().url,
        { ...query, ...extra },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(search, () => reload());
watch([status, category], () => reload());
watch(current, () => reload());

const activeFilters = computed(
    () =>
        (status.value !== 'todos' ? 1 : 0) +
        (category.value !== 'todas' ? 1 : 0),
);

const columns: DataColumn<ProductRow>[] = [
    { key: 'name', label: 'Producto', primary: true },
    { key: 'category', label: 'Categoría', value: (r) => r.category.name },
    { key: 'status', label: 'Estado' },
    { key: 'updatedAt', label: 'Actualizado', hideOnMobile: true },
];
</script>

<template>
    <Head title="Catálogo" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Catálogo"
            description="Categorías, productos y acabados que se muestran en el sitio. Solo lo publicado es visible."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="productos.create().url"
                        ><Plus class="size-4" /> Nuevo producto</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <Tabs v-model="current" class="gap-6">
            <TabsList class="w-max">
                <TabsTrigger value="productos">Productos</TabsTrigger>
                <TabsTrigger value="categorias">Categorías</TabsTrigger>
                <TabsTrigger value="acabados">Acabados</TabsTrigger>
            </TabsList>

            <TabsContent value="productos" class="flex flex-col gap-4">
                <FilterBar
                    v-model:search="search"
                    placeholder="Buscar producto"
                    :active-count="activeFilters"
                    @clear="((status = 'todos'), (category = 'todas'))"
                >
                    <template #filters>
                        <Select v-model="status">
                            <SelectTrigger
                                class="w-full md:w-44"
                                aria-label="Estado"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="todos"
                                    >Todos los estados</SelectItem
                                >
                                <SelectItem
                                    v-for="o in props.statusOptions"
                                    :key="o.value"
                                    :value="o.value"
                                    >{{ o.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <Select v-model="category">
                            <SelectTrigger
                                class="w-full md:w-56"
                                aria-label="Categoría"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="todas"
                                    >Todas las categorías</SelectItem
                                >
                                <SelectItem
                                    v-for="c in props.categories"
                                    :key="c.id"
                                    :value="String(c.id)"
                                    >{{ c.name }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </template>
                </FilterBar>

                <DataView
                    :columns="columns"
                    :paginator="props.products"
                    :row-href="(row: ProductRow) => productos.edit(row.id).url"
                    item-label="productos"
                    empty-title="No hay productos"
                    empty-description="Crea el primer producto de una categoría para mostrarlo en el catálogo."
                >
                    <template #cell-name="{ row }">
                        <span class="flex items-center gap-3">
                            <span
                                class="size-10 shrink-0 overflow-hidden rounded-md bg-muted"
                            >
                                <img
                                    v-if="row.cover?.thumbnailUrl"
                                    :src="row.cover.thumbnailUrl"
                                    alt=""
                                    class="h-full w-full object-cover"
                                />
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{
                                    row.name
                                }}</span>
                                <span
                                    v-if="row.isFeatured"
                                    class="text-xs text-oak-ink"
                                    >Destacado</span
                                >
                            </span>
                        </span>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge
                            :tone="row.statusTone as Tone"
                            :label="row.statusLabel"
                            size="sm"
                        />
                        <span
                            v-if="
                                row.status === 'published' &&
                                !row.category.isPublished
                            "
                            class="mt-1 block text-xs text-warning-ink"
                            >Categoría sin publicar</span
                        >
                    </template>
                    <template #cell-updatedAt="{ row }"
                        ><DateText :value="row.updatedAt" relative
                    /></template>
                    <template #actions="{ row }">
                        <Button
                            v-if="row.publicUrl"
                            as="a"
                            :href="row.publicUrl"
                            target="_blank"
                            rel="noopener"
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :aria-label="`Ver ${row.name} en el sitio`"
                        >
                            <ExternalLink class="size-4" />
                        </Button>
                    </template>
                </DataView>
            </TabsContent>

            <TabsContent value="categorias">
                <CategoriesPanel
                    :categories="props.categories"
                    :can-publish="props.can.publish"
                />
            </TabsContent>

            <TabsContent value="acabados">
                <FinishesPanel :finishes="props.finishes" />
            </TabsContent>
        </Tabs>
    </PageContainer>
</template>
