<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    BookOpen,
    ChevronLeft,
    ChevronRight,
    FileText,
    HardDrive,
    Home,
    ImageIcon,
    Loader2,
    Palette,
    PenTool,
    Search,
    Share2,
    Tags,
    X,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import actividad from '@/routes/admin/actividad';
import type { BreadcrumbItem } from '@/types';

interface LogRow {
    id: number;
    userName: string;
    actionKey: string;
    actionLabel: string;
    subjectLabel: string | null;
    subjectId: number | null;
    summary: string[];
    result: string;
    createdAt: string | null;
}

const props = defineProps<{
    logs: { data: LogRow[]; currentPage: number; lastPage: number; total: number };
    filters: { usuario?: string; accion?: string; desde?: string; hasta?: string; q?: string };
    actions: { value: string; label: string }[];
    users: { value: number; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Actividad', href: actividad.index() },
        ] as BreadcrumbItem[],
    },
});

const TOUCH = 'pointer-coarse:h-11';
const TOUCH_SELECT = 'pointer-coarse:data-[size=default]:h-11';
// Reka Select no admite un valor vacío: «todos» se representa con un centinela.
const ALL = '__todos__';

// ---------------------------------------------------------------- Filtros

const search = ref(props.filters.q ?? '');
const action = ref(props.filters.accion || ALL);
const user = ref(props.filters.usuario || ALL);
const desde = ref(props.filters.desde ?? '');
const hasta = ref(props.filters.hasta ?? '');
const loading = ref(false);

const hasActiveFilters = computed(() => Object.values(props.filters).some((v) => !!v));

function query(page?: number) {
    return {
        q: search.value || undefined,
        accion: action.value === ALL ? undefined : action.value,
        usuario: user.value === ALL ? undefined : user.value,
        desde: desde.value || undefined,
        hasta: hasta.value || undefined,
        page: page && page > 1 ? page : undefined,
    };
}

