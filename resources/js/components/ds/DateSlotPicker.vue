<script setup lang="ts">
import { Clock } from '@lucide/vue';
import type { DateValue } from 'reka-ui';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Calendar } from '@/components/ui/calendar';
import { Skeleton } from '@/components/ui/skeleton';
import {
    CALENDAR_LOCALE,
    WEEK_STARTS_ON,
    calendarDateToIso,
    formatIsoDate,
    isoToCalendarDate,
    unavailableFrom,
} from '@/lib/dates';
import { cn } from '@/lib/utils';

export type TimeSlot = {
    /** Valor que se envía (p. ej. "2026-11-03T10:00:00-06:00" o "10:00"). */
    value: string;
    /** Texto visible, p. ej. "10:00 a. m.". */
    label: string;
    available?: boolean;
    hint?: string;
};

/**
 * Elegir día y horario para una cita o visita. La página carga los
 * horarios del día elegido (escucha `update:date`) y los pasa en `slots`.
 */
const date = defineModel<string | null>('date', { default: null });
const slot = defineModel<string | null>('slot', { default: null });

const props = withDefaults(
    defineProps<{
        slots?: TimeSlot[];
        loading?: boolean;
        min?: string;
        max?: string;
        disabledDates?: string[];
        isDateDisabled?: (iso: string) => boolean;
        emptyText?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        slots: () => [],
        loading: false,
        disabledDates: () => [],
        emptyText:
            'No hay horarios disponibles este día. Prueba con otra fecha.',
    },
);

const value = computed(() => isoToCalendarDate(date.value));
const minValue = computed(() => isoToCalendarDate(props.min));
const maxValue = computed(() => isoToCalendarDate(props.max));
const isUnavailable = computed(() =>
    unavailableFrom(props.disabledDates, props.isDateDisabled),
);
const heading = computed(() =>
    formatIsoDate(date.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);

function onDate(next: DateValue | undefined) {
    const iso = calendarDateToIso(next);

    if (iso !== date.value) {
        date.value = iso;
        slot.value = null;
    }
}
</script>

<template>
    <div
        data-slot="date-slot-picker"
        :class="
            cn(
                'grid overflow-hidden rounded-xl border bg-card md:grid-cols-[auto_minmax(0,1fr)]',
                props.class,
            )
        "
    >
        <Calendar
            :model-value="value"
            :min-value="minValue"
            :max-value="maxValue"
            :is-date-unavailable="isUnavailable"
            :locale="CALENDAR_LOCALE"
            :week-starts-on="WEEK_STARTS_ON"
            :default-placeholder="value ?? minValue"
            weekday-format="short"
            class="mx-auto md:border-r"
            @update:model-value="onDate"
        />

        <div class="flex min-h-64 flex-col border-t p-4 md:border-t-0">
            <p
                class="text-sm font-medium text-foreground first-letter:uppercase"
            >
                {{ heading ?? 'Elige un día' }}
            </p>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Horario del centro de México
            </p>

            <div
                v-if="!date"
                class="flex flex-1 flex-col items-center justify-center gap-2 py-8 text-center"
            >
                <Clock
                    class="size-5 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="max-w-56 text-sm text-muted-foreground">
                    Selecciona un día para ver los horarios disponibles.
                </p>
            </div>

            <div
                v-else-if="props.loading"
                class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3"
                aria-busy="true"
            >
                <Skeleton v-for="n in 6" :key="n" class="h-11 rounded-md" />
            </div>

            <p
                v-else-if="props.slots.length === 0"
                class="mt-6 text-sm text-muted-foreground"
                role="status"
            >
                {{ props.emptyText }}
            </p>

            <div
                v-else
                role="radiogroup"
                aria-label="Horarios disponibles"
                class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3"
            >
                <button
                    v-for="option in props.slots"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="slot === option.value"
                    :disabled="option.available === false"
                    :class="
                        cn(
                            'flex h-11 flex-col items-center justify-center rounded-md border text-sm font-medium tabular-nums transition-colors duration-150',
                            'focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none',
                            slot === option.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input bg-card text-foreground hover:border-primary/50 hover:bg-primary-soft/50',
                            option.available === false &&
                                'cursor-not-allowed line-through opacity-40',
                        )
                    "
                    @click="slot = option.value"
                >
                    {{ option.label }}
                    <span
                        v-if="option.hint"
                        class="text-[11px] font-normal opacity-80"
                        >{{ option.hint }}</span
                    >
                </button>
            </div>
        </div>
    </div>
</template>
