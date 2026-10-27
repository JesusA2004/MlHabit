<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileCheck2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    DataView,
    DateText,
    EmptyState,
    FilterBar,
    PageContainer,
    PageHeader,
    StatusBadge,
} from '@/components/ds';
import type { DataColumn, Tone } from '@/components/ds';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { BreadcrumbItem, LengthAwarePaginator } from '@/types';

type Option = { value: string; label: string; tone?: Tone };

type Row = {
    uuid: string;
    folio: string;
    status: Option;
    customer: { uuid: string; name: string };
    lead: { uuid: string; folio: string };
    reviewer: { id: number; name: string } | null;
    submittedAt: string | null;
    reviewedAt: string | null;
    createdAt: string | null;
};

type Tray = 'por_revisar' | 'en_revision' | 'todas';

const props = defineProps<{
    prequalifications: LengthAwarePaginator<Row>;
    filters: { bandeja: Tray; estado?: string; q?: string };
    counts: { por_revisar: number; en_revision: number };
    options: { statuses: Option[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Precalificaciones', href: '/admin/precalificaciones' },
        ] as BreadcrumbItem[],
    },
});

const ALL = '__todos__';
const tray = ref<Tray>(props.filters.bandeja ?? 'por_revisar');
const search = ref(props.filters.q ?? '');
const estado = ref(props.filters.estado ?? ALL);
const loading = ref(false);
const activeCount = computed(() => (estado.value !== ALL ? 1 : 0));

function visit() {
    router.get(
        '/admin/precalificaciones',
        {
            bandeja: tray.value,
            q: search.value || undefined,
            estado: estado.value === ALL ? undefined : estado.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['prequalifications', 'filters', 'counts'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

watch([tray, search, estado], visit);

function clearFilters() {
    search.value = '';
    estado.value = ALL;
}

const columns: DataColumn<Row>[] = [
    { key: 'folio', label: 'Solicitud', primary: true },
    { key: 'status', label: 'Estado' },
    { key: 'lead', label: 'Prospecto' },
    { key: 'submittedAt', label: 'Enviada' },
    { key: 'reviewer', label: 'Revisa', hideOnMobile: true },
];

const emptyCopy: Record<Tray, { title: string; description: string }> = {
    por_revisar: {
        title: 'Nada por revisar.',
        description:
            'Cuando un cliente envíe su información de precalificación aparecerá aquí, en orden de llegada.',
    },
    en_revision: {
        title: 'No hay solicitudes en revisión.',
        description:
            'Las que tomes para revisar o a las que les pidas información quedarán aquí.',
    },
    todas: {
        title: 'Aún no hay precalificaciones.',
        description:
            'Se inician desde el detalle de un prospecto con “Iniciar precalificación”.',
    },
};

const empty = computed(() =>
    search.value || activeCount.value > 0
        ? {
              title: 'Sin solicitudes con estos filtros',
              description: 'Cambia la búsqueda o limpia los filtros.',
          }
        : emptyCopy[tray.value],
);
</script>

<template>
    <Head title="Precalificaciones" />

    <PageContainer>
        <PageHeader
            title="Precalificaciones"
            description="Revisión manual de solicitudes de pago a plazos. Ninguna decisión es automática y ningún resultado es una aprobación de crédito."
        />

        <Tabs v-model="tray">
            <TabsList class="w-full justify-start overflow-x-auto sm:w-auto">
                <TabsTrigger value="por_revisar" class="gap-2">
                    Por revisar
                    <span
                        v-if="counts.por_revisar > 0"
                        class="rounded-full bg-primary px-1.5 text-[11px] leading-5 font-semibold text-primary-foreground tabular-nums"
                        >{{ counts.por_revisar }}</span
                    >
                </TabsTrigger>
                <TabsTrigger value="en_revision" class="gap-2">
                    En revisión
                    <span
                        v-if="counts.en_revision > 0"
                        class="rounded-full bg-muted px-1.5 text-[11px] leading-5 font-semibold tabular-nums"
                        >{{ counts.en_revision }}</span
                    >
                </TabsTrigger>
                <TabsTrigger value="todas">Todas</TabsTrigger>
            </TabsList>
        </Tabs>

        <FilterBar
            v-model:search="search"
            placeholder="Folio o nombre del cliente"
            :active-count="activeCount"
            @clear="clearFilters"
        >
            <template #filters>
                <div class="grid gap-1.5">
                    <Label class="text-xs text-muted-foreground">Estado</Label>
                    <Select v-model="estado">
                        <SelectTrigger
                            class="w-full md:w-60"
                            aria-label="Filtrar por estado"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL"
                                >Todos los estados</SelectItem
                            >
                            <SelectItem
                                v-for="s in options.statuses"
                                :key="s.value"
                                :value="s.value"
                                >{{ s.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="prequalifications"
            :loading="loading"
            row-key="uuid"
            :row-href="(row) => `/admin/precalificaciones/${row.uuid}`"
            item-label="solicitudes"
            caption="Precalificaciones"
        >
            <template #cell-folio="{ row }">
                <Link
                    :href="`/admin/precalificaciones/${row.uuid}`"
                    class="group flex flex-col gap-0.5"
                >
                    <span
                        class="font-medium text-foreground group-hover:underline"
                        >{{ row.customer.name }}</span
                    >
                    <span class="text-xs text-muted-foreground tabular-nums">{{
                        row.folio
                    }}</span>
                </Link>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="row.status.tone ?? 'neutral'"
                    :label="row.status.label"
                    size="sm"
                />
            </template>
            <template #cell-lead="{ row }">
                <span class="text-sm tabular-nums">{{ row.lead.folio }}</span>
            </template>
            <template #cell-submittedAt="{ row }">
                <DateText
                    :value="row.submittedAt"
                    relative
                    fallback="Sin enviar"
                    class="text-sm"
                />
            </template>
            <template #cell-reviewer="{ row }">
                <span :class="!row.reviewer && 'text-muted-foreground'">{{
                    row.reviewer?.name ?? 'Sin asignar'
                }}</span>
            </template>
            <template #empty>
                <EmptyState
                    :icon="FileCheck2"
                    :title="empty.title"
                    :description="empty.description"
                />
            </template>
        </DataView>
    </PageContainer>
</template>
