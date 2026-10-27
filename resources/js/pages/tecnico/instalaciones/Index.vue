<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarClock, ChevronRight, MapPin, Wrench } from '@lucide/vue';
import { DateText, EmptyState, StatusBadge } from '@/components/ds';
import { cn } from '@/lib/utils';
import instalaciones from '@/routes/tecnico/instalaciones';
import type { InstallationRow } from '@/types/operations';

type Row = InstallationRow & { address: string | null };

defineProps<{ active: Row[]; history: Row[] }>();

defineOptions({ layout: { title: 'Instalaciones' } });

function isToday(iso: string | null): boolean {
    if (!iso) return false;
    const fmt = (d: Date) =>
        d.toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' });

    return fmt(new Date(iso)) === fmt(new Date());
}

function progress(row: Row): number {
    return row.itemsCount
        ? Math.round(((row.doneCount ?? 0) / row.itemsCount) * 100)
        : 0;
}
</script>

<template>
    <Head title="Instalaciones" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-8">
        <section aria-labelledby="activas" class="flex flex-col gap-3">
            <h1
                id="activas"
                class="font-display text-xl font-semibold tracking-tight"
            >
                Por hacer
            </h1>

            <EmptyState
                v-if="active.length === 0"
                :icon="Wrench"
                title="No tienes instalaciones programadas"
                description="Cuando la oficina te asigne una instalación aparecerá aquí con su dirección y partidas."
            />

            <ul v-else class="flex flex-col gap-3">
                <li v-for="row in active" :key="row.id">
                    <Link
                        :href="instalaciones.show(row.id).url"
                        :class="
                            cn(
                                'flex flex-col gap-3 rounded-2xl border bg-card p-4 shadow-xs transition-colors active:bg-muted/60',
                                row.status.value === 'in_progress' &&
                                    'border-primary/50 ring-2 ring-primary/15',
                            )
                        "
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        :tone="row.status.tone"
                                        :label="row.status.label"
                                        size="sm"
                                    />
                                    <span
                                        v-if="isToday(row.scheduledStart)"
                                        class="text-xs font-semibold text-primary"
                                        >Hoy</span
                                    >
                                </span>
                                <span
                                    class="truncate text-base font-semibold"
                                    >{{ row.project.name }}</span
                                >
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                    >{{ row.folio
                                    }}<template v-if="row.project.customer">
                                        · {{ row.project.customer }}</template
                                    ></span
                                >
                            </div>
                            <ChevronRight
                                class="mt-1 size-5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>

                        <div
                            class="flex flex-col gap-1.5 text-sm text-muted-foreground"
                        >
                            <span
                                v-if="row.scheduledStart"
                                class="flex items-center gap-2"
                            >
                                <CalendarClock
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <DateText
                                    :value="row.scheduledStart"
                                    format="datetime"
                                />
                            </span>
                            <span
                                v-if="row.address"
                                class="flex items-center gap-2"
                            >
                                <MapPin
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ row.address }}</span>
                            </span>
                        </div>

                        <div
                            v-if="row.itemsCount"
                            class="flex items-center gap-3"
                        >
                            <div
                                class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                :aria-valuenow="progress(row)"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-label="Avance de partidas"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-[width]"
                                    :style="{ width: `${progress(row)}%` }"
                                />
                            </div>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                                >{{ row.doneCount ?? 0 }}/{{
                                    row.itemsCount
                                }}
                                partidas</span
                            >
                        </div>
                    </Link>
                </li>
            </ul>
        </section>

        <section
            v-if="history.length"
            aria-labelledby="historial"
            class="flex flex-col gap-3"
        >
            <h2
                id="historial"
                class="font-display text-lg font-semibold tracking-tight"
            >
                Enviadas y cerradas
            </h2>
            <ul class="flex flex-col divide-y rounded-2xl border bg-card">
                <li v-for="row in history" :key="row.id">
                    <Link
                        :href="instalaciones.show(row.id).url"
                        class="flex min-h-14 items-center gap-3 px-4 py-3 active:bg-muted/60"
                    >
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate text-sm font-medium">{{
                                row.project.name
                            }}</span>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                                >{{ row.folio }}</span
                            >
                        </span>
                        <StatusBadge
                            :tone="row.status.tone"
                            :label="row.status.label"
                            size="sm"
                        />
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
