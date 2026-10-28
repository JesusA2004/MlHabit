<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { MapPinned, Plus, Settings2, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
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
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { LengthAwarePaginator } from '@/types/ui';

type Option = { value: string; label: string; tone?: string };

type VisitRow = {
    uuid: string;
    folio: string;
    customer: string;
    zone: string;
    technician: string | null;
    startsAt: string;
    endsAt: string;
    status: Option;
    fee: string;
    attention: string | null;
    holdExpiresAt: string | null;
};

type TabKey =
    | 'hoy'
    | 'proximas'
    | 'pendientes'
    | 'atencion'
    | 'realizadas'
    | 'todas';

const props = defineProps<{
    visits: LengthAwarePaginator<VisitRow>;
    tab: TabKey;
    counts: Record<TabKey, number>;
    filters: { zona?: string; tecnico?: string; estado?: string; q?: string };
    options: { zones: Option[]; technicians: Option[]; statuses: Option[] };
    can: { create: boolean; policy: boolean };
    hasActivePolicy: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Visitas', href: '/admin/visitas' },
        ],
    },
});

const ALL = '__all';
const tone = (o: { tone?: string } | null | undefined) =>
    (o?.tone ?? 'neutral') as Tone;

const tabs: { value: TabKey; label: string }[] = [
    { value: 'hoy', label: 'Hoy' },
    { value: 'proximas', label: 'Próximas' },
    { value: 'pendientes', label: 'Pendientes de pago' },
    { value: 'atencion', label: 'Requieren atención' },
    { value: 'realizadas', label: 'Realizadas' },
    { value: 'todas', label: 'Todas' },
];

const vista = ref<TabKey>(props.tab);
const search = ref(props.filters.q ?? '');
const zona = ref(props.filters.zona ?? ALL);
const tecnico = ref(props.filters.tecnico ?? ALL);
const estado = ref(props.filters.estado ?? ALL);
const loading = ref(false);

const activeCount = computed(
    () =>
        [zona.value, tecnico.value, estado.value].filter((v) => v !== ALL)
            .length,
);

