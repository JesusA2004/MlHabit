<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FolderKanban } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import proyectos from '@/routes/admin/proyectos';
import type { BreadcrumbItem } from '@/types';
import type { EnumValue, ProjectSummary } from '@/types/projects';
import type { Paginator } from '@/types/ui';

type Option = { id: number; name: string };

const props = defineProps<{
    projects: Paginator<ProjectSummary>;
    filters: {
        q: string;
        vista: string;
        estado: string | null;
        asesor: number | null;
        disenador: number | null;
        categoria: number | null;
    };
    views: { key: string; label: string; count: number }[];
    options: {
        statuses: EnumValue[];
        advisors: Option[];
        designers: Option[];
        operations: Option[];
        categories: Option[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Proyectos', href: '/admin/proyectos' },
        ] as BreadcrumbItem[],
    },
});

const search = ref(props.filters.q ?? '');
const estado = ref<string | null>(props.filters.estado);
const asesor = ref<number | null>(
    props.filters.asesor ? Number(props.filters.asesor) : null,
);
const disenador = ref<number | null>(
    props.filters.disenador ? Number(props.filters.disenador) : null,
);
const categoria = ref<number | null>(
    props.filters.categoria ? Number(props.filters.categoria) : null,
);
const loading = ref(false);

const activeCount = computed(
    () =>
        [estado.value, asesor.value, disenador.value, categoria.value].filter(
            (v) => v !== null,
        ).length,
);

function reload(vista = props.filters.vista) {
    router.get(
        proyectos.index().url,
        {
            q: search.value || undefined,
            vista: vista === 'activos' ? undefined : vista,
            estado: estado.value ?? undefined,
            asesor: asesor.value ?? undefined,
            disenador: disenador.value ?? undefined,
            categoria: categoria.value ?? undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['projects', 'filters', 'views'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

watch(search, () => reload());
watch([estado, asesor, disenador, categoria], () => reload());

function clearFilters() {
    estado.value = null;
    asesor.value = null;
    disenador.value = null;
    categoria.value = null;
    search.value = '';
}

const toOptions = (list: Option[]) =>
    list.map((o) => ({ value: o.id, label: o.name }));
const statusOptions = computed(() =>
    props.options.statuses.map((s) => ({ value: s.value, label: s.label })),
);

const columns: DataColumn<ProjectSummary>[] = [
    { key: 'name', label: 'Proyecto', primary: true },
    { key: 'status', label: 'Etapa' },
    { key: 'customer', label: 'Cliente', value: (row) => row.customer?.name },
    { key: 'category', label: 'Categoría', hideOnMobile: true },
    {
        key: 'advisor',
        label: 'Asesor',
        value: (row) => row.advisor?.name,
        hideOnMobile: true,
    },
    {
        key: 'designer',
        label: 'Diseño',
        value: (row) => row.designer?.name,
        hideOnMobile: true,
    },
    { key: 'updated_at', label: 'Último movimiento', align: 'right' },
];

const emptyTitle = computed(() =>
    props.filters.q || activeCount.value
        ? 'Ningún proyecto coincide con tu búsqueda'
        : 'Aún no hay proyectos en esta vista',
);
</script>

<template>
    <Head title="Proyectos" />

    <PageContainer>
        <PageHeader
            title="Proyectos"
            description="Cada proyecto reúne medición, diseño, cotización, contrato y operación. Se crean desde un prospecto, una visita o un cliente."
        />

        <nav
            aria-label="Vistas rápidas"
            class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1"
        >
            <button
                v-for="view in views"
                :key="view.key"
                type="button"
                :aria-current="filters.vista === view.key ? 'page' : undefined"
                :class="
                    cn(
                        'inline-flex h-9 shrink-0 items-center gap-2 rounded-full border px-3.5 text-sm font-medium transition-colors pointer-coarse:h-11',
                        filters.vista === view.key
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
                    )
                "
                @click="reload(view.key)"
            >
                {{ view.label }}
                <span
                    :class="
                        cn(
                            'rounded-full px-1.5 text-xs tabular-nums',
                            filters.vista === view.key
                                ? 'bg-primary-foreground/20'
                                : 'bg-muted text-foreground',
                        )
                    "
                >
                    {{ view.count }}
                </span>
            </button>
        </nav>

        <FilterBar
            v-model:search="search"
            placeholder="Buscar por folio, nombre, cliente, correo o teléfono…"
            :active-count="activeCount"
            @clear="clearFilters"
        >
            <template #filters>
                <div class="grid gap-1.5 md:w-48">
                    <Label class="md:sr-only" for="f-estado">Etapa</Label>
                    <SearchSelect
                        id="f-estado"
                        v-model="estado"
                        :options="statusOptions"
                        placeholder="Todas las etapas"
                        clearable
                    />
                </div>
                <div class="grid gap-1.5 md:w-44">
                    <Label class="md:sr-only" for="f-asesor">Asesor</Label>
                    <SearchSelect
                        id="f-asesor"
                        v-model="asesor"
                        :options="toOptions(options.advisors)"
                        placeholder="Cualquier asesor"
                        clearable
                    />
                </div>
                <div class="grid gap-1.5 md:w-44">
                    <Label class="md:sr-only" for="f-disenador"
                        >Diseñador</Label
                    >
                    <SearchSelect
                        id="f-disenador"
                        v-model="disenador"
                        :options="toOptions(options.designers)"
                        placeholder="Cualquier diseñador"
                        clearable
                    />
                </div>
                <div class="grid gap-1.5 md:w-44">
                    <Label class="md:sr-only" for="f-categoria"
                        >Categoría</Label
                    >
                    <SearchSelect
                        id="f-categoria"
                        v-model="categoria"
                        :options="toOptions(options.categories)"
                        placeholder="Todas las categorías"
                        clearable
                    />
                </div>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="projects"
            row-key="uuid"
            :row-href="(row) => proyectos.show(row.uuid).url"
            :loading="loading"
            :only="['projects']"
            item-label="proyectos"
            :empty-title="emptyTitle"
            empty-description="Los proyectos nacen cuando un prospecto pasa a diseño. Usa «Crear proyecto» desde el detalle del prospecto o de la visita."
            caption="Listado de proyectos"
        >
            <template #cell-name="{ row }">
                <Link
                    :href="proyectos.show(row.uuid).url"
                    class="group flex min-w-0 flex-col"
                >
                    <span
                        class="truncate font-medium text-foreground group-hover:underline"
                        >{{ row.name }}</span
                    >
                    <span class="text-xs text-muted-foreground tabular-nums">{{
                        row.folio
                    }}</span>
                </Link>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="row.status.tone"
                    :label="row.status.label"
                    size="sm"
                />
            </template>
            <template #cell-updated_at="{ row }">
                <DateText
                    :value="row.updated_at"
                    relative
                    class="text-sm text-muted-foreground"
                />
            </template>
            <template #empty>
                <EmptyState
                    :icon="FolderKanban"
                    :title="emptyTitle"
                    description="Los proyectos nacen cuando un prospecto pasa a diseño. Usa «Crear proyecto» desde el detalle del prospecto o de la visita."
                />
            </template>
        </DataView>
    </PageContainer>
</template>
