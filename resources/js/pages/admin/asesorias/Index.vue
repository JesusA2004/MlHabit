<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    CircleAlert,
    CreditCard,
    MessageCircle,
    Phone,
    Timer,
    Video,
} from '@lucide/vue';
import type { Component } from 'vue';
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
import { formatMoney } from '@/lib/format';
import type { BreadcrumbItem, LengthAwarePaginator } from '@/types';

type Option = { value: string; label: string; tone?: Tone };

type ConsultationRow = {
    uuid: string;
    folio: string;
    startsAt: string;
    endsAt: string;
    status: Option;
    channel: Option;
    outcome: Option | null;
    advisor: { id: number; name: string } | null;
    meetingUrl: string | null;
    isActive: boolean;
    price: string;
    holdExpiresAt: string | null;
    needsAttention: boolean;
    payment: {
        uuid: string;
        folio: string;
        amount: string;
        status: Option;
        paidAt: string | null;
    } | null;
    lead: {
        uuid: string;
        folio: string;
        customer: { name: string; phone: string | null };
    };
};

type Period = 'hoy' | 'proximas' | 'pasadas' | 'todas';

const props = defineProps<{
    consultations: LengthAwarePaginator<ConsultationRow>;
    filters: { periodo: Period; estado?: string; asesor?: string; q?: string };
    counts: { hoy: number };
    options: {
        statuses: Option[];
        advisors: { value: number; label: string }[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Asesorías', href: '/admin/asesorias' },
        ] as BreadcrumbItem[],
    },
});

const ALL = '__todos__';
const period = ref<Period>(props.filters.periodo ?? 'proximas');
const search = ref(props.filters.q ?? '');
const estado = ref(props.filters.estado ?? ALL);
const asesor = ref(props.filters.asesor ?? ALL);
const loading = ref(false);

const activeCount = computed(
    () => [estado.value !== ALL, asesor.value !== ALL].filter(Boolean).length,
);

