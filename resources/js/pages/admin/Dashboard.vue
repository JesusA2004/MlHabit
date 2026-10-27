<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Briefcase,
    CalendarClock,
    CircleCheck,
    ClipboardList,
    FolderKanban,
    HardHat,
    MapPinned,
    MessagesSquare,
    ShieldAlert,
    Wallet,
    Wrench,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import LeadTrend from '@/components/platform/dashboard/LeadTrend.vue';
import StageBar from '@/components/platform/dashboard/StageBar.vue';
import type { Stage } from '@/components/platform/dashboard/StageBar.vue';
import StatTile from '@/components/platform/dashboard/StatTile.vue';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDate, formatNumber } from '@/lib/format';

type LeadWindow = { current: number; previous: number; delta: number };

type Commercial = {
    newLeads?: Record<'7' | '30', LeadWindow>;
    uncontacted?: number;
    followUpHours?: number;
    conversion?: { contracted: number; leads: number; rate: string | null };
    trend?: { date: string; count: number }[];
    consultationsToday?: number;
};

type AgendaItem = {
    kind: 'consultation' | 'visit' | 'installation';
    kindLabel: string;
    startsAt: string;
    endsAt: string | null;
    folio: string | null;
    title: string;
    owner: string | null;
    status: string;
    statusTone: string;
    href: string;
};

type AttentionItem = {
    key: string;
    label: string;
    count: number;
    href: string;
    tone: string;
};

type SeverityRow = {
    severity: string;
    label: string;
    tone: string;
    count: number;
};

const props = defineProps<{
    greeting: { name: string; roleLabel: string; isSuperAdmin: boolean };
    sections: Record<string, boolean>;
    commercial: Commercial | null;
    visits: {
        today: number;
        pendingPayment: number;
        paidNext7: number;
        completedThisMonth: number;
    } | null;
    projects: {
        active: number;
        stages: Stage[];
        pendingDeposit: number;
        inFabrication: number;
        inInstallation: number;
        pendingQuality: number;
    } | null;
    finance: {
        collectedThisMonth: { net: string; gross: string; payments: number };
        failedLast7: number;
        refundsThisMonth: { amount: string; count: number };
    } | null;
    operations: {
        incidents: { open: number; bySeverity: SeverityRow[] } | null;
        activeWarranties: number | null;
    } | null;
    agenda: AgendaItem[] | null;
    attention: AttentionItem[];
    generatedAt?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inicio', href: '/admin' }],
    },
});

const TONES: Tone[] = [
    'success',
    'warning',
    'danger',
    'info',
    'neutral',
    'brand',
];
const asTone = (tone: string): Tone =>
    TONES.includes(tone as Tone) ? (tone as Tone) : 'neutral';

const hasAnySection = computed(() =>
    Object.values(props.sections).some(Boolean),
);

const leadWindow = ref<'7' | '30'>('7');
function onWindow(value: unknown) {
    if (value === '7' || value === '30') {
        leadWindow.value = value;
    }
}

const currentLeads = computed(
    () => props.commercial?.newLeads?.[leadWindow.value] ?? null,
);

const conversionRate = computed(() => {
    const rate = props.commercial?.conversion?.rate;

    return rate === null || rate === undefined
        ? null
        : `${rate.replace('.', ',').replace(',0', '')} %`;
});

const kindIcon: Record<AgendaItem['kind'], Component> = {
    consultation: MessagesSquare,
    visit: MapPinned,
    installation: Wrench,
};

const today = computed(() =>
    formatDate(props.generatedAt ?? new Date(), 'full'),
);
</script>

