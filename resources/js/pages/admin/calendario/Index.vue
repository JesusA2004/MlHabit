<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, FilePen } from '@lucide/vue';
import { computed } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { Button } from '@/components/ui/button';
import { calendario } from '@/routes/admin';
import publicaciones from '@/routes/admin/publicaciones';
import type { BreadcrumbItem } from '@/types';

interface CalendarEvent {
    id: number;
    title: string;
    status: string;
    date: string | null;
    category: string | null;
    color: string | null;
}

const props = defineProps<{
    month: string;
    rangeStart: string;
    rangeEnd: string;
    events: CalendarEvent[];
    unscheduled: { id: number; title: string; category: string | null; color: string | null; updatedAt: string | null }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Calendario editorial', href: '/admin/calendario' },
        ] as BreadcrumbItem[],
    },
});

const WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const monthDate = computed(() => new Date(`${props.month}-01T12:00:00`));
const title = computed(() => new Intl.DateTimeFormat('es-MX', { month: 'long', year: 'numeric' }).format(monthDate.value));
const todayKey = new Date().toLocaleDateString('en-CA');

function key(d: Date): string {
    return d.toLocaleDateString('en-CA');
}

const days = computed(() => {
    const out: { key: string; num: number; inMonth: boolean; isToday: boolean; events: CalendarEvent[] }[] = [];
    const cursor = new Date(`${props.rangeStart}T12:00:00`);
    const end = new Date(`${props.rangeEnd}T12:00:00`);
    while (cursor <= end) {
        const k = key(cursor);
        out.push({
            key: k,
            num: cursor.getDate(),
            inMonth: cursor.getMonth() === monthDate.value.getMonth(),
            isToday: k === todayKey,
            events: props.events.filter((e) => e.date && key(new Date(e.date)) === k),
        });
        cursor.setDate(cursor.getDate() + 1);
    }

    return out;
});

function shift(delta: number) {
    const d = new Date(monthDate.value);
    d.setMonth(d.getMonth() + delta);
    router.get(calendario({ query: { mes: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` } }).url, {}, { preserveScroll: true });
}

function time(iso: string | null): string {
    return iso ? new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) : '';
}
</script>

<template>
    <Head title="Calendario editorial" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader title="Calendario editorial" description="Qué se publicó y qué está programado. Haz clic en un tip para editarlo o cambiar su fecha.">
            <template #actions>
                <Button as-child>
                    <Link :href="publicaciones.create()"><FilePen class="size-4" /> Nuevo tip</Link>
                </Button>
            </template>
        </AdminPageHeader>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
            <section class="overflow-hidden rounded-xl border bg-card">
                <header class="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <h2 class="font-display text-xl font-semibold capitalize">{{ title }}</h2>
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon-sm" aria-label="Mes anterior" @click="shift(-1)"><ChevronLeft class="size-4" /></Button>
                        <Button variant="outline" size="sm" @click="router.get(calendario().url)">Hoy</Button>
                        <Button variant="outline" size="icon-sm" aria-label="Mes siguiente" @click="shift(1)"><ChevronRight class="size-4" /></Button>
                    </div>
                </header>
                <div class="flex gap-4 border-b px-4 py-2 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-success" /> Publicada</span>
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full border-2 border-warning" /> Programada</span>
                </div>
                <!-- Escritorio/tablet: cuadrícula mensual -->
                <div class="hidden md:block">
                    <div class="grid grid-cols-7 border-b bg-muted/40 text-center text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                        <span v-for="w in WEEKDAYS" :key="w" class="py-2">{{ w }}</span>
                    </div>
                    <div class="grid grid-cols-7">
                        <div
                            v-for="day in days"
                            :key="day.key"
                            class="min-h-28 border-r border-b p-1.5 [&:nth-child(7n)]:border-r-0"
                            :class="{ 'bg-muted/30': !day.inMonth }"
                        >
                            <span
                                class="mb-1 inline-grid size-6 place-items-center rounded-full text-xs"
                                :class="day.isToday ? 'bg-brand font-bold text-brand-foreground' : day.inMonth ? 'text-foreground' : 'text-muted-foreground/60'"
                            >{{ day.num }}</span>
                            <ul class="flex flex-col gap-1">
                                <li v-for="e in day.events" :key="e.id">
                                    <Link
                                        :href="publicaciones.edit(e.id)"
                                        class="block truncate rounded-md border-l-2 px-1.5 py-1 text-[11px] leading-tight hover:bg-muted"
                                        :class="e.status === 'scheduled' ? 'border-warning bg-warning/8' : 'border-success bg-success/8'"
                                        :title="e.title"
                                    >
                                        <span class="text-muted-foreground tabular-nums">{{ time(e.date) }}</span> {{ e.title }}
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Móvil: agenda -->
                <ol class="divide-y md:hidden">
                    <template v-for="day in days" :key="day.key">
                        <li v-if="day.events.length" class="flex gap-3 p-4">
                            <span class="w-10 shrink-0 text-center">
                                <span class="block font-display text-xl font-semibold">{{ day.num }}</span>
                            </span>
                            <ul class="flex min-w-0 flex-1 flex-col gap-2">
                                <li v-for="e in day.events" :key="e.id">
                                    <Link :href="publicaciones.edit(e.id)" class="block rounded-lg border p-3 text-sm">
                                        {{ e.title }}
                                        <span class="block text-xs text-muted-foreground">{{ e.status === 'scheduled' ? 'Programada' : 'Publicada' }} · {{ time(e.date) }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </li>
                    </template>
                    <li v-if="!events.length" class="p-6 text-center text-sm text-muted-foreground">Sin publicaciones este mes.</li>
                </ol>
            </section>

            <aside class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <h2 class="text-sm font-semibold">Borradores sin fecha</h2>
                <p class="text-xs text-muted-foreground">Ábrelos y usa «Programar» para darles un lugar en el calendario.</p>
                <ul v-if="unscheduled.length" class="flex flex-col gap-1">
                    <li v-for="d in unscheduled" :key="d.id">
                        <Link :href="publicaciones.edit(d.id)" class="flex items-center gap-2 rounded-md px-2 py-2 text-sm hover:bg-muted">
                            <span class="size-2 shrink-0 rounded-full" :style="{ background: d.color ?? 'var(--color-muted-foreground)' }" />
                            <span class="truncate">{{ d.title }}</span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">No hay borradores pendientes.</p>
            </aside>
        </div>
    </div>
</template>
