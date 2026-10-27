<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowUpRight, ChartColumn, Eye, FileText, Globe, Users } from '@lucide/vue';
import { computed } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import StatCard from '@/components/admin/StatCard.vue';
import TrendChart from '@/components/admin/TrendChart.vue';
import { estadisticas } from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    days: number;
    totalViews: number;
    rangeViews: number;
    uniqueVisitors: number;
    viewsToday: number;
    avgPerDay: number;
    deltaPct: number | null;
}

const props = defineProps<{
    range: number;
    ranges: number[];
    summary: Summary;
    series: { date: string; label: string; total: number; unique: number }[];
    topPosts: { title: string; slug: string; category: string | null; color: string | null; views: number; uniques: number }[];
    topPages: { path: string; label: string; views: number; uniques: number }[];
    referrers: { host: string; views: number }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Estadísticas', href: estadisticas() },
        ] as BreadcrumbItem[],
    },
});

const RANGE_LABEL: Record<number, string> = { 7: '7 días', 30: '30 días', 90: '90 días' };

function setRange(days: number) {
    if (days === props.range) return;
    router.get(estadisticas({ query: { dias: days } }).url, {}, { preserveState: true, preserveScroll: true, replace: true });
}

const hasData = computed(() => props.summary.totalViews > 0);
const nf = new Intl.NumberFormat('es-MX');
function fmt(n: number): string {
    return nf.format(n);
}

const maxPost = computed(() => Math.max(1, ...props.topPosts.map((p) => p.views)));
const maxPage = computed(() => Math.max(1, ...props.topPages.map((p) => p.views)));
const deltaLabel = computed(() => {
    const d = props.summary.deltaPct;
    if (d === null) return 'Sin periodo anterior comparable';

    return `${d >= 0 ? '+' : ''}${d}% frente a los ${props.summary.days} días previos`;
});
</script>

