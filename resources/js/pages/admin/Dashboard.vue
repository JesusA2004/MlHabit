<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    CalendarClock,
    Clapperboard,
    Eye,
    FilePen,
    FileText,
    HardDrive,
    ImagePlus,
    PenTool,
    Send,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import BarChart from '@/components/admin/BarChart.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import StatCard from '@/components/admin/StatCard.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { calendario, estadisticas } from '@/routes/admin';
import actividad from '@/routes/admin/actividad';
import multimedia from '@/routes/admin/multimedia';
import publicaciones from '@/routes/admin/publicaciones';
import studio from '@/routes/admin/studio';
import type { BreadcrumbItem } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Panel', href: '/admin' }] as BreadcrumbItem[] },
});

const props = defineProps<{
    stats: {
        totalPosts: number;
        published: number;
        drafts: number;
        scheduled: number;
        archived: number;
        featured: number;
        mediaCount: number;
        mediaBytes: number;
        publishedThisMonth: number;
    };
    charts: {
        postsByMonth: { month: string; total: number; published: number }[];
        postsByCategory: { category: string; color: string; total: number }[];
    };
    analytics: {
        summary: { rangeViews: number; uniqueVisitors: number; viewsToday: number; deltaPct: number | null };
        topPosts: { title: string; slug: string; category: string | null; color: string | null; views: number; uniques: number }[];
    };
    recentPosts: {
        id: number;
        title: string;
        status: string;
        category: string | null;
        categoryColor: string | null;
        author: string | null;
        coverUrl: string | null;
        updatedAt: string | null;
    }[];
    recentMedia: { id: number; filename: string; type: string; status: string; url: string | null; posterUrl: string | null }[];
    activity: { id: number; label: string; detail: string | null; user: string | null; result: string; createdAt: string | null }[];
    storage: { provider: string; bytes: number };
    upcoming: { id: number; title: string; status: string; date: string | null; category: string | null; color: string | null }[];
    studio: { id: number; title: string; path: string; hasChanges: boolean; publishedAt: string | null }[];
    today: string;
}>();

const greeting = computed(() => {
    const h = new Date().getHours();

    return h < 12 ? 'Buenos días' : h < 19 ? 'Buenas tardes' : 'Buenas noches';
});
const todayLabel = new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());

const MONTH = new Intl.DateTimeFormat('es-MX', { month: 'short' });
const MONTH_LONG = new Intl.DateTimeFormat('es-MX', { month: 'long', year: 'numeric' });
const monthly = computed(() =>
    props.charts.postsByMonth.map((m) => {
        const d = new Date(`${m.month}-01T12:00:00`);

        return { label: MONTH.format(d).replace('.', ''), sublabel: `Publicadas en ${MONTH_LONG.format(d)}`, value: m.published };
    }),
);
const categoryMax = computed(() => Math.max(1, ...props.charts.postsByCategory.map((c) => c.total)));
const viewedMax = computed(() => Math.max(1, ...props.analytics.topPosts.map((p) => p.views)));

function bytes(n: number): string {
    if (n <= 0) return '0 MB';
    const mb = n / 1024 / 1024;

    return mb >= 1024 ? `${(mb / 1024).toFixed(1)} GB` : `${mb.toFixed(1)} MB`;
}

function relative(iso: string | null): string {
    if (!iso) return '';
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    const rtf = new Intl.RelativeTimeFormat('es-MX', { numeric: 'auto' });
    if (diff < 3600) return rtf.format(-Math.max(1, Math.round(diff / 60)), 'minute');
    if (diff < 86400) return rtf.format(-Math.round(diff / 3600), 'hour');

    return rtf.format(-Math.round(diff / 86400), 'day');
}

const agenda = computed(() => {
    const groups = new Map<string, typeof props.upcoming>();
    for (const e of props.upcoming) {
        if (!e.date) continue;
        const key = e.date.slice(0, 10);
        groups.set(key, [...(groups.get(key) ?? []), e]);
    }

    return [...groups.entries()].map(([day, events]) => {
        const d = new Date(`${day}T12:00:00`);

        return {
            day,
            weekday: new Intl.DateTimeFormat('es-MX', { weekday: 'short' }).format(d).replace('.', ''),
            num: d.getDate(),
            isToday: day === props.today,
            events,
        };
    });
});
</script>

