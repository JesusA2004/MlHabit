<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import DateSlotPicker from '@/components/ds/DateSlotPicker.vue';
import type { TimeSlot } from '@/components/ds/DateSlotPicker.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { TIME_ZONE } from '@/lib/format';
import { apiFetch } from '@/lib/http';

/**
 * Selector de fecha y hora para asesorías. Consulta las fechas con lugar
 * de todo el periodo reservable (`?mes=YYYY-MM`) y los horarios del día
 * elegido (`?fecha=YYYY-MM-DD`). Los días sin horarios quedan deshabilitados.
 * v-model:slot = inicio ISO con zona ("2026-11-03T10:00:00-06:00").
 */
const date = defineModel<string | null>('date', { default: null });
const slot = defineModel<string | null>('slot', { default: null });

const props = withDefaults(
    defineProps<{
        availabilityUrl: string;
        /** Primer día reservable (ISO). */
        min: string;
        /** Último día reservable (ISO). */
        max: string;
        /** Panel: limitar a un asesor. */
        advisorId?: number | null;
        /** Horario que se excluye (p. ej. el actual al reprogramar). */
        excludeSlot?: string | null;
    }>(),
    { advisorId: null, excludeSlot: null },
);

const availableDates = ref<Set<string>>(new Set());
const loadingDates = ref(true);
const loadingSlots = ref(false);
const slots = ref<TimeSlot[]>([]);
const error = ref<string | null>(null);

const timeFormatter = new Intl.DateTimeFormat('es-MX', {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: TIME_ZONE,
});

function months(): string[] {
    const out: string[] = [];
    const [y1, m1] = props.min.split('-').map(Number);
    const [y2, m2] = props.max.split('-').map(Number);
    let y = y1;
    let m = m1;

    while (y < y2 || (y === y2 && m <= m2)) {
        out.push(`${y}-${String(m).padStart(2, '0')}`);
        m++;

        if (m > 12) {
            m = 1;
            y++;
        }
    }

    return out;
}

function url(params: Record<string, string>): string {
    const query = new URLSearchParams(params);

    if (props.advisorId) {
        query.set('asesor', String(props.advisorId));
    }

    return `${props.availabilityUrl}?${query.toString()}`;
}

async function loadDates() {
    loadingDates.value = true;
    error.value = null;

    try {
        const results = await Promise.all(
            months().map(async (mes) => {
                const response = await apiFetch(url({ mes }));

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return ((await response.json()) as { dates: string[] }).dates;
            }),
        );
        availableDates.value = new Set(results.flat());
    } catch {
        error.value =
            'No pudimos cargar la disponibilidad. Revisa tu conexión e inténtalo de nuevo.';
    } finally {
        loadingDates.value = false;
    }
}

async function loadSlots(day: string) {
    loadingSlots.value = true;
    slots.value = [];

    try {
        const response = await apiFetch(url({ fecha: day }));

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const data = (await response.json()) as {
            slots: { value: string; time: string }[];
        };

        if (date.value !== day) {
            return;
        }

        slots.value = data.slots
            .filter((s) => s.value !== props.excludeSlot)
            .map((s) => ({
                value: s.value,
                label: timeFormatter.format(new Date(s.value)),
            }));
    } catch {
        error.value =
            'No pudimos cargar los horarios de ese día. Inténtalo de nuevo.';
    } finally {
        loadingSlots.value = false;
    }
}

const isDateDisabled = computed(
    () => (iso: string) => !availableDates.value.has(iso),
);
const hasAvailability = computed(() => availableDates.value.size > 0);

watch(date, (day) => {
    slot.value = null;

    if (day) {
        void loadSlots(day);
    }
});

watch(
    () => props.advisorId,
    () => {
        date.value = null;
        void loadDates();
    },
);

onMounted(() => {
    void loadDates();
});

defineExpose({ reload: loadDates });
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="loadingDates"
            class="grid gap-4 rounded-xl border bg-card p-4 md:grid-cols-[18rem_1fr]"
            aria-busy="true"
        >
            <Skeleton class="h-72 rounded-lg" />
            <div class="space-y-3">
                <Skeleton class="h-5 w-40" />
                <div class="grid grid-cols-3 gap-2">
                    <Skeleton v-for="n in 6" :key="n" class="h-11 rounded-md" />
                </div>
            </div>
        </div>

        <template v-else>
            <DateSlotPicker
                v-model:date="date"
                v-model:slot="slot"
                :slots="slots"
                :loading="loadingSlots"
                :min="min"
                :max="max"
                :is-date-disabled="isDateDisabled"
                empty-text="Ya no quedan horarios este día. Prueba con otra fecha."
            />
            <p
                v-if="!hasAvailability && !error"
                class="text-sm text-muted-foreground"
                role="status"
            >
                Por ahora no hay horarios disponibles en las próximas semanas.
            </p>
        </template>

        <p
            v-if="error"
            class="flex items-start gap-2 text-sm text-danger-ink"
            role="alert"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                {{ error }}
                <button
                    type="button"
                    class="font-medium underline underline-offset-2"
                    @click="loadDates"
                >
                    Reintentar
                </button>
            </span>
        </p>
    </div>
</template>