let lastQueried = search.value;
function visit(page?: number) {
    clearTimeout(searchTimer);
    lastQueried = search.value;
    router.get(actividad.index().url, query(page), {
        preserveState: true,
        preserveScroll: page === undefined,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

function applyFilters() {
    visit();
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    if (value === lastQueried) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});
onBeforeUnmount(() => clearTimeout(searchTimer));

function clearFilters() {
    search.value = '';
    action.value = ALL;
    user.value = ALL;
    desde.value = '';
    hasta.value = '';
    applyFilters();
}

function goToPage(page: number) {
    visit(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ---------------------------------------------------------------- Formato

const ACTION_ICONS: Record<string, Component> = {
    studio: PenTool,
    publicacion: FileText,
    categoria: Tags,
    multimedia: ImageIcon,
    pagina: BookOpen,
    pagina_entrada: BookOpen,
    red_social: Share2,
    inicio: Home,
    apariencia: Palette,
    almacenamiento: HardDrive,
};

function iconFor(log: LogRow): Component {
    return ACTION_ICONS[log.actionKey.split('.')[0]] ?? Activity;
}

const RESULTS: Record<string, { label: string; cls: string }> = {
    success: { label: 'Correcto', cls: 'border-transparent bg-success/12 text-success' },
    ok: { label: 'Correcto', cls: 'border-transparent bg-success/12 text-success' },
    error: { label: 'Error', cls: 'border-transparent bg-destructive/10 text-destructive' },
    failed: { label: 'Error', cls: 'border-transparent bg-destructive/10 text-destructive' },
    failure: { label: 'Error', cls: 'border-transparent bg-destructive/10 text-destructive' },
    pending: { label: 'Pendiente', cls: 'border-transparent bg-warning/15 text-warning' },
};

function resultOf(log: LogRow) {
    return RESULTS[log.result] ?? { label: log.result, cls: 'border-transparent bg-muted text-muted-foreground' };
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('');
}

function time(iso: string | null): string {
    return iso ? new Intl.DateTimeFormat('es-MX', { hour: '2-digit', minute: '2-digit' }).format(new Date(iso)) : '';
}

function fullDate(iso: string | null): string {
    return iso ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'full', timeStyle: 'short' }).format(new Date(iso)) : '';
}

function dayKey(iso: string | null): string {
    return iso ? new Date(iso).toLocaleDateString('en-CA') : 'sin-fecha';
}

function dayLabel(key: string): string {
    if (key === 'sin-fecha') return 'Sin fecha';
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    if (key === today.toLocaleDateString('en-CA')) return 'Hoy';
    if (key === yesterday.toLocaleDateString('en-CA')) return 'Ayer';

    return new Intl.DateTimeFormat('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${key}T12:00:00`));
}

/** Registros agrupados por día, conservando el orden del servidor (más reciente primero). */
const groups = computed(() => {
    const out: { key: string; label: string; logs: LogRow[] }[] = [];
    for (const log of props.logs.data) {
        const key = dayKey(log.createdAt);
        let group = out.find((g) => g.key === key);
        if (!group) {
            group = { key, label: dayLabel(key), logs: [] };
            out.push(group);
        }
        group.logs.push(log);
    }

    return out;
});
</script>

<template>
    <Head title="Actividad" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Sistema"
            title="Actividad"
            description="Quién cambió qué y cuándo. Útil para revisar publicaciones, cambios de Studio y movimientos de la biblioteca."
        >
            <template #meta>
                <p class="mt-3 text-xs text-muted-foreground">
                    <span class="font-semibold text-foreground tabular-nums">{{ logs.total }}</span>
                    {{ logs.total === 1 ? 'registro' : 'registros' }}{{ hasActiveFilters ? ' con los filtros actuales' : '' }}
                </p>
            </template>
        </AdminPageHeader>

        <!-- Filtros -->
        <form class="grid gap-3 rounded-xl border bg-card p-3 sm:grid-cols-2 lg:grid-cols-12 lg:items-end" @submit.prevent="applyFilters">
            <div class="grid gap-1.5 sm:col-span-2 lg:col-span-4">
                <Label for="act-q" class="text-xs text-muted-foreground">Buscar</Label>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input id="act-q" v-model="search" type="search" :class="cn('pl-8', TOUCH)" placeholder="Usuario o acción" />
                </div>
            </div>
            <div class="grid gap-1.5 lg:col-span-2">
                <Label for="act-user" class="text-xs text-muted-foreground">Usuario</Label>
                <Select v-model="user" @update:model-value="applyFilters">
                    <SelectTrigger id="act-user" :class="cn('w-full', TOUCH_SELECT)"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todos</SelectItem>
                        <SelectItem v-for="u in users" :key="u.value" :value="String(u.value)">{{ u.label }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5 lg:col-span-2">
                <Label for="act-action" class="text-xs text-muted-foreground">Acción</Label>
                <Select v-model="action" @update:model-value="applyFilters">
                    <SelectTrigger id="act-action" :class="cn('w-full', TOUCH_SELECT)"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todas</SelectItem>
                        <SelectItem v-for="a in actions" :key="a.value" :value="a.value">{{ a.label }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5 lg:col-span-2">
                <Label for="act-desde" class="text-xs text-muted-foreground">Desde</Label>
                <Input id="act-desde" v-model="desde" type="date" :max="hasta || undefined" :class="TOUCH" @change="applyFilters" />
            </div>
            <div class="grid gap-1.5 lg:col-span-2">
                <Label for="act-hasta" class="text-xs text-muted-foreground">Hasta</Label>
                <Input id="act-hasta" v-model="hasta" type="date" :min="desde || undefined" :class="TOUCH" @change="applyFilters" />
            </div>
            <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-12">
                <Button type="submit" size="sm" :class="TOUCH">
                    <Loader2 v-if="loading" class="size-4 animate-spin" />
                    <Search v-else class="size-4" />
                    Filtrar
                </Button>
                <Button v-if="hasActiveFilters" type="button" variant="ghost" size="sm" :class="TOUCH" @click="clearFilters">
                    <X class="size-4" /> Limpiar filtros
                </Button>
            </div>
        </form>

        <!-- Línea de tiempo -->
        <EmptyState
            v-if="logs.data.length === 0 && hasActiveFilters"
            :icon="Search"
            title="Sin actividad con estos filtros"
            description="Amplía el rango de fechas o quita algún filtro."
        >
            <Button variant="outline" :class="TOUCH" @click="clearFilters">Limpiar filtros</Button>
        </EmptyState>
        <EmptyState
            v-else-if="logs.data.length === 0"
            :icon="Activity"
            title="Aún no hay actividad"
            description="Cada vez que alguien publique, suba un archivo o cambie el sitio, quedará registrado aquí."
        />

        <div v-else class="flex flex-col gap-6 transition-opacity" :class="loading && 'opacity-60'">
            <section v-for="group in groups" :key="group.key" :aria-labelledby="`dia-${group.key}`">
                <h2 :id="`dia-${group.key}`" class="mb-2 text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                    {{ group.label }}
                </h2>
                <ol class="divide-y overflow-hidden rounded-xl border bg-card">
                    <li v-for="log in group.logs" :key="log.id" class="flex gap-3 p-4 md:items-start">
                        <span class="relative shrink-0">
                            <span class="grid size-9 place-items-center rounded-full bg-muted text-xs font-semibold" :title="log.userName" aria-hidden="true">
                                {{ initials(log.userName) || '·' }}
                            </span>
                            <span class="absolute -right-1 -bottom-1 grid size-5 place-items-center rounded-full border-2 border-card bg-brand/15 text-brand">
                                <component :is="iconFor(log)" class="size-3" />
                            </span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between md:gap-4">
                                <p class="text-sm">
                                    <span class="font-semibold">{{ log.userName }}</span>
                                    <span class="text-muted-foreground"> · </span>
                                    <span>{{ log.actionLabel }}</span>
                                    <span v-if="log.subjectLabel" class="text-muted-foreground"> · {{ log.subjectLabel }}</span>
                                </p>
                                <div class="flex shrink-0 items-center gap-2">
                                    <Badge variant="outline" :class="resultOf(log).cls">{{ resultOf(log).label }}</Badge>
                                    <time :datetime="log.createdAt ?? undefined" :title="fullDate(log.createdAt)" class="text-xs text-muted-foreground tabular-nums">
                                        {{ time(log.createdAt) }}
                                    </time>
                                </div>
                            </div>
                            <ul v-if="log.summary.length" class="mt-2 flex flex-wrap gap-1.5">
                                <li
                                    v-for="(line, i) in log.summary"
                                    :key="i"
                                    class="max-w-full truncate rounded-md bg-muted/70 px-2 py-0.5 text-xs text-muted-foreground"
                                    :title="line"
                                >
                                    {{ line }}
                                </li>
                            </ul>
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <nav
            v-if="logs.lastPage > 1"
            class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
            aria-label="Paginación"
        >
            <span>Página {{ logs.currentPage }} de {{ logs.lastPage }} · {{ logs.total }} registros</span>
            <div class="flex items-center gap-2">
                <Button size="sm" variant="outline" :class="TOUCH" :disabled="logs.currentPage <= 1" @click="goToPage(logs.currentPage - 1)">
                    <ChevronLeft class="size-4" /> Anterior
                </Button>
                <Button size="sm" variant="outline" :class="TOUCH" :disabled="logs.currentPage >= logs.lastPage" @click="goToPage(logs.currentPage + 1)">
                    Siguiente <ChevronRight class="size-4" />
                </Button>
            </div>
        </nav>
    </div>
</template>
