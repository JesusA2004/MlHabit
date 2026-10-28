<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import { Label } from '@/components/ui/label';
import disenos from '@/routes/admin/disenos';
import type { BreadcrumbItem } from '@/types';
import type { EnumValue } from '@/types/projects';
import type { Paginator } from '@/types/ui';

type Row = {
    id: number;
    number: number;
    title: string;
    status: EnumValue;
    project: {
        uuid: string;
        folio: string;
        name: string;
        customer: string | null;
    };
    created_by: string | null;
    sent_at: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    designs: Paginator<Row>;
    filters: { estado: string | null; q: string };
    statuses: EnumValue[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Diseño', href: '/admin/disenos' },
        ] as BreadcrumbItem[],
    },
});

const search = ref(props.filters.q ?? '');
const estado = ref<string | null>(props.filters.estado);
const loading = ref(false);

function reload() {
    router.get(
        disenos.index().url,
        { q: search.value || undefined, estado: estado.value ?? undefined },
        {
            preserveState: true,
            replace: true,
            only: ['designs', 'filters'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}
watch([search, estado], reload);

const quick = [
    { value: 'client_approved', label: 'Por aprobar técnicamente' },
    { value: 'sent_to_client', label: 'En revisión del cliente' },
    { value: 'changes_requested', label: 'Con cambios solicitados' },
    { value: 'draft', label: 'Borradores' },
];

const href = (row: Row) => `/admin/proyectos/${row.project.uuid}?tab=diseno`;

const columns: DataColumn<Row>[] = [
    { key: 'title', label: 'Diseño', primary: true },
    { key: 'project', label: 'Proyecto', value: (r) => r.project.name },
    { key: 'status', label: 'Estado' },
    { key: 'created_by', label: 'Diseñador', hideOnMobile: true },
    { key: 'updated_at', label: 'Actualizado', align: 'right' },
];
</script>

<template>
    <Head title="Diseño" />

    <PageContainer>
        <PageHeader
            title="Diseño"
            description="Versiones de diseño de todos los proyectos. Fabricación solo arranca con la aprobación técnica."
        />

        <div class="-mx-1 flex gap-1 overflow-x-auto px-1">
            <button
                v-for="q in quick"
                :key="q.value"
                type="button"
                :aria-pressed="estado === q.value"
                class="h-9 shrink-0 rounded-full border px-3.5 text-sm font-medium transition-[color,background-color,border-color,scale] duration-150 active:scale-[0.96] pointer-coarse:h-11"
                :class="
                    estado === q.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                @click="estado = estado === q.value ? null : q.value"
            >
                {{ q.label }}
            </button>
        </div>

        <FilterBar
            v-model:search="search"
            placeholder="Buscar por folio o nombre del proyecto…"
            :active-count="estado ? 1 : 0"
            @clear="estado = null"
        >
            <template #filters>
                <div class="grid gap-1.5 md:w-56">
                    <Label class="md:sr-only" for="d-estado">Estado</Label>
                    <SearchSelect
                        id="d-estado"
                        v-model="estado"
                        :options="
                            statuses.map((s) => ({
                                value: s.value,
                                label: s.label,
                            }))
                        "
                        placeholder="Vigentes"
                        clearable
                    />
                </div>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="designs"
            :row-href="href"
            :loading="loading"
            :only="['designs']"
            item-label="diseños"
            empty-title="Nada pendiente aquí"
            empty-description="Cuando haya versiones de diseño en este estado aparecerán aquí."
        >
            <template #cell-title="{ row }">
                <Link :href="href(row)" class="font-medium hover:underline"
                    >v{{ row.number }} · {{ row.title }}</Link
                >
            </template>
            <template #cell-project="{ row }">
                <span class="flex flex-col">
                    <span>{{ row.project.name }}</span>
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{{ row.project.folio
                        }}<template v-if="row.project.customer">
                            · {{ row.project.customer }}</template
                        ></span
                    >
                </span>
            </template>
            <template #cell-status="{ row }"
                ><StatusBadge
                    :tone="row.status.tone"
                    :label="row.status.label"
                    size="sm"
            /></template>
            <template #cell-updated_at="{ row }"
                ><DateText
                    :value="row.updated_at"
                    relative
                    class="text-sm text-muted-foreground"
            /></template>
        </DataView>
    </PageContainer>
</template>