<template>
    <Head title="Estadísticas" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Audiencia"
            title="Estadísticas del sitio"
            description="Cuántas visitas recibe MR. INSIGHT y qué contenido se lee más. Datos anónimos y agregados: sin cookies ni seguimiento personal."
        >
            <template #actions>
                <div class="inline-flex rounded-lg border bg-card p-0.5" role="group" aria-label="Periodo">
                    <button
                        v-for="r in ranges"
                        :key="r"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="r === range ? 'bg-brand text-brand-foreground' : 'text-muted-foreground hover:text-foreground'"
                        :aria-pressed="r === range"
                        @click="setRange(r)"
                    >
                        {{ RANGE_LABEL[r] ?? `${r} días` }}
                    </button>
                </div>
            </template>
        </AdminPageHeader>

        <EmptyState
            v-if="!hasData"
            :icon="ChartColumn"
            title="Aún no hay visitas registradas"
            description="En cuanto el sitio público reciba visitas, aquí verás cuántas son, cómo evolucionan y qué contenido se lee más. El registro es anónimo y no usa cookies."
        />

        <template v-else>
            <!-- KPIs -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard label="Visitas" :value="fmt(summary.rangeViews)" :hint="deltaLabel" :icon="Eye" tone="brand" />
                <StatCard label="Visitantes únicos" :value="fmt(summary.uniqueVisitors)" :hint="`En los últimos ${summary.days} días`" :icon="Users" />
                <StatCard label="Visitas hoy" :value="fmt(summary.viewsToday)" :hint="`Promedio ${fmt(summary.avgPerDay)}/día`" :icon="ChartColumn" tone="success" />
                <StatCard label="Total histórico" :value="fmt(summary.totalViews)" hint="Desde el inicio del registro" :icon="Globe" />
            </div>

            <!-- Serie temporal -->
            <section class="flex flex-col gap-4 rounded-xl border bg-card p-5">
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold">Evolución de visitas</h2>
                        <p class="text-xs text-muted-foreground">Visitas y visitantes únicos por día</p>
                    </div>
                    <span
                        v-if="summary.deltaPct !== null"
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="summary.deltaPct >= 0 ? 'bg-success/12 text-success' : 'bg-destructive/10 text-destructive'"
                    >
                        <component :is="summary.deltaPct >= 0 ? ArrowUpRight : ArrowDownRight" class="size-3.5" />
                        {{ summary.deltaPct >= 0 ? '+' : '' }}{{ summary.deltaPct }}%
                    </span>
                </header>
                <TrendChart title="Evolución de visitas" :data="series" />
            </section>

            <div class="grid gap-4 xl:grid-cols-2">
                <!-- Publicaciones más vistas -->
                <section class="flex flex-col rounded-xl border bg-card">
                    <header class="flex items-center gap-2 border-b px-5 py-4">
                        <FileText class="size-4 text-brand" />
                        <h2 class="text-sm font-semibold">Publicaciones más vistas</h2>
                    </header>
                    <ol v-if="topPosts.length" class="flex flex-col divide-y">
                        <li v-for="(p, i) in topPosts" :key="p.slug" class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <span class="w-5 shrink-0 text-center font-display text-sm font-semibold text-muted-foreground tabular-nums">{{ i + 1 }}</span>
                                <a :href="`/tips/${p.slug}`" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate text-sm font-medium hover:text-brand">
                                    {{ p.title }}
                                </a>
                                <span class="shrink-0 font-display text-sm font-semibold tabular-nums">{{ fmt(p.views) }}</span>
                            </div>
                            <div class="mt-1.5 ml-8 flex items-center gap-2">
                                <span v-if="p.category" class="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                                    <span class="size-1.5 rounded-full" :style="{ background: p.color ?? undefined }" />{{ p.category }}
                                </span>
                                <span class="text-[11px] text-muted-foreground">· {{ fmt(p.uniques) }} únicos</span>
                                <span class="ml-auto h-1 w-24 overflow-hidden rounded-full bg-muted">
                                    <span class="block h-full rounded-full bg-brand/70" :style="{ width: `${(p.views / maxPost) * 100}%` }" />
                                </span>
                            </div>
                        </li>
                    </ol>
                    <p v-else class="p-5 text-sm text-muted-foreground">Ninguna publicación ha recibido visitas en este periodo.</p>
                </section>

                <!-- Páginas más vistas -->
                <section class="flex flex-col rounded-xl border bg-card">
                    <header class="flex items-center gap-2 border-b px-5 py-4">
                        <Globe class="size-4 text-brand" />
                        <h2 class="text-sm font-semibold">Páginas más vistas</h2>
                    </header>
                    <ol v-if="topPages.length" class="flex flex-col divide-y">
                        <li v-for="(p, i) in topPages" :key="p.path" class="flex items-center gap-3 px-5 py-3">
                            <span class="w-5 shrink-0 text-center font-display text-sm font-semibold text-muted-foreground tabular-nums">{{ i + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                <a :href="p.path" target="_blank" rel="noopener" class="block truncate text-sm font-medium hover:text-brand">{{ p.label }}</a>
                                <span class="block truncate text-[11px] text-muted-foreground">{{ p.path }}</span>
                            </span>
                            <span class="h-1 w-20 shrink-0 overflow-hidden rounded-full bg-muted">
                                <span class="block h-full rounded-full bg-brand/70" :style="{ width: `${(p.views / maxPage) * 100}%` }" />
                            </span>
                            <span class="shrink-0 font-display text-sm font-semibold tabular-nums">{{ fmt(p.views) }}</span>
                        </li>
                    </ol>
                    <p v-else class="p-5 text-sm text-muted-foreground">Sin datos de páginas en este periodo.</p>
                </section>
            </div>

            <!-- Procedencia -->
            <section v-if="referrers.length" class="flex flex-col gap-3 rounded-xl border bg-card p-5">
                <header>
                    <h2 class="text-sm font-semibold">De dónde llegan</h2>
                    <p class="text-xs text-muted-foreground">Sitios externos que enviaron visitas</p>
                </header>
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="r in referrers"
                        :key="r.host"
                        class="inline-flex items-center gap-2 rounded-full border bg-muted/40 px-3 py-1 text-sm"
                    >
                        {{ r.host }}
                        <span class="font-medium tabular-nums text-muted-foreground">{{ fmt(r.views) }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