<template>
    <Head title="Panel" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader :eyebrow="todayLabel" :title="`${greeting}.`" description="Esto es lo que está pasando en MR. INSIGHT.">
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="multimedia.index()"><ImagePlus class="size-4" /> Subir multimedia</Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="studio.index()"><PenTool class="size-4" /> Diseñar en Studio</Link>
                </Button>
                <Button as-child>
                    <Link :href="publicaciones.create()"><FilePen class="size-4" /> Escribir un tip</Link>
                </Button>
            </template>
        </AdminPageHeader>

        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard label="Publicadas" :value="stats.published" :hint="`${stats.publishedThisMonth} este mes · ${stats.featured} destacadas`" :icon="Send" tone="success" :href="publicaciones.index({ query: { status: 'published' } }).url" />
            <StatCard label="Borradores" :value="stats.drafts" hint="Pendientes de terminar" :icon="FileText" :href="publicaciones.index({ query: { status: 'draft' } }).url" />
            <StatCard label="Programadas" :value="stats.scheduled" hint="Saldrán solas a su hora" :icon="CalendarClock" tone="warning" :href="calendario().url" />
            <StatCard label="Multimedia" :value="stats.mediaCount" :hint="`${bytes(stats.mediaBytes)} en ${storage.provider}`" :icon="HardDrive" tone="brand" :href="multimedia.index().url" />
        </div>

        <!-- Audiencia: visitas y lo más visto (últimos 30 días) -->
        <section class="grid gap-4 rounded-xl border bg-card p-5 lg:grid-cols-12">
            <div class="flex flex-col gap-4 lg:col-span-4">
                <header class="flex items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 text-sm font-semibold"><Eye class="size-4 text-brand" /> Audiencia</h2>
                    <Link :href="estadisticas()" class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">
                        Ver todo <ArrowRight class="size-3.5" />
                    </Link>
                </header>
                <div>
                    <div class="flex items-end gap-2">
                        <span class="font-display text-4xl leading-none font-semibold tabular-nums">{{ analytics.summary.rangeViews.toLocaleString('es-MX') }}</span>
                        <span
                            v-if="analytics.summary.deltaPct !== null"
                            class="mb-1 inline-flex items-center gap-0.5 text-xs font-medium"
                            :class="analytics.summary.deltaPct >= 0 ? 'text-success' : 'text-destructive'"
                        >
                            <ArrowUpRight v-if="analytics.summary.deltaPct >= 0" class="size-3.5" />
                            {{ analytics.summary.deltaPct >= 0 ? '+' : '' }}{{ analytics.summary.deltaPct }}%
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">Visitas en los últimos 30 días</p>
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-muted/50 p-3">
                        <dt class="text-xs text-muted-foreground">Visitantes únicos</dt>
                        <dd class="font-display text-xl font-semibold tabular-nums">{{ analytics.summary.uniqueVisitors.toLocaleString('es-MX') }}</dd>
                    </div>
                    <div class="rounded-lg bg-muted/50 p-3">
                        <dt class="text-xs text-muted-foreground">Visitas hoy</dt>
                        <dd class="font-display text-xl font-semibold tabular-nums">{{ analytics.summary.viewsToday.toLocaleString('es-MX') }}</dd>
                    </div>
                </dl>
            </div>
            <div class="lg:col-span-8 lg:border-l lg:pl-6">
                <h3 class="mb-3 text-xs font-semibold text-muted-foreground uppercase tracking-wide">Lo más visto</h3>
                <ol v-if="analytics.topPosts.length" class="flex flex-col gap-2.5">
                    <li v-for="(p, i) in analytics.topPosts" :key="p.slug" class="flex items-center gap-3">
                        <span class="w-4 shrink-0 text-center text-xs font-semibold text-muted-foreground tabular-nums">{{ i + 1 }}</span>
                        <a :href="`/tips/${p.slug}`" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate text-sm font-medium hover:text-brand">{{ p.title }}</a>
                        <span class="h-1.5 w-28 shrink-0 overflow-hidden rounded-full bg-muted">
                            <span class="block h-full rounded-full bg-brand/70" :style="{ width: `${(p.views / viewedMax) * 100}%` }" />
                        </span>
                        <span class="w-12 shrink-0 text-right text-sm font-semibold tabular-nums">{{ p.views.toLocaleString('es-MX') }}</span>
                    </li>
                </ol>
                <div v-else class="flex h-full min-h-28 flex-col items-center justify-center gap-1.5 text-center">
                    <Eye class="size-5 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">Cuando el sitio reciba visitas, aquí verás los tips más leídos.</p>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-12">
            <section class="flex flex-col gap-4 rounded-xl border bg-card p-5 xl:col-span-8">
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold">Publicaciones por mes</h2>
                        <p class="text-xs text-muted-foreground">Tips publicados en los últimos seis meses</p>
                    </div>
                    <span class="font-display text-2xl font-semibold tabular-nums">{{ monthly.reduce((a, m) => a + m.value, 0) }}</span>
                </header>
                <div class="h-56">
                    <BarChart title="Publicaciones por mes" :data="monthly" unit="tips" />
                </div>
            </section>

            <section class="flex flex-col gap-4 rounded-xl border bg-card p-5 xl:col-span-4">
                <header>
                    <h2 class="text-sm font-semibold">Por categoría</h2>
                    <p class="text-xs text-muted-foreground">Todas las publicaciones</p>
                </header>
                <ul v-if="charts.postsByCategory.length" class="flex flex-col gap-3.5">
                    <li v-for="row in charts.postsByCategory" :key="row.category" class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2"><span class="size-2.5 rounded-full" :style="{ background: row.color }" />{{ row.category }}</span>
                            <span class="font-medium tabular-nums">{{ row.total }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full" :style="{ width: `${(row.total / categoryMax) * 100}%`, background: row.color }" />
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">Aún no hay publicaciones.</p>
            </section>

            <section class="flex flex-col rounded-xl border bg-card xl:col-span-8">
                <header class="flex items-center justify-between border-b px-5 py-4">
                    <h2 class="text-sm font-semibold">Actividad editorial reciente</h2>
                    <Link :href="publicaciones.index()" class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">Ver todas <ArrowRight class="size-3.5" /></Link>
                </header>
                <EmptyState v-if="!recentPosts.length" class="m-5" :icon="FilePen" title="Aún no hay publicaciones" description="Escribe tu primer tip; podrás programarlo o publicarlo cuando quieras.">
                    <Button as-child size="sm"><Link :href="publicaciones.create()">Escribir el primero</Link></Button>
                </EmptyState>
                <ul v-else class="divide-y">
                    <li v-for="post in recentPosts" :key="post.id">
                        <Link :href="publicaciones.edit(post.id)" class="flex items-center gap-4 px-5 py-3 transition-colors hover:bg-muted/50">
                            <span class="size-12 shrink-0 overflow-hidden rounded-lg bg-muted">
                                <img v-if="post.coverUrl" :src="post.coverUrl" alt="" class="size-full object-cover" loading="lazy" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ post.title }}</span>
                                <span class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span v-if="post.category" class="inline-flex items-center gap-1"><span class="size-1.5 rounded-full" :style="{ background: post.categoryColor ?? undefined }" />{{ post.category }}</span>
                                    <span>· {{ relative(post.updatedAt) }}</span>
                                </span>
                            </span>
                            <StatusBadge :status="post.status" />
                        </Link>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col rounded-xl border bg-card xl:col-span-4">
                <header class="flex items-center justify-between border-b px-5 py-4">
                    <h2 class="text-sm font-semibold">Próximos 14 días</h2>
                    <Link :href="calendario()" class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">Calendario <ArrowRight class="size-3.5" /></Link>
                </header>
                <div v-if="!agenda.length" class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center">
                    <CalendarClock class="size-6 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">Nada programado. Programa un tip desde el editor para mantener el ritmo.</p>
                </div>
                <ol v-else class="flex flex-col gap-1 p-3">
                    <li v-for="day in agenda" :key="day.day" class="flex gap-3 rounded-lg p-2">
                        <span class="flex w-11 shrink-0 flex-col items-center rounded-lg py-1" :class="day.isToday ? 'bg-brand text-brand-foreground' : 'bg-muted'">
                            <span class="text-[10px] font-semibold uppercase">{{ day.weekday }}</span>
                            <span class="font-display text-lg leading-none font-semibold">{{ day.num }}</span>
                        </span>
                        <ul class="flex min-w-0 flex-1 flex-col gap-1">
                            <li v-for="e in day.events" :key="e.id">
                                <Link :href="publicaciones.edit(e.id)" class="block truncate rounded-md px-2 py-1 text-sm hover:bg-muted">
                                    <span class="mr-1.5 inline-block size-1.5 rounded-full align-middle" :style="{ background: e.color ?? undefined }" />{{ e.title }}
                                    <span class="block pl-3 text-[11px] text-muted-foreground">{{ e.status === 'scheduled' ? 'Programada' : 'Publicada' }} · {{ new Date(e.date!).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) }}</span>
                                </Link>
                            </li>
                        </ul>
                    </li>
                </ol>
            </section>

            <section class="flex flex-col rounded-xl border bg-card xl:col-span-4">
                <header class="flex items-center justify-between border-b px-5 py-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold"><Sparkles class="size-4 text-brand" /> Páginas en Studio</h2>
                    <Link :href="studio.index()" class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">Abrir <ArrowRight class="size-3.5" /></Link>
                </header>
                <ul class="divide-y">
                    <li v-for="p in props.studio" :key="p.id">
                        <Link :href="studio.edit(p.id)" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-muted/50">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium">{{ p.title }}</span>
                                <span class="text-xs text-muted-foreground">{{ p.path }}</span>
                            </span>
                            <StatusBadge :status="p.hasChanges ? 'changes' : p.publishedAt ? 'published' : 'unpublished'" />
                        </Link>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col rounded-xl border bg-card xl:col-span-4">
                <header class="flex items-center justify-between border-b px-5 py-4">
                    <h2 class="text-sm font-semibold">Archivos recientes</h2>
                    <Link :href="multimedia.index()" class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">Biblioteca <ArrowRight class="size-3.5" /></Link>
                </header>
                <div v-if="recentMedia.length" class="grid grid-cols-4 gap-2 p-4">
                    <Link
                        v-for="m in recentMedia"
                        :key="m.id"
                        :href="multimedia.index()"
                        class="relative aspect-square overflow-hidden rounded-lg bg-muted"
                        :title="m.filename"
                    >
                        <img v-if="m.type === 'image' && m.url" :src="m.url" :alt="m.filename" loading="lazy" class="size-full object-cover" />
                        <img v-else-if="m.posterUrl" :src="m.posterUrl" alt="" loading="lazy" class="size-full object-cover" />
                        <span v-else class="grid size-full place-items-center text-muted-foreground"><Clapperboard class="size-5" /></span>
                        <span v-if="m.status !== 'ready'" class="absolute inset-x-0 bottom-0 bg-black/70 py-0.5 text-center text-[9px] font-semibold text-white">Procesando</span>
                    </Link>
                </div>
                <p v-else class="p-5 text-sm text-muted-foreground">Sube fotos y videos para usarlos en tips y en Studio.</p>
                <div class="mt-auto flex items-center gap-3 border-t px-5 py-3 text-xs text-muted-foreground">
                    <HardDrive class="size-4" /> {{ storage.provider }} · {{ bytes(storage.bytes) }} usados
                </div>
            </section>

            <section class="flex flex-col rounded-xl border bg-card xl:col-span-4">
                <header class="flex items-center justify-between border-b px-5 py-4">
                    <h2 class="text-sm font-semibold">Bitácora</h2>
                    <Link :href="actividad.index()" class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">Toda la actividad <ArrowRight class="size-3.5" /></Link>
                </header>
                <ol v-if="activity.length" class="flex flex-col gap-3 p-5">
                    <li v-for="a in activity" :key="a.id" class="flex gap-3">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="a.result === 'success' ? 'bg-brand' : 'bg-destructive'" />
                        <span class="min-w-0 text-sm">
                            <span class="font-medium">{{ a.label }}</span>
                            <span v-if="a.detail" class="text-muted-foreground"> · {{ a.detail }}</span>
                            <span class="block text-xs text-muted-foreground">{{ a.user ?? 'Sistema' }} · {{ relative(a.createdAt) }}</span>
                        </span>
                    </li>
                </ol>
                <p v-else class="p-5 text-sm text-muted-foreground">Sin actividad todavía.</p>
            </section>
        </div>
    </div>
</template>