<template>
    <Head title="Inicio" />

    <PageContainer>
        <PageHeader
            :title="`Hola, ${props.greeting.name}`"
            :description="
                today
                    ? `${today} · ${props.greeting.roleLabel}`
                    : props.greeting.roleLabel
            "
        />

        <!-- Sin permiso de tablero -->
        <EmptyState
            v-if="!hasAnySection"
            :icon="Briefcase"
            title="Te damos la bienvenida a ML Habitat"
            description="Usa el menú lateral para entrar a las secciones de tu rol. Si necesitas ver el tablero con indicadores, pide acceso a un administrador."
        />

        <template v-else>
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Requiere tu atención -->
                <SectionCard
                    title="Requiere tu atención"
                    :icon="ClipboardList"
                    flush
                    class="lg:col-span-1"
                >
                    <ul v-if="props.attention.length" class="divide-y">
                        <li v-for="item in props.attention" :key="item.key">
                            <Link
                                :href="item.href"
                                class="flex min-h-12 items-center gap-3 px-4 py-3 text-sm transition-colors hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:outline-none sm:px-5"
                            >
                                <span class="min-w-0 flex-1">{{
                                    item.label
                                }}</span>
                                <StatusBadge
                                    :tone="asTone(item.tone)"
                                    :label="formatNumber(item.count)"
                                    size="sm"
                                />
                            </Link>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        :icon="CircleCheck"
                        title="Todo al día: no hay pendientes."
                        compact
                        :bordered="false"
                        class="py-8"
                    />
                </SectionCard>

                <!-- Agenda de hoy -->
                <SectionCard
                    v-if="props.agenda !== null"
                    title="Agenda de hoy"
                    :icon="CalendarClock"
                    flush
                    class="lg:col-span-2"
                >
                    <ul v-if="props.agenda.length" class="divide-y">
                        <li
                            v-for="item in props.agenda"
                            :key="`${item.kind}-${item.href}`"
                        >
                            <Link
                                :href="item.href"
                                class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:outline-none sm:px-5"
                            >
                                <span
                                    class="w-[4.75rem] shrink-0 pt-0.5 text-sm font-semibold whitespace-nowrap tabular-nums"
                                    >{{
                                        formatDate(item.startsAt, 'time')
                                    }}</span
                                >
                                <component
                                    :is="kindIcon[item.kind]"
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span
                                    class="flex min-w-0 flex-1 flex-col gap-0.5"
                                >
                                    <span
                                        class="truncate text-sm font-medium"
                                        >{{ item.title }}</span
                                    >
                                    <span
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ item.kindLabel
                                        }}<template v-if="item.folio">
                                            · {{ item.folio }}</template
                                        ><template v-if="item.owner">
                                            · {{ item.owner }}</template
                                        >
                                    </span>
                                </span>
                                <StatusBadge
                                    :tone="asTone(item.statusTone)"
                                    :label="item.status"
                                    size="sm"
                                    class="shrink-0"
                                />
                            </Link>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        :icon="CalendarClock"
                        title="No hay citas programadas para hoy."
                        compact
                        :bordered="false"
                        class="py-8"
                    />
                </SectionCard>
            </div>

            <!-- Comercial -->
            <SectionCard
                v-if="props.commercial !== null"
                title="Comercial"
                :icon="Briefcase"
            >
                <template v-if="props.commercial.newLeads" #actions>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        :model-value="leadWindow"
                        aria-label="Periodo de comparación"
                        @update:model-value="onWindow"
                    >
                        <ToggleGroupItem value="7">7 días</ToggleGroupItem>
                        <ToggleGroupItem value="30">30 días</ToggleGroupItem>
                    </ToggleGroup>
                </template>

                <div class="flex flex-col gap-5">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <StatTile
                            v-if="currentLeads"
                            label="Prospectos nuevos"
                            :value="currentLeads.current"
                            :delta="currentLeads.delta"
                            :delta-label="`vs ${leadWindow} días anteriores (${formatNumber(currentLeads.previous)})`"
                            href="/admin/prospectos"
                        />
                        <StatTile
                            v-if="props.commercial.uncontacted !== undefined"
                            label="Sin contactar a tiempo"
                            :value="props.commercial.uncontacted"
                            :hint="`Nuevos con más de ${props.commercial.followUpHours ?? 24} h sin primer contacto`"
                            :tone="
                                props.commercial.uncontacted > 0
                                    ? 'warning'
                                    : 'default'
                            "
                            href="/admin/prospectos?estado=new"
                        />
                        <StatTile
                            v-if="props.commercial.conversion"
                            label="Tasa de conversión"
                            :value="conversionRate"
                            :hint="
                                conversionRate === null
                                    ? 'Sin prospectos nuevos en los últimos 30 días; no hay base para calcularla.'
                                    : `Proyectos contratados ÷ prospectos nuevos (últimos 30 días): ${formatNumber(props.commercial.conversion.contracted)} de ${formatNumber(props.commercial.conversion.leads)}`
                            "
                        />
                        <StatTile
                            v-if="
                                props.commercial.consultationsToday !==
                                undefined
                            "
                            label="Asesorías hoy"
                            :value="props.commercial.consultationsToday"
                            href="/admin/asesorias"
                        />
                    </div>

                    <LeadTrend
                        v-if="
                            props.commercial.trend &&
                            props.commercial.trend.length
                        "
                        :points="props.commercial.trend"
                    />
                </div>
            </SectionCard>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Visitas -->
                <SectionCard
                    v-if="props.visits !== null"
                    title="Visitas técnicas"
                    :icon="MapPinned"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <StatTile
                            label="Hoy"
                            :value="props.visits.today"
                            href="/admin/visitas"
                        />
                        <StatTile
                            label="Pendientes de pago"
                            :value="props.visits.pendingPayment"
                            hint="Apartadas, esperando el cobro"
                            :tone="
                                props.visits.pendingPayment > 0
                                    ? 'warning'
                                    : 'default'
                            "
                        />
                        <StatTile
                            label="Pagadas, próximos 7 días"
                            :value="props.visits.paidNext7"
                        />
                        <StatTile
                            label="Realizadas este mes"
                            :value="props.visits.completedThisMonth"
                        />
                    </div>
                </SectionCard>

                <!-- Finanzas -->
                <SectionCard
                    v-if="props.finance !== null"
                    title="Finanzas"
                    :icon="Wallet"
                >
                    <div class="flex flex-col gap-4">
                        <div class="rounded-lg bg-primary-soft p-4">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Cobrado en el mes (neto de reembolsos)
                            </p>
                            <p
                                class="mt-1 font-display text-3xl font-semibold tracking-tight"
                            >
                                <Money
                                    :value="
                                        props.finance.collectedThisMonth.net
                                    "
                                />
                            </p>
                            <p
                                v-if="
                                    props.finance.collectedThisMonth.payments >
                                    0
                                "
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{
                                    formatNumber(
                                        props.finance.collectedThisMonth
                                            .payments,
                                    )
                                }}
                                {{
                                    props.finance.collectedThisMonth
                                        .payments === 1
                                        ? 'pago'
                                        : 'pagos'
                                }}
                                · bruto
                                <Money
                                    :value="
                                        props.finance.collectedThisMonth.gross
                                    "
                                />
                            </p>
                            <p
                                v-else
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Aún no hay movimientos este mes.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <StatTile
                                label="Pagos fallidos (7 días)"
                                :value="props.finance.failedLast7"
                                :tone="
                                    props.finance.failedLast7 > 0
                                        ? 'danger'
                                        : 'default'
                                "
                                href="/admin/pagos?estado=failed"
                            />
                            <div
                                class="flex min-w-0 flex-col gap-1 rounded-lg bg-muted/60 p-4"
                            >
                                <span
                                    class="text-xs font-medium text-muted-foreground"
                                    >Reembolsos del mes</span
                                >
                                <span
                                    class="font-display text-2xl font-semibold tracking-tight"
                                    ><Money
                                        :value="
                                            props.finance.refundsThisMonth
                                                .amount
                                        "
                                /></span>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        formatNumber(
                                            props.finance.refundsThisMonth
                                                .count,
                                        )
                                    }}
                                    {{
                                        props.finance.refundsThisMonth.count ===
                                        1
                                            ? 'reembolso'
                                            : 'reembolsos'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </SectionCard>
            </div>

            <!-- Proyectos -->
            <SectionCard
                v-if="props.projects !== null"
                title="Proyectos activos"
                :icon="FolderKanban"
            >
                <template #actions>
                    <span class="text-sm font-semibold tabular-nums">{{
                        formatNumber(props.projects.active)
                    }}</span>
                </template>
                <div class="flex flex-col gap-5">
                    <StageBar
                        v-if="props.projects.stages.length"
                        :stages="props.projects.stages"
                    />
                    <EmptyState
                        v-else
                        :icon="FolderKanban"
                        title="Aún no hay proyectos activos."
                        description="Los proyectos aparecen aquí cuando un prospecto pasa a medición o diseño."
                        compact
                        :bordered="false"
                    />
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <StatTile
                            label="Pendientes de anticipo"
                            :value="props.projects.pendingDeposit"
                            :tone="
                                props.projects.pendingDeposit > 0
                                    ? 'warning'
                                    : 'default'
                            "
                            href="/admin/proyectos?estado=pending_deposit"
                        />
                        <StatTile
                            label="En fabricación"
                            :value="props.projects.inFabrication"
                            href="/admin/fabricacion"
                        />
                        <StatTile
                            label="En instalación"
                            :value="props.projects.inInstallation"
                            href="/admin/instalaciones"
                        />
                        <StatTile
                            label="Pendientes de calidad"
                            :value="props.projects.pendingQuality"
                            href="/admin/calidad"
                        />
                    </div>
                </div>
            </SectionCard>

            <!-- Operación -->
            <SectionCard
                v-if="props.operations !== null"
                title="Operación"
                :icon="HardHat"
            >
                <div
                    class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
                >
                    <div
                        v-if="props.operations.incidents"
                        class="flex flex-col gap-3"
                    >
                        <div class="flex items-baseline justify-between">
                            <h3
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <ShieldAlert
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                Incidencias abiertas
                            </h3>
                            <Link
                                href="/admin/incidencias"
                                class="text-sm font-semibold tabular-nums hover:underline"
                            >
                                {{
                                    formatNumber(
                                        props.operations.incidents.open,
                                    )
                                }}
                            </Link>
                        </div>
                        <ul
                            v-if="props.operations.incidents.open > 0"
                            class="grid grid-cols-2 gap-2 sm:grid-cols-4"
                        >
                            <li
                                v-for="row in props.operations.incidents
                                    .bySeverity"
                                :key="row.severity"
                                class="flex flex-col gap-1.5 rounded-lg bg-muted/60 p-3"
                            >
                                <StatusBadge
                                    :tone="asTone(row.tone)"
                                    :label="row.label"
                                    size="sm"
                                    class="self-start"
                                />
                                <span
                                    class="font-display text-xl font-semibold tabular-nums"
                                    >{{ formatNumber(row.count) }}</span
                                >
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">
                            Sin incidencias abiertas.
                        </p>
                    </div>
                    <StatTile
                        v-if="props.operations.activeWarranties !== null"
                        label="Garantías activas"
                        :value="props.operations.activeWarranties"
                        hint="Vigentes a la fecha"
                        href="/admin/garantias"
                    />
                </div>
            </SectionCard>
        </template>
    </PageContainer>
</template>
