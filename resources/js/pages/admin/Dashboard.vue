<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlarmClock,
    BadgeCheck,
    Briefcase,
    CalendarCheck,
    CalendarClock,
    ChevronRight,
    CircleCheck,
    ClipboardCheck,
    ClipboardList,
    CreditCard,
    Factory,
    FolderKanban,
    HandCoins,
    HardHat,
    MapPinned,
    MessagesSquare,
    Percent,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    Truck,
    Undo2,
    UserRoundPlus,
    Wallet,
    Wrench,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import AnimatedNumber from '@/components/platform/dashboard/AnimatedNumber.vue';
import LeadTrend from '@/components/platform/dashboard/LeadTrend.vue';
import StageBar from '@/components/platform/dashboard/StageBar.vue';
import type { Stage } from '@/components/platform/dashboard/StageBar.vue';
import StatTile from '@/components/platform/dashboard/StatTile.vue';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';

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

const kindAccent: Record<AgendaItem['kind'], string> = {
    consultation: 'bg-info-soft text-info-ink',
    visit: 'bg-primary-soft text-primary',
    installation: 'bg-[color-mix(in_srgb,var(--oak)_16%,white)] text-oak-ink',
};

const today = computed(() =>
    formatDate(props.generatedAt ?? new Date(), 'full'),
);

/** Saludo según la hora local de quien abre el panel. */
const salutation = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Buenos días';
    }

    return hour < 19 ? 'Buenas tardes' : 'Buenas noches';
});

const pendingTotal = computed(() =>
    props.attention.reduce((sum, item) => sum + item.count, 0),
);

const collectedNet = computed(() => {
    const amount = Number(props.finance?.collectedThisMonth.net ?? 0);

    return Number.isFinite(amount) ? amount : 0;
});

const money = (value: number) => formatMoney(value) ?? '—';
</script>