function reload() {
    const pick = (v: string) => (v === ALL ? undefined : v);

    router.get(
        '/admin/visitas',
        {
            vista: vista.value,
            q: search.value || undefined,
            zona: pick(zona.value),
            tecnico: pick(tecnico.value),
            estado: pick(estado.value),
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

watch([vista, search, zona, tecnico, estado], reload);

function clearFilters() {
    zona.value = ALL;
    tecnico.value = ALL;
    estado.value = ALL;
}

const now = ref(Date.now());
useIntervalFn(() => (now.value = Date.now()), 30_000);

function holdLeft(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    const minutes = Math.ceil((new Date(iso).getTime() - now.value) / 60_000);

    if (minutes <= 0) {
        return 'Apartado vencido';
    }

    return minutes >= 120
        ? `Apartado ${Math.floor(minutes / 60)} h más`
        : `Apartado ${minutes} min más`;
}

const columns: DataColumn<VisitRow>[] = [
    { key: 'folio', label: 'Visita', primary: true },
    { key: 'startsAt', label: 'Fecha y horario' },
    { key: 'zone', label: 'Zona', hideOnMobile: true },
    { key: 'technician', label: 'Técnico' },
    { key: 'status', label: 'Estado' },
];

const showUrl = (uuid: string) => `/admin/visitas/${uuid}`;
const timeRange = (row: VisitRow) =>
    `${formatDate(row.startsAt, 'time')} – ${formatDate(row.endsAt, 'time')}`;
</script>

<template>
    <Head title="Visitas técnicas" />

    <PageContainer>
        <PageHeader
            title="Visitas técnicas"
            description="Agenda de visitas a domicilio: pagos, técnicos asignados y seguimiento."
        >
            <template #actions>
                <Button v-if="can.policy" variant="outline" as-child>
                    <Link href="/admin/visitas/politica"
                        ><Settings2 class="size-4" /> Política</Link
                    >
                </Button>
                <Button v-if="can.create" as-child>
                    <Link href="/admin/visitas/crear"
                        ><Plus class="size-4" /> Nueva visita</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <FormStatus
            v-if="!hasActivePolicy"
            tone="info"
            message="No hay una política de visita activa: el sitio muestra «Por ahora agenda tu visita con un asesor» y no se pueden apartar visitas nuevas."
        />

        <nav
            aria-label="Vistas de visitas"
            class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
        >
            <ul class="flex w-max gap-1.5">
                <li v-for="t in tabs" :key="t.value">
                    <button
                        type="button"
                        :aria-pressed="vista === t.value"
                        :class="
                            cn(
                                'flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm transition-[color,background-color,border-color,scale] duration-150 active:scale-[0.96]',
                                vista === t.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'bg-card hover:bg-muted',
                                t.value === 'atencion' &&
                                    counts.atencion > 0 &&
                                    vista !== t.value &&
                                    'border-warning/50',
                            )
                        "
                        @click="vista = t.value"
                    >
                        {{ t.label }}
                        <span class="tabular-nums opacity-70">{{
                            counts[t.value]
                        }}</span>
                    </button>
                </li>
            </ul>
        </nav>

        <FilterBar
            v-model:search="search"
            placeholder="Folio, nombre o teléfono"
            :active-count="activeCount"
            @clear="clearFilters"
        >
            <template #filters>
                <Select v-model="zona">
                    <SelectTrigger class="md:w-44" aria-label="Zona"
                        ><SelectValue placeholder="Zona"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todas las zonas</SelectItem>
                        <SelectItem
                            v-for="z in options.zones"
                            :key="z.value"
                            :value="z.value"
                            >{{ z.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="tecnico">
                    <SelectTrigger class="md:w-48" aria-label="Técnico"
                        ><SelectValue placeholder="Técnico"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todos los técnicos</SelectItem>
                        <SelectItem
                            v-for="t in options.technicians"
                            :key="t.value"
                            :value="t.value"
                            >{{ t.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="estado">
                    <SelectTrigger class="md:w-44" aria-label="Estado"
                        ><SelectValue placeholder="Estado"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todos los estados</SelectItem>
                        <SelectItem
                            v-for="s in options.statuses"
                            :key="s.value"
                            :value="s.value"
                            >{{ s.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="visits"
            :loading="loading"
            row-key="uuid"
            :row-href="(row) => showUrl(row.uuid)"
            item-label="visitas"
            caption="Visitas técnicas"
        >
            <template #cell-folio="{ row }">
                <div class="min-w-0">
                    <Link
                        :href="showUrl(row.uuid)"
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.customer }}</Link
                    >
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ row.folio }}
                    </p>
                    <p
                        v-if="row.attention"
                        class="mt-1 flex items-start gap-1 text-xs text-warning-ink"
                    >
                        <TriangleAlert
                            class="mt-px size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="line-clamp-2">{{ row.attention }}</span>
                    </p>
                </div>
            </template>
            <template #cell-startsAt="{ row }">
                <div class="text-sm">
                    <p class="first-letter:uppercase">
                        {{ formatDate(row.startsAt, 'full') }}
                    </p>
                    <p class="text-muted-foreground tabular-nums">
                        {{ timeRange(row) }}
                    </p>
                </div>
            </template>
            <template #cell-technician="{ row }">
                <span
                    :class="
                        row.technician
                            ? 'text-sm'
                            : 'text-sm text-muted-foreground'
                    "
                    >{{ row.technician ?? 'Sin asignar' }}</span
                >
            </template>
            <template #cell-status="{ row }">
                <div class="flex flex-col items-start gap-1">
                    <StatusBadge
                        :tone="tone(row.status)"
                        :label="row.status.label"
                        size="sm"
                    />
                    <span
                        v-if="row.holdExpiresAt"
                        class="text-xs text-muted-foreground tabular-nums"
                        >{{ holdLeft(row.holdExpiresAt) }}</span
                    >
                </div>
            </template>
            <template #empty>
                <EmptyState
                    :icon="MapPinned"
                    title="Sin visitas en esta vista"
                    description="Cuando un cliente aparte o pague una visita técnica aparecerá aquí."
                />
            </template>
        </DataView>
    </PageContainer>
</template>
