<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Camera, ExternalLink, PenTool, Plus } from '@lucide/vue';
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
import inspiracion from '@/routes/admin/inspiracion';
import type { MediaRef, SimplePaginator, ToneOption } from '@/types/web';

type ProjectRow = {
    id: number;
    title: string;
    slug: string;
    origin: 'render' | 'real_photo';
    originLabel: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    isFeatured: boolean;
    category: string | null;
    cover: MediaRef | null;
    publicUrl: string | null;
    updatedAt: string | null;
};

const props = defineProps<{
    filters: { q: string; estado: string | null; origen: string | null };
    projects: SimplePaginator<ProjectRow>;
    statusOptions: ToneOption[];
    originOptions: ToneOption[];
    can: { publish: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Inspiración', href: '/admin/inspiracion' },
        ],
    },
});

const search = ref(props.filters.q);
const status = ref(props.filters.estado ?? 'todos');
const origin = ref(props.filters.origen ?? 'todos');

function reload() {
    const query: Record<string, string> = {};
    if (search.value) query.q = search.value;
    if (status.value !== 'todos') query.estado = status.value;
    if (origin.value !== 'todos') query.origen = origin.value;
    router.get(inspiracion.index().url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
watch([search, status, origin], reload);

const activeFilters = computed(
    () =>
        (status.value !== 'todos' ? 1 : 0) + (origin.value !== 'todos' ? 1 : 0),
);

const columns: DataColumn<ProjectRow>[] = [
    { key: 'title', label: 'Proyecto', primary: true },
    { key: 'origin', label: 'Origen' },
    { key: 'status', label: 'Estado' },
    { key: 'updatedAt', label: 'Actualizado', hideOnMobile: true },
];
</script>

<template>
    <Head title="Inspiración" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Inspiración"
            description="Proyectos entregados y renders conceptuales. El sitio siempre indica de cuál se trata."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="inspiracion.create().url"
                        ><Plus class="size-4" /> Nuevo proyecto</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="search"
            placeholder="Buscar proyecto"
            :active-count="activeFilters"
            @clear="((status = 'todos'), (origin = 'todos'))"
        >
            <template #filters>
                <Select v-model="status">
                    <SelectTrigger class="w-full md:w-44" aria-label="Estado"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos">Todos los estados</SelectItem>
                        <SelectItem
                            v-for="o in props.statusOptions"
                            :key="o.value"
                            :value="o.value"
                            >{{ o.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="origin">
                    <SelectTrigger class="w-full md:w-48" aria-label="Origen"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="todos"
                            >Todos los orígenes</SelectItem
                        >
                        <SelectItem
                            v-for="o in props.originOptions"
                            :key="o.value"
                            :value="o.value"
                            >{{ o.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="props.projects"
            :row-href="(row: ProjectRow) => inspiracion.edit(row.id).url"
            item-label="proyectos"
            empty-title="Aún no hay proyectos"
            empty-description="Agrega proyectos entregados (con autorización del cliente) o renders conceptuales."
        >
            <template #cell-title="{ row }">
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
                            row.title
                        }}</span>
                        <span class="text-xs text-muted-foreground"
                            >{{ row.category ?? 'Sin categoría'
                            }}<template v-if="row.isFeatured">
                                · Destacado</template
                            ></span
                        >
                    </span>
                </span>
            </template>
            <template #cell-origin="{ row }">
                <StatusBadge
                    :tone="row.origin === 'real_photo' ? 'success' : 'info'"
                    :icon="row.origin === 'real_photo' ? Camera : PenTool"
                    :label="row.originLabel"
                    :dot="false"
                    size="sm"
                />
            </template>
            <template #cell-status="{ row }"
                ><StatusBadge
                    :tone="row.statusTone as Tone"
                    :label="row.statusLabel"
                    size="sm"
            /></template>
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
                    :aria-label="`Ver ${row.title} en el sitio`"
                >
                    <ExternalLink class="size-4" />
                </Button>
            </template>
        </DataView>
    </PageContainer>
</template>