<template>
    <Head title="Inicio" />

    <PageContainer>
        <!-- Bienvenida -->
        <header
            class="relative isolate overflow-hidden rounded-2xl bg-primary px-5 py-6 text-primary-foreground shadow-md sm:px-7 sm:py-7"
        >
            <!-- Fondo: luz cálida y muestrario de acabados (decorativo) -->
            <div
                class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(40rem_16rem_at_100%_0%,rgb(176_125_79/0.35),transparent_70%),radial-gradient(30rem_14rem_at_0%_100%,rgb(255_255_255/0.08),transparent_70%)]"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute inset-y-0 right-0 -z-10 hidden w-56 sm:flex md:w-72"
                aria-hidden="true"
            >
                <span
                    v-for="(swatch, index) in [
                        'bg-linen/25',
                        'bg-input/25',
                        'bg-oak/45',
                        'bg-primary-soft/15',
                        'bg-espresso/35',
                    ]"
                    :key="swatch"
                    :class="[
                        'admin-grow-y h-[140%] flex-1 origin-bottom -skew-x-12',
                        swatch,
                    ]"
                    :style="{ animationDelay: `${120 + index * 70}ms` }"
                />
            </div>

            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="min-w-0 sm:pr-48 lg:pr-0">
                    <p
                        class="flex items-center gap-1.5 text-sm text-primary-foreground/75"
                    >
                        <Sparkles class="size-4" aria-hidden="true" />
                        {{ today }}
                    </p>
                    <h1
                        class="mt-2 font-display text-[1.75rem] leading-tight font-semibold tracking-tight md:text-[2.125rem]"
                    >
                        {{ salutation }}, {{ props.greeting.name }}
                    </h1>
                    <p class="mt-1 text-sm text-primary-foreground/80">
                        {{ props.greeting.roleLabel }} · Esto es lo que pasa hoy
                        en ML Habitat.
                    </p>
                </div>

                <dl
                    v-if="hasAnySection"
                    class="admin-stagger relative flex flex-wrap gap-2 [--stagger-base:200ms] sm:gap-3"
                >
                    <div
                        class="min-w-32 rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15 backdrop-blur-sm"
                    >
                        <dt class="text-xs text-primary-foreground/75">
                            Pendientes
                        </dt>
                        <dd class="mt-0.5 font-display text-2xl font-semibold">
                            <AnimatedNumber :value="pendingTotal" />
                        </dd>
                    </div>
                    <div
                        v-if="props.agenda !== null"
                        class="min-w-32 rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15 backdrop-blur-sm"
                    >
                        <dt class="text-xs text-primary-foreground/75">
                            Citas hoy
                        </dt>
                        <dd class="mt-0.5 font-display text-2xl font-semibold">
                            <AnimatedNumber :value="props.agenda.length" />
                        </dd>
                    </div>
                    <div
                        v-if="props.projects !== null"
                        class="min-w-32 rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15 backdrop-blur-sm"
                    >
                        <dt class="text-xs text-primary-foreground/75">
                            Proyectos activos
                        </dt>
                        <dd class="mt-0.5 font-display text-2xl font-semibold">
                            <AnimatedNumber :value="props.projects.active" />
                        </dd>
                    </div>
                </dl>
            </div>
        </header>

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
                    <template v-if="props.attention.length" #actions>
                        <StatusBadge
                            tone="warning"
                            :label="formatNumber(pendingTotal)"
                            size="sm"
                        />
                    </template>
                    <ul
                        v-if="props.attention.length"
                        class="admin-stagger divide-y"
                    >
                        <li v-for="item in props.attention" :key="item.key">
                            <Link
                                :href="item.href"
                                class="group flex min-h-12 items-center gap-3 px-4 py-3 text-sm transition-colors hover:bg-primary-soft/40 focus-visible:bg-primary-soft/40 focus-visible:outline-none sm:px-5"
                            >
                                <span
                                    class="min-w-0 flex-1 transition-colors group-hover:text-primary"
                                    >{{ item.label }}</span
                                >
                                <StatusBadge
                                    :tone="asTone(item.tone)"
                                    :label="formatNumber(item.count)"
                                    size="sm"
                                />
                                <ChevronRight
                                    class="size-4 shrink-0 -translate-x-1 text-muted-foreground opacity-0 transition-[opacity,translate] duration-200 group-hover:translate-x-0 group-hover:text-primary group-hover:opacity-100 group-focus-visible:translate-x-0 group-focus-visible:opacity-100"
                                    aria-hidden="true"
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
                    <template #actions>
                        <Link
                            href="/admin/agenda"
                            class="group inline-flex h-8 items-center gap-1 rounded-md px-2 text-sm font-medium text-primary transition-colors hover:bg-primary-soft"
                        >
                            Ver agenda
                            <ChevronRight
                                class="size-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </Link>
                    </template>
                    <ol
                        v-if="props.agenda.length"
                        class="admin-stagger divide-y"
                    >
                        <li
                            v-for="item in props.agenda"
                            :key="`${item.kind}-${item.href}`"
                        >
                            <Link
                                :href="item.href"
                                class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-primary-soft/40 focus-visible:bg-primary-soft/40 focus-visible:outline-none sm:gap-4 sm:px-5"
                            >
                                <span
                                    class="w-[4.75rem] shrink-0 rounded-md bg-muted/70 px-2 py-1 text-center text-sm font-semibold whitespace-nowrap tabular-nums transition-colors group-hover:bg-card"
                                    >{{
                                        formatDate(item.startsAt, 'time')
                                    }}</span
                                >
                                <span
                                    :class="[
                                        'hidden size-8 shrink-0 place-items-center rounded-lg transition-transform duration-200 group-hover:scale-110 sm:grid',
                                        kindAccent[item.kind],
                                    ]"
                                    aria-hidden="true"
                                >
                                    <component
                                        :is="kindIcon[item.kind]"
                                        class="size-4"
                                    />
                                </span>
                                <span
                                    class="flex min-w-0 flex-1 flex-col gap-0.5"
                                >
                                    <span
                                        class="truncate text-sm font-medium transition-colors group-hover:text-primary"
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
                    </ol>
                    <EmptyState
                        v-else
                        :icon="CalendarCheck"
                        title="No hay citas programadas para hoy."
                        description="Las asesorías, visitas e instalaciones del día aparecerán aquí."
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
                    <div
                        class="admin-stagger grid grid-cols-2 gap-3 lg:grid-cols-4"
                    >
                        <StatTile
                            v-if="currentLeads"
                            label="Prospectos nuevos"
                            :icon="UserRoundPlus"
                            :value="currentLeads.current"
                            :delta="currentLeads.delta"
                            :delta-label="`vs ${leadWindow} días anteriores (${formatNumber(currentLeads.previous)})`"
                            href="/admin/prospectos"
                        />
                        <StatTile
                            v-if="props.commercial.uncontacted !== undefined"
                            label="Sin contactar a tiempo"
                            :icon="AlarmClock"
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
                            :icon="Percent"
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
                            :icon="MessagesSquare"
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
                    <div class="admin-stagger grid grid-cols-2 gap-3">
                        <StatTile
                            label="Hoy"
                            :icon="CalendarClock"
                            :value="props.visits.today"
                            href="/admin/visitas"
                        />
                        <StatTile
                            label="Pendientes de pago"
                            :icon="CreditCard"
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
                            :icon="CalendarCheck"
                            :value="props.visits.paidNext7"
                        />
                        <StatTile
                            label="Realizadas este mes"
                            :icon="BadgeCheck"
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
                        <div
                            class="relative overflow-hidden rounded-xl border border-primary/15 bg-linear-to-br from-primary-soft via-primary-soft/70 to-card p-4 sm:p-5"
                        >
                            <TrendingUp
                                class="pointer-events-none absolute -right-3 -bottom-4 size-28 text-primary/[0.07]"
                                aria-hidden="true"
                            />
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Cobrado en el mes (neto de reembolsos)
                            </p>
                            <p
                                class="mt-1 font-display text-3xl font-semibold tracking-tight text-primary md:text-[2.125rem]"
                            >
                                <AnimatedNumber
                                    :value="collectedNet"
                                    :decimals="2"
                                    :format="money"
                                    :duration="1100"
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
                        <div class="admin-stagger grid grid-cols-2 gap-3">
                            <StatTile
                                label="Pagos fallidos (7 días)"
                                :icon="HandCoins"
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
                                    class="flex items-start justify-between gap-2"
                                >
                                    <span
                                        class="text-xs font-medium text-muted-foreground"
                                        >Reembolsos del mes</span
                                    >
                                    <span
                                        class="-mt-1 -mr-1 grid size-7 shrink-0 place-items-center rounded-md bg-card text-primary ring-1 ring-border/70"
                                        aria-hidden="true"
                                    >
                                        <Undo2 class="size-3.5" />
                                    </span>
                                </span>
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
                    <Link
                        href="/admin/proyectos"
                        class="group inline-flex h-8 items-center gap-1.5 rounded-md px-2 text-sm font-semibold tabular-nums transition-colors hover:bg-primary-soft hover:text-primary"
                    >
                        <AnimatedNumber :value="props.projects.active" />
                        <span class="font-normal text-muted-foreground"
                            >en curso</span
                        >
                        <ChevronRight
                            class="size-4 transition-transform duration-200 group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </Link>
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
                    <div
                        class="admin-stagger grid grid-cols-2 gap-3 lg:grid-cols-4"
                    >
                        <StatTile
                            label="Pendientes de anticipo"
                            :icon="Wallet"
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
                            :icon="Factory"
                            :value="props.projects.inFabrication"
                            href="/admin/fabricacion"
                        />
                        <StatTile
                            label="En instalación"
                            :icon="Truck"
                            :value="props.projects.inInstallation"
                            href="/admin/instalaciones"
                        />
                        <StatTile
                            label="Pendientes de calidad"
                            :icon="ClipboardCheck"
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
                                class="group inline-flex items-center gap-1 rounded-md px-1.5 text-sm font-semibold tabular-nums transition-colors hover:bg-primary-soft hover:text-primary"
                            >
                                <AnimatedNumber
                                    :value="props.operations.incidents.open"
                                />
                                <ChevronRight
                                    class="size-4 transition-transform duration-200 group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </div>
                        <ul
                            v-if="props.operations.incidents.open > 0"
                            class="admin-stagger grid grid-cols-2 gap-2 sm:grid-cols-4"
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
                                    ><AnimatedNumber :value="row.count"
                                /></span>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="flex items-center gap-2 rounded-lg bg-success-soft/60 px-3 py-2.5 text-sm text-success-ink"
                        >
                            <ShieldCheck class="size-4" aria-hidden="true" />
                            Sin incidencias abiertas.
                        </p>
                    </div>
                    <StatTile
                        v-if="props.operations.activeWarranties !== null"
                        label="Garantías activas"
                        :icon="ShieldCheck"
                        :value="props.operations.activeWarranties"
                        hint="Vigentes a la fecha"
                        href="/admin/garantias"
                    />
                </div>
            </SectionCard>
        </template>
    </PageContainer>
</template>
