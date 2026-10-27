<script setup lang="ts">
import { CalendarDays, X } from '@lucide/vue';
import type { DateValue } from 'reka-ui';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    CALENDAR_LOCALE,
    WEEK_STARTS_ON,
    calendarDateToIso,
    formatIsoDate,
    isoToCalendarDate,
    unavailableFrom,
} from '@/lib/dates';
import { cn } from '@/lib/utils';

/**
 * Selector de fecha (es-MX, semana desde lunes). v-model en ISO
 * "YYYY-MM-DD" o null. Incluye un input oculto con `name` para usarse
 * dentro de <Form> de Inertia.
 */
const model = defineModel<string | null>({ default: null });

const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        placeholder?: string;
        /** Fecha mínima ISO. */
        min?: string;
        /** Fecha máxima ISO. */
        max?: string;
        /** Fechas ISO no seleccionables. */
        disabledDates?: string[];
        /** Regla extra de bloqueo, p. ej. domingos. */
        isDateDisabled?: (iso: string) => boolean;
        clearable?: boolean;
        disabled?: boolean;
        invalid?: boolean;
        /** Formato del texto del botón. */
        displayFormat?: Intl.DateTimeFormatOptions;
        class?: HTMLAttributes['class'];
    }>(),
    {
        placeholder: 'Elige una fecha',
        disabledDates: () => [],
        clearable: false,
        disabled: false,
        invalid: false,
        displayFormat: () => ({
            weekday: 'short',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    },
);

const open = ref(false);

const value = computed(() => isoToCalendarDate(model.value));
const minValue = computed(() => isoToCalendarDate(props.min));
const maxValue = computed(() => isoToCalendarDate(props.max));
const isUnavailable = computed(() =>
    unavailableFrom(props.disabledDates, props.isDateDisabled),
);
const label = computed(() => formatIsoDate(model.value, props.displayFormat));

function onSelect(date: DateValue | undefined) {
    model.value = calendarDateToIso(date);

    if (date) {
        open.value = false;
    }
}
</script>

<template>
    <Popover v-model:open="open">
        <div :class="cn('relative w-full', props.class)">
            <PopoverTrigger as-child>
                <Button
                    :id="props.id"
                    type="button"
                    variant="outline"
                    :disabled="props.disabled"
                    :aria-invalid="props.invalid || undefined"
                    :class="
                        cn(
                            'h-11 w-full justify-start gap-2.5 bg-card px-3 text-left font-normal md:h-9',
                            !model && 'text-muted-foreground',
                            props.clearable && model && 'pr-10',
                        )
                    "
                >
                    <CalendarDays class="text-muted-foreground" />
                    <span class="truncate first-letter:uppercase">{{
                        label ?? props.placeholder
                    }}</span>
                </Button>
            </PopoverTrigger>
            <button
                v-if="props.clearable && model && !props.disabled"
                type="button"
                class="absolute top-1/2 right-1.5 grid size-8 -translate-y-1/2 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground md:size-7"
                aria-label="Quitar fecha"
                @click="model = null"
            >
                <X class="size-4" />
            </button>
        </div>
        <PopoverContent class="w-auto rounded-xl p-0" align="start">
            <Calendar
                :model-value="value"
                :min-value="minValue"
                :max-value="maxValue"
                :is-date-unavailable="isUnavailable"
                :locale="CALENDAR_LOCALE"
                :week-starts-on="WEEK_STARTS_ON"
                :default-placeholder="value ?? minValue"
                weekday-format="short"
                initial-focus
                @update:model-value="onSelect"
            />
        </PopoverContent>
    </Popover>
    <input
        v-if="props.name"
        type="hidden"
        :name="props.name"
        :value="model ?? ''"
    />
</template>
