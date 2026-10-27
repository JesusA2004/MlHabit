<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    ChartColumn,
    Eye,
    Globe,
    MousePointerClick,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import StatCard from '@/components/admin/StatCard.vue';
import TrendChart from '@/components/admin/TrendChart.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { estadisticas } from '@/routes/admin';

interface Summary {
    days: number;
    totalViews: number;
    rangeViews: number;
    uniqueVisitors: number;
    viewsToday: number;
    avgPerDay: number;
    deltaPct: number | null;
    requestPageVisitors: number;
}

const props = defineProps<{
    range: number;
    ranges: number[];
    summary: Summary;
    series: { date: string; label: string; total: number; unique: number }[];
    areas: { area: string; views: number; uniques: number }[];
    topPages: { path: string; label: string; views: number; uniques: number }[];
    referrers: { host: string; views: number }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Estadísticas', href: '/admin/estadisticas' },
        ],
    },
});

function setRange(value: unknown) {
    const days = Number(value);
    if (!days || days === props.range) return;
    router.get(
        estadisticas({ query: { dias: days } }).url,
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const nf = new Intl.NumberFormat('es-MX');
const fmt = (n: number) => nf.format(n);
const hasData = computed(() => props.summary.totalViews > 0);
const maxArea = computed(() => Math.max(1, ...props.areas.map((a) => a.views)));
const maxPage = computed(() =>
    Math.max(1, ...props.topPages.map((p) => p.views)),
);
const deltaLabel = computed(() => {
    const d = props.summary.deltaPct;
    if (d === null) return 'Sin periodo anterior para comparar';

    return `${d >= 0 ? '+' : ''}${d}% frente a los ${props.summary.days} días previos`;
});
</script>

<template>
    <Head title="Estadísticas" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Estadísticas del sitio"
            description="Visitas al sitio público de ML Habitat. Datos anónimos y agregados: sin cookies ni seguimiento personal. No se cuentan las visitas del personal con sesión iniciada."
        >
            <template #actions>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    :model-value="String(props.range)"
                    aria-label="Periodo"
                    @update:model-value="setRange"
                >
                    <ToggleGroupItem
                        v-for="r in props.ranges"
                        :key="r"
                        :value="String(r)"
                        class="px-3"
                        >{{ r }} días</ToggleGroupItem
                    >
                </ToggleGroup>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!hasData"
            :icon="ChartColumn"
            title="Aún no hay visitas registradas"
            description="En cuanto el sitio reciba visitas verás aquí cuántas son, cómo evolucionan y qué secciones interesan más."
        />

        <template v-else>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard
                    label="Visitas"
                    :value="fmt(props.summary.rangeViews)"
                    :hint="deltaLabel"
                    :icon="Eye"
                    tone="brand"
                />
                <StatCard
                    label="Visitantes únicos"
                    :value="fmt(props.summary.uniqueVisitors)"
                    :hint="`En los últimos ${props.summary.days} días`"
                    :icon="Users"
                />
                <StatCard
                    label="Visitas hoy"
                    :value="fmt(props.summary.viewsToday)"
                    :hint="`Promedio de ${fmt(props.summary.avgPerDay)} al día`"
                    :icon="ChartColumn"
                    tone="success"
                />
                <StatCard
                    label="Llegaron a “Solicitar”"
                    :value="fmt(props.summary.requestPageVisitors)"
                    hint="Visitantes únicos en la página de solicitud"
                    :icon="MousePointerClick"
                    tone="info"
                />
            </div>

            <SectionCard
                title="Evolución de visitas"
                description="Visitas y visitantes únicos por día"
            >
                <template v-if="props.summary.deltaPct !== null" #actions>
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            props.summary.deltaPct >= 0
                                ? 'bg-success-soft text-success-ink'
                                : 'bg-danger-soft text-danger-ink'
                        "
                    >
                        <component
                            :is="
                                props.summary.deltaPct >= 0
                                    ? ArrowUpRight
                                    : ArrowDownRight
                            "
                            class="size-3.5"
                        />
                        {{ props.summary.deltaPct >= 0 ? '+' : ''
                        }}{{ props.summary.deltaPct }}%
                    </span>
                </template>
                <TrendChart title="Evolución de visitas" :data="props.series" />
            </SectionCard>

            <div class="grid gap-4 xl:grid-cols-2">
                <SectionCard title="Visitas por sección del sitio">
                    <ul class="grid gap-3">
                        <li
                            v-for="a in props.areas"
                            :key="a.area"
                            class="grid gap-1"
                        >
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span>{{ a.area }}</span>
                                <span class="font-medium tabular-nums">{{
                                    fmt(a.views)
                                }}</span>
                            </div>
                            <span
                                class="h-1.5 overflow-hidden rounded-full bg-muted"
                            >
                                <span
                                    class="block h-full rounded-full bg-primary/70"
                                    :style="{
                                        width: `${(a.views / maxArea) * 100}%`,
                                    }"
                                />
                            </span>
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard title="Páginas más vistas" flush>
                    <ol
                        v-if="props.topPages.length"
                        class="divide-y divide-border"
                    >
                        <li
                            v-for="(p, i) in props.topPages"
                            :key="p.path"
                            class="flex items-center gap-3 px-5 py-3"
                        >
                            <span
                                class="w-5 shrink-0 text-center font-display text-sm font-semibold text-muted-foreground tabular-nums"
                                >{{ i + 1 }}</span
                            >
                            <span class="min-w-0 flex-1">
                                <a
                                    :href="p.path"
                                    target="_blank"
                                    rel="noopener"
                                    class="block truncate text-sm font-medium hover:text-primary"
                                    >{{ p.label }}</a
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ p.path }} ·
                                    {{ fmt(p.uniques) }} únicos</span
                                >
                            </span>
                            <span
                                class="h-1 w-16 shrink-0 overflow-hidden rounded-full bg-muted"
                            >
                                <span
                                    class="block h-full rounded-full bg-primary/70"
                                    :style="{
                                        width: `${(p.views / maxPage) * 100}%`,
                                    }"
                                />
                            </span>
                            <span
                                class="shrink-0 font-display text-sm font-semibold tabular-nums"
                                >{{ fmt(p.views) }}</span
                            >
                        </li>
                    </ol>
                    <p v-else class="p-5 text-sm text-muted-foreground">
                        Sin datos en este periodo.
                    </p>
                </SectionCard>
            </div>

            <SectionCard
                v-if="props.referrers.length"
                title="De dónde llegan"
                description="Sitios externos que enviaron visitas"
                :icon="Globe"
            >
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="r in props.referrers"
                        :key="r.host"
                        class="inline-flex items-center gap-2 rounded-full border border-border bg-muted/40 px-3 py-1 text-sm"
                    >
                        {{ r.host }}
                        <span
                            class="font-medium text-muted-foreground tabular-nums"
                            >{{ fmt(r.views) }}</span
                        >
                    </li>
                </ul>
            </SectionCard>
        </template>
    </PageContainer>
</template>
