<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarX } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export type AgendaBooking = {
    id: number;
    start: string;
    end: string;
    title: string;
    subtitle: string | null;
    url: string | null;
    held: boolean;
};

export type AgendaDay = {
    date: string;
    windows: { start: string; end: string }[];
    bookings: AgendaBooking[];
};

/**
 * Semana de agenda: rejilla de horas en escritorio (disponibilidad como
 * fondo y citas como bloques) y lista por día en móvil. Cada día es una
 * lista con nombre accesible; la disponibilidad se anuncia en texto.
 */
const props = withDefaults(
    defineProps<{
        days: AgendaDay[];
        today: string;
        /** Altura en px de una hora en la rejilla. */
        hourHeight?: number;
        class?: HTMLAttributes['class'];
    }>(),
    { hourHeight: 52 },
);

const dayName = new Intl.DateTimeFormat('es-MX', { weekday: 'short' });
const dayNumber = new Intl.DateTimeFormat('es-MX', { day: 'numeric' });
const dayLong = new Intl.DateTimeFormat('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

function asDate(iso: string): Date {
    const [y, m, d] = iso.split('-').map(Number);

    return new Date(y, m - 1, d, 12);
}

function minutes(time: string): number {
    const [h, m] = time.split(':').map(Number);

    return h * 60 + (m || 0);
}

function formatTime(time: string): string {
    const [h, m] = time.split(':').map(Number);

    return new Intl.DateTimeFormat('es-MX', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(2000, 0, 1, h, m));
}

/** Rango visible: de la primera a la última hora con algo, con margen; mínimo 8–19 h. */
const range = computed(() => {
    let start = 8 * 60;
    let end = 19 * 60;

    for (const day of props.days) {
        for (const item of [...day.windows, ...day.bookings]) {
            start = Math.min(start, minutes(item.start));
            end = Math.max(end, minutes(item.end));
        }
    }

    return {
        start: Math.floor(start / 60) * 60,
        end: Math.min(24 * 60, Math.ceil(end / 60) * 60),
    };
});

const hours = computed(() => {
    const out: number[] = [];

    for (let t = range.value.start; t < range.value.end; t += 60) {
        out.push(t);
    }

    return out;
});

const gridHeight = computed(
    () => ((range.value.end - range.value.start) / 60) * props.hourHeight,
);

function top(time: string): string {
    return `${((minutes(time) - range.value.start) / 60) * props.hourHeight}px`;
}

function height(start: string, end: string): string {
    return `${Math.max(((minutes(end) - minutes(start)) / 60) * props.hourHeight, 22)}px`;
}

function hourLabel(t: number): string {
    return new Intl.DateTimeFormat('es-MX', { hour: 'numeric' }).format(
        new Date(2000, 0, 1, t / 60, 0),
    );
}

function availabilityText(day: AgendaDay): string {
    if (day.windows.length === 0) {
        return 'Sin horario disponible';
    }

    return (
        'Disponible ' +
        day.windows
            .map((w) => `${formatTime(w.start)} a ${formatTime(w.end)}`)
            .join(', ')
    );
}
</script>

<template>
    <div :class="cn('rounded-xl border bg-card', props.class)">
        <!-- Escritorio: rejilla semanal -->
        <div class="hidden md:block">
            <div
                class="grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))] border-b"
            >
                <div />
                <div
                    v-for="day in days"
                    :key="day.date"
                    class="border-l px-2 py-2.5 text-center"
                    :class="day.date === today && 'bg-primary-soft/60'"
                >
                    <p
                        class="text-xs font-medium text-muted-foreground capitalize"
                    >
                        {{ dayName.format(asDate(day.date)) }}
                    </p>
                    <p
                        class="mx-auto mt-0.5 flex size-8 items-center justify-center rounded-full font-display text-lg font-semibold tabular-nums"
                        :class="
                            day.date === today
                                ? 'bg-primary text-primary-foreground'
                                : 'text-foreground'
                        "
                    >
                        {{ dayNumber.format(asDate(day.date)) }}
                    </p>
                </div>
            </div>

            <div class="max-h-[38rem] overflow-y-auto">
                <div
                    class="relative grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))]"
                    :style="{ height: `${gridHeight}px` }"
                >
                    <!-- Horas -->
                    <div class="relative" aria-hidden="true">
                        <span
                            v-for="t in hours"
                            :key="t"
                            class="absolute right-2 -translate-y-1/2 text-[11px] text-muted-foreground tabular-nums first:translate-y-0"
                            :style="{
                                top: `${((t - range.start) / 60) * hourHeight}px`,
                            }"
                        >
                            {{ hourLabel(t) }}
                        </span>
                    </div>

                    <section
                        v-for="day in days"
                        :key="day.date"
                        class="relative border-l"
                        :class="day.date === today && 'bg-primary-soft/20'"
                        :aria-label="dayLong.format(asDate(day.date))"
                    >
                        <!-- Líneas de hora -->
                        <div
                            v-for="t in hours"
                            :key="t"
                            class="pointer-events-none absolute inset-x-0 border-t border-border/60"
                            :style="{
                                top: `${((t - range.start) / 60) * hourHeight}px`,
                            }"
                            aria-hidden="true"
                        />
                        <!-- Disponibilidad -->
                        <div
                            v-for="(w, i) in day.windows"
                            :key="`w-${i}`"
                            class="absolute inset-x-1 rounded-md border border-dashed border-primary/30 bg-primary-soft/60"
                            :style="{
                                top: top(w.start),
                                height: height(w.start, w.end),
                            }"
                            aria-hidden="true"
                        />
                        <p class="sr-only">{{ availabilityText(day) }}</p>

                        <!-- Citas -->
                        <ol
                            :aria-label="`Citas del ${dayLong.format(asDate(day.date))}`"
                        >
                            <li
                                v-for="b in day.bookings"
                                :key="b.id"
                                class="absolute inset-x-1.5 z-10"
                                :style="{
                                    top: top(b.start),
                                    height: height(b.start, b.end),
                                }"
                            >
                                <component
                                    :is="b.url ? Link : 'div'"
                                    :href="b.url ?? undefined"
                                    :class="
                                        cn(
                                            'flex h-full flex-col overflow-hidden rounded-md border-l-[3px] px-2 py-1 text-left text-xs shadow-xs transition-colors',
                                            b.held
                                                ? 'border-warning bg-warning-soft text-warning-ink'
                                                : 'border-primary bg-card text-foreground ring-1 ring-border',
                                            b.url &&
                                                'hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none',
                                        )
                                    "
                                >
                                    <span class="font-medium tabular-nums">{{
                                        formatTime(b.start)
                                    }}</span>
                                    <span class="truncate font-medium">{{
                                        b.title
                                    }}</span>
                                    <span
                                        v-if="b.subtitle"
                                        class="truncate text-muted-foreground"
                                        >{{ b.subtitle }}</span
                                    >
                                    <span v-if="b.held" class="truncate"
                                        >Apartado (pendiente de pago)</span
                                    >
                                </component>
                            </li>
                        </ol>
                    </section>
                </div>
            </div>
        </div>

        <!-- Móvil: lista por día -->
        <ol class="divide-y md:hidden">
            <li
                v-for="day in days"
                :key="day.date"
                class="px-4 py-3"
                :class="day.date === today && 'bg-primary-soft/30'"
            >
                <div class="flex items-baseline justify-between gap-3">
                    <h3 class="text-sm font-medium first-letter:uppercase">
                        {{ dayLong.format(asDate(day.date)) }}
                        <span
                            v-if="day.date === today"
                            class="ml-1 text-xs font-normal text-primary"
                            >Hoy</span
                        >
                    </h3>
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{{ day.bookings.length }} citas</span
                    >
                </div>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ availabilityText(day) }}
                </p>

                <ul v-if="day.bookings.length" class="mt-2 grid gap-2">
                    <li v-for="b in day.bookings" :key="b.id">
                        <component
                            :is="b.url ? Link : 'div'"
                            :href="b.url ?? undefined"
                            :class="
                                cn(
                                    'flex min-h-11 items-center gap-3 rounded-md border-l-[3px] px-3 py-2 text-sm',
                                    b.held
                                        ? 'border-warning bg-warning-soft'
                                        : 'border-primary bg-muted/50',
                                )
                            "
                        >
                            <span
                                class="w-20 shrink-0 font-medium tabular-nums"
                                >{{ formatTime(b.start) }}</span
                            >
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{
                                    b.title
                                }}</span>
                                <span
                                    v-if="b.subtitle"
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ b.subtitle }}</span
                                >
                            </span>
                        </component>
                    </li>
                </ul>
                <p
                    v-else-if="day.windows.length"
                    class="mt-2 flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <CalendarX class="size-3.5" aria-hidden="true" /> Sin citas
                </p>
            </li>
        </ol>
    </div>
</template>