function visit() {
    router.get(
        '/admin/asesorias',
        {
            periodo: period.value,
            q: search.value || undefined,
            estado: estado.value === ALL ? undefined : estado.value,
            asesor: asesor.value === ALL ? undefined : asesor.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['consultations', 'filters', 'counts'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

watch([period, search, estado, asesor], visit);

function clearFilters() {
    search.value = '';
    estado.value = ALL;
    asesor.value = ALL;
}

const channelIcons: Record<string, Component> = {
    video: Video,
    phone: Phone,
    whatsapp: MessageCircle,
};

const columns: DataColumn<ConsultationRow>[] = [
    { key: 'startsAt', label: 'Fecha y hora', primary: true },
    { key: 'customer', label: 'Cliente' },
    { key: 'channel', label: 'Medio' },
    { key: 'advisor', label: 'Asesor', hideOnMobile: true },
    { key: 'status', label: 'Estado' },
    { key: 'payment', label: 'Pago' },
    { key: 'outcome', label: 'Resultado', hideOnMobile: true },
];

const emptyCopy: Record<Period, { title: string; description: string }> = {
    hoy: {
        title: 'No hay asesorías hoy.',
        description: 'Las citas del día aparecerán aquí en orden de hora.',
    },
    proximas: {
        title: 'No hay asesorías próximas.',
        description:
            'Cuando un cliente agende desde su enlace o las agendes desde un prospecto, aparecerán aquí.',
    },
    pasadas: {
        title: 'Aún no hay asesorías pasadas.',
        description: 'Aquí verás el historial y los resultados registrados.',
    },
    todas: {
        title: 'Aún no hay asesorías.',
        description: 'Agenda la primera desde el detalle de un prospecto.',
    },
};

const empty = computed(() =>
    search.value || activeCount.value > 0
        ? {
              title: 'Sin asesorías con estos filtros',
              description: 'Cambia la búsqueda o limpia los filtros.',
          }
        : emptyCopy[period.value],
);

const timeFmt = new Intl.DateTimeFormat('es-MX', {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: 'America/Mexico_City',
});
const time = (iso: string) => timeFmt.format(new Date(iso));

const SETTLED = ['paid', 'partially_refunded', 'refunded', 'disputed'];

function paymentTone(row: ConsultationRow): Tone {
    if (!row.payment) {
        return 'neutral';
    }

    if (SETTLED.includes(row.payment.status.value)) {
        return (row.payment.status.tone ?? 'success') as Tone;
    }

    return row.payment.status.value === 'failed' ? 'danger' : 'warning';
}

function paymentLabel(row: ConsultationRow): string {
    if (!row.payment) {
        return 'Sin cobro';
    }

    if (row.payment.status.value === 'pending') {
        return 'Por pagar';
    }

    return row.payment.status.label;
}
</script>

<template>
    <Head title="Asesorías" />

    <PageContainer>
        <PageHeader
            title="Asesorías"
            description="Citas remotas con prospectos: confirma, registra el resultado y da el siguiente paso."
        />

        <Tabs v-model="period">
            <TabsList class="w-full justify-start overflow-x-auto sm:w-auto">
                <TabsTrigger value="hoy" class="gap-2">
                    Hoy
                    <span
                        v-if="counts.hoy > 0"
                        class="rounded-full bg-primary px-1.5 text-[11px] leading-5 font-semibold text-primary-foreground tabular-nums"
                        :aria-label="`${counts.hoy} hoy`"
                        >{{ counts.hoy }}</span
                    >
                </TabsTrigger>
                <TabsTrigger value="proximas">Próximas</TabsTrigger>
                <TabsTrigger value="pasadas">Pasadas</TabsTrigger>
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
                            class="w-full md:w-44"
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
                <div class="grid gap-1.5">
                    <Label class="text-xs text-muted-foreground">Asesor</Label>
                    <Select v-model="asesor">
                        <SelectTrigger
                            class="w-full md:w-52"
                            aria-label="Filtrar por asesor"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL"
                                >Todos los asesores</SelectItem
                            >
                            <SelectItem value="yo">Mis asesorías</SelectItem>
                            <SelectItem
                                v-for="a in options.advisors"
                                :key="a.value"
                                :value="String(a.value)"
                                >{{ a.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="consultations"
            :loading="loading"
            row-key="uuid"
            :row-href="(row) => `/admin/asesorias/${row.uuid}`"
            item-label="asesorías"
            caption="Asesorías"
        >
            <template #cell-startsAt="{ row }">
                <Link
                    :href="`/admin/asesorias/${row.uuid}`"
                    class="group flex flex-col gap-0.5"
                >
                    <span
                        class="font-medium text-foreground group-hover:underline first-letter:uppercase"
                    >
                        <DateText :value="row.startsAt" format="full" />
                    </span>
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{{ time(row.startsAt) }} – {{ time(row.endsAt) }} ·
                        {{ row.folio }}</span
                    >
                </Link>
            </template>
            <template #cell-customer="{ row }">
                <span class="flex flex-col">
                    <span class="text-sm">{{ row.lead.customer.name }}</span>
                    <span class="text-xs text-muted-foreground tabular-nums">{{
                        row.lead.folio
                    }}</span>
                </span>
            </template>
            <template #cell-channel="{ row }">
                <span class="inline-flex items-center gap-1.5 text-sm">
                    <component
                        :is="channelIcons[row.channel.value] ?? CalendarClock"
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {{ row.channel.label }}
                </span>
            </template>
            <template #cell-advisor="{ row }">
                <span :class="!row.advisor && 'text-muted-foreground'">{{
                    row.advisor?.name ?? 'Sin asesor'
                }}</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="row.status.tone ?? 'neutral'"
                    :label="row.status.label"
                    size="sm"
                />
            </template>
            <template #cell-payment="{ row }">
                <span class="flex flex-col items-start gap-1">
                    <span class="inline-flex items-center gap-1.5">
                        <StatusBadge
                            :tone="paymentTone(row)"
                            :label="paymentLabel(row)"
                            size="sm"
                        />
                        <CircleAlert
                            v-if="row.needsAttention"
                            class="size-4 text-danger"
                            aria-label="Requiere atención"
                        />
                    </span>
                    <span
                        class="inline-flex items-center gap-1 text-xs text-muted-foreground tabular-nums"
                    >
                        <CreditCard class="size-3" aria-hidden="true" />
                        {{ formatMoney(row.payment?.amount ?? row.price) }}
                        <template v-if="row.holdExpiresAt">
                            ·
                            <Timer class="size-3" aria-hidden="true" /> apartado
                            hasta {{ time(row.holdExpiresAt) }}
                        </template>
                    </span>
                </span>
            </template>
            <template #cell-outcome="{ row }">
                <span v-if="row.outcome" class="text-sm">{{
                    row.outcome.label
                }}</span>
                <span v-else class="text-sm text-muted-foreground">—</span>
            </template>
            <template #empty>
                <EmptyState
                    :icon="CalendarClock"
                    :title="empty.title"
                    :description="empty.description"
                />
            </template>
        </DataView>
    </PageContainer>
</template>
