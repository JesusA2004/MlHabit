<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import { Label } from '@/components/ui/label';
import cotizaciones from '@/routes/admin/cotizaciones';
import type { BreadcrumbItem } from '@/types';
import type { EnumValue, QuoteSummary } from '@/types/projects';
import type { Paginator } from '@/types/ui';

type Row = QuoteSummary & {
    project: {
        uuid: string;
        folio: string;
        name: string;
        customer: string | null;
    };
};

const props = defineProps<{
    quotes: Paginator<Row>;
    filters: { estado: string | null; q: string };
    statuses: EnumValue[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Cotizaciones', href: '/admin/cotizaciones' },
        ] as BreadcrumbItem[],
    },
});

const search = ref(props.filters.q ?? '');
const estado = ref<string | null>(props.filters.estado);
const loading = ref(false);

function reload() {
    router.get(
        cotizaciones.index().url,
        { q: search.value || undefined, estado: estado.value ?? undefined },
        {
            preserveState: true,
            replace: true,
            only: ['quotes', 'filters'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}
watch([search, estado], reload);

const columns: DataColumn<Row>[] = [
    { key: 'title', label: 'Cotización', primary: true },
    { key: 'project', label: 'Proyecto', value: (r) => r.project.name },
    { key: 'status', label: 'Estado' },
    { key: 'total', label: 'Total', align: 'right' },
    { key: 'valid_until', label: 'Vigencia', hideOnMobile: true },
];
</script>

<template>
    <Head title="Cotizaciones" />

    <PageContainer>
        <PageHeader
            title="Cotizaciones"
            description="Todas las cotizaciones vigentes de los proyectos. Se crean desde el detalle de cada proyecto."
        />

        <FilterBar
            v-model:search="search"
            placeholder="Buscar por folio, título o proyecto…"
            :active-count="estado ? 1 : 0"
            @clear="estado = null"
        >
            <template #filters>
                <div class="grid gap-1.5 md:w-52">
                    <Label class="md:sr-only" for="q-estado">Estado</Label>
                    <SearchSelect
                        id="q-estado"
                        v-model="estado"
                        :options="
                            statuses.map((s) => ({
                                value: s.value,
                                label: s.label,
                            }))
                        "
                        placeholder="Vigentes (sin reemplazadas)"
                        clearable
                    />
                </div>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="quotes"
            row-key="uuid"
            :row-href="(r) => cotizaciones.edit(r.uuid).url"
            :loading="loading"
            :only="['quotes']"
            item-label="cotizaciones"
            empty-title="Sin cotizaciones en esta vista"
            empty-description="Crea una desde la pestaña «Cotizaciones» de un proyecto."
        >
            <template #cell-title="{ row }">
                <Link
                    :href="cotizaciones.edit(row.uuid).url"
                    class="flex flex-col"
                >
                    <span class="font-medium hover:underline"
                        >v{{ row.version }} · {{ row.title }}</span
                    >
                    <span class="text-xs text-muted-foreground tabular-nums">{{
                        row.folio
                    }}</span>
                </Link>
            </template>
            <template #cell-project="{ row }">
                <span class="flex flex-col">
                    <span>{{ row.project.name }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        row.project.customer
                    }}</span>
                </span>
            </template>
            <template #cell-status="{ row }"
                ><StatusBadge
                    :tone="row.status.tone"
                    :label="row.status.label"
                    size="sm"
            /></template>
            <template #cell-total="{ row }"
                ><Money :value="row.total" class="font-medium tabular-nums"
            /></template>
            <template #cell-valid_until="{ row }"
                ><DateText :value="row.valid_until"
            /></template>
        </DataView>
    </PageContainer>
</template>
