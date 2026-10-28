<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarOff,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    Trash2,
    UserRound,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AgendaWeek from '@/components/crm/AgendaWeek.vue';
import type { AgendaDay } from '@/components/crm/AgendaWeek.vue';
import TimeSelect from '@/components/crm/TimeSelect.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import agenda from '@/routes/admin/agenda';

type Option = { value: string; label: string; tone?: Tone };
type Rule = {
    id: number;
    weekday: number;
    startsAt: string;
    endsAt: string;
    isActive: boolean;
};
type Exception = {
    id: number;
    type: Option;
    kind: string | null;
    startsAt: string;
    endsAt: string;
    reason: string | null;
    isPast: boolean;
};

const props = defineProps<{
    kind: 'consultation' | 'visit';
    kinds: Option[];
    people: { value: number; label: string }[];
    person: { id: number; name: string } | null;
    week: { start: string; end: string; today: string };
    rules: Rule[];
    effective: { from: string | null; until: string | null };
    exceptions: Exception[];
    days: AgendaDay[];
    exceptionTypes: Option[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Agenda', href: '/admin/agenda' },
        ],
    },
});

// ── Navegación ───────────────────────────────────────────────────────
function go(params: {
    tipo?: string;
    persona?: number | null;
    semana?: string;
}) {
    router.get(
        agenda.index().url,
        {
            tipo: params.tipo ?? props.kind,
            persona:
                params.persona === undefined
                    ? props.person?.id
                    : (params.persona ?? undefined),
            semana: params.semana ?? props.week.start,
        },
        { preserveScroll: true, preserveState: false, replace: true },
    );
}

const kindModel = computed({
    get: () => props.kind as string,
    set: (value: string) => go({ tipo: value, persona: null }),
});

const personModel = computed({
    get: () => (props.person ? String(props.person.id) : undefined),
    set: (value: string | undefined) =>
        go({ persona: value ? Number(value) : null }),
});

function shiftWeek(days: number): string {
    const [y, m, d] = props.week.start.split('-').map(Number);
    const date = new Date(y, m - 1, d + days, 12);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

const jumpDate = ref<string | null>(null);
watch(jumpDate, (value) => {
    if (value) {
        go({ semana: value });
    }
});

const weekLabel = computed(() => {
    const fmt = new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'short',
    });
    const parse = (iso: string) => {
        const [y, m, d] = iso.split('-').map(Number);

        return new Date(y, m - 1, d, 12);
    };

    return `${fmt.format(parse(props.week.start))} – ${fmt.format(parse(props.week.end))}`;
});

const isCurrentWeek = computed(
    () =>
        props.week.today >= props.week.start &&
        props.week.today <= props.week.end,
);
const bookingsCount = computed(() =>
    props.days.reduce((n, d) => n + d.bookings.length, 0),
);

// ── Horario semanal ──────────────────────────────────────────────────
/** Lunes a domingo (weekday de PHP: 0 = domingo). */
const WEEKDAYS = [
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
    { value: 6, label: 'Sábado' },
    { value: 0, label: 'Domingo' },
];

type Range = { key: number; starts_at: string | null; ends_at: string | null };
let seq = 0;

function buildSchedule(): Record<number, Range[]> {
    const map: Record<number, Range[]> = {};

    for (const day of WEEKDAYS) {
        map[day.value] = props.rules
            .filter((r) => r.weekday === day.value)
            .map((r) => ({
                key: ++seq,
                starts_at: r.startsAt,
                ends_at: r.endsAt,
            }));
    }

    return map;
}

const schedule = ref<Record<number, Range[]>>(buildSchedule());
const effectiveFrom = ref<string | null>(props.effective.from);
const effectiveUntil = ref<string | null>(props.effective.until);
const scheduleForm = useForm({
    kind: props.kind as string,
    ranges: [] as { weekday: number; starts_at: string; ends_at: string }[],
    effective_from: null as string | null,
    effective_until: null as string | null,
});

watch(
    () => [props.rules, props.person?.id, props.kind],
    () => {
        schedule.value = buildSchedule();
        effectiveFrom.value = props.effective.from;
        effectiveUntil.value = props.effective.until;
        scheduleForm.clearErrors();
    },
);

const scheduleDirty = ref(false);
watch(
    [schedule, effectiveFrom, effectiveUntil],
    () => (scheduleDirty.value = true),
    { deep: true },
);
watch(
    () => props.rules,
    () => setTimeout(() => (scheduleDirty.value = false)),
    { immediate: true },
);

function toggleDay(weekday: number, on: boolean) {
    schedule.value[weekday] = on
        ? [{ key: ++seq, starts_at: '09:00', ends_at: '14:00' }]
        : [];
}

function addRange(weekday: number) {
    const list = schedule.value[weekday];
    const last = list[list.length - 1];
    const start = last?.ends_at ?? '09:00';
    const [h, m] = start.split(':').map(Number);
    const end = `${String(Math.min(h + 2, 22)).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    list.push({ key: ++seq, starts_at: start, ends_at: end });
}

function removeRange(weekday: number, key: number) {
    schedule.value[weekday] = schedule.value[weekday].filter(
        (r) => r.key !== key,
    );
}

/** Orden plano enviado al servidor → para ubicar errores `ranges.{i}.campo`. */
const flatOrder = computed(() =>
    WEEKDAYS.flatMap((d) =>
        schedule.value[d.value].map((r) => ({ weekday: d.value, range: r })),
    ),
);

function rangeError(key: number): string | undefined {
    const index = flatOrder.value.findIndex((item) => item.range.key === key);
    const errors = scheduleForm.errors as Record<string, string | undefined>;

    return (
        errors[`ranges.${index}.starts_at`] ?? errors[`ranges.${index}.ends_at`]
    );
}

function saveSchedule() {
    if (!props.person) {
        return;
    }

    scheduleForm
        .transform(() => ({
            kind: props.kind,
            ranges: flatOrder.value.map((item) => ({
                weekday: item.weekday,
                starts_at: item.range.starts_at ?? '',
                ends_at: item.range.ends_at ?? '',
            })),
            effective_from: effectiveFrom.value,
            effective_until: effectiveUntil.value,
        }))
        .put(agenda.horario(props.person.id).url, { preserveScroll: true });
}

const weeklyHours = computed(() => {
    let total = 0;

    for (const list of Object.values(schedule.value)) {
        for (const r of list) {
            if (r.starts_at && r.ends_at) {
                const [h1, m1] = r.starts_at.split(':').map(Number);
                const [h2, m2] = r.ends_at.split(':').map(Number);
                total += Math.max(0, h2 * 60 + m2 - (h1 * 60 + m1));
            }
        }
    }

    return Math.round((total / 60) * 10) / 10;
});

// ── Excepciones ──────────────────────────────────────────────────────
const exceptionOpen = ref(false);
const editingException = ref<Exception | null>(null);
const exceptionForm = useForm({
    type: 'blocked',
    scope: 'kind' as 'kind' | 'all',
    start_date: null as string | null,
    start_time: '09:00' as string | null,
    end_date: null as string | null,
    end_time: '14:00' as string | null,
    all_day: false,
    reason: '',
});

/** Errores del servidor con los nombres de campo de la API (starts_at/ends_at). */
const excErrors = computed(
    () => exceptionForm.errors as Record<string, string | undefined>,
);

function splitIso(iso: string): { date: string; time: string } {
    // El backend envía ISO con zona del negocio; tomamos fecha/hora locales del texto.
    return { date: iso.slice(0, 10), time: iso.slice(11, 16) };
}

function openException(exception: Exception | null) {
    editingException.value = exception;
    exceptionForm.clearErrors();

    if (exception) {
        const start = splitIso(exception.startsAt);
        const end = splitIso(exception.endsAt);
        exceptionForm.type = exception.type.value;
        exceptionForm.scope = exception.kind ? 'kind' : 'all';
        exceptionForm.start_date = start.date;
        exceptionForm.start_time = start.time;
        exceptionForm.end_date = end.date;
        exceptionForm.end_time = end.time;
        exceptionForm.all_day = start.time === '00:00' && end.time === '23:59';
        exceptionForm.reason = exception.reason ?? '';
    } else {
        exceptionForm.type = 'blocked';
        exceptionForm.scope = 'kind';
        exceptionForm.start_date =
            props.week.today >= props.week.start &&
            props.week.today <= props.week.end
                ? props.week.today
                : props.week.start;
        exceptionForm.start_time = '09:00';
        exceptionForm.end_date = exceptionForm.start_date;
        exceptionForm.end_time = '14:00';
        exceptionForm.all_day = false;
        exceptionForm.reason = '';
    }

    exceptionOpen.value = true;
}

watch(
    () => exceptionForm.type,
    (type) => {
        if (type === 'vacation') {
            exceptionForm.all_day = true;
        }

        if (type === 'extra') {
            exceptionForm.all_day = false;
            exceptionForm.end_date = exceptionForm.start_date;
        }
    },
);

watch(
    () => exceptionForm.start_date,
    (value) => {
        if (
            value &&
            (!exceptionForm.end_date ||
                exceptionForm.end_date < value ||
                exceptionForm.type === 'extra')
        ) {
            exceptionForm.end_date = value;
        }
    },
);

function submitException() {
    if (!props.person) {
        return;
    }

    const transform = () => ({
        type: exceptionForm.type,
        kind: exceptionForm.scope === 'kind' ? props.kind : null,
        starts_at: exceptionForm.start_date
            ? `${exceptionForm.start_date} ${exceptionForm.all_day ? '00:00' : exceptionForm.start_time}`
            : '',
        ends_at: exceptionForm.end_date
            ? `${exceptionForm.end_date} ${exceptionForm.all_day ? '23:59' : exceptionForm.end_time}`
            : '',
        reason: exceptionForm.reason || null,
    });
    const options = {
        preserveScroll: true,
        onSuccess: () => (exceptionOpen.value = false),
    };

    if (editingException.value) {
        exceptionForm
            .transform(transform)
            .put(
                agenda.excepciones.update(editingException.value.id).url,
                options,
            );
    } else {
        exceptionForm
            .transform(transform)
            .post(agenda.excepciones.store(props.person.id).url, options);
    }
}

const deleteOpen = ref(false);
const deletingException = ref<Exception | null>(null);

function askDeleteException(exception: Exception) {
    deletingException.value = exception;
    deleteOpen.value = true;
}

function confirmDeleteException(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!deletingException.value) {
            resolve();

            return;
        }

        router.delete(
            agenda.excepciones.destroy(deletingException.value.id).url,
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () =>
                    reject(new Error('No se pudo eliminar la excepción.')),
            },
        );
    });
}

const rangeFormatter = new Intl.DateTimeFormat('es-MX', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: 'numeric',
    minute: '2-digit',
    timeZone: 'America/Mexico_City',
});

function exceptionRange(e: Exception): string {
    return `${rangeFormatter.format(new Date(e.startsAt))} → ${rangeFormatter.format(new Date(e.endsAt))}`;
}

const upcomingExceptions = computed(() =>
    props.exceptions.filter((e) => !e.isPast),
);
const pastExceptions = computed(() => props.exceptions.filter((e) => e.isPast));
const kindLabel = computed(() =>
    props.kind === 'visit' ? 'visitas técnicas' : 'asesorías',
);
</script>

<template>
    <Head title="Agenda" />

    <PageContainer>
        <PageHeader
            title="Agenda"
            description="Disponibilidad de asesores y técnicos. Lo que configures aquí es lo que el cliente ve al agendar su asesoría o su visita."
        />

        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Tabs v-model="kindModel">
                    <TabsList class="w-full sm:w-auto">
                        <TabsTrigger
                            v-for="k in kinds"
                            :key="k.value"
                            :value="k.value"
                            class="flex-1 sm:flex-none"
                            >{{ k.label }}</TabsTrigger
                        >
                    </TabsList>
                </Tabs>
                <Select v-if="people.length" v-model="personModel">
                    <SelectTrigger class="w-full sm:w-64" aria-label="Persona">
                        <UserRound class="size-4 text-muted-foreground" />
                        <SelectValue placeholder="Elige a una persona" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="p in people"
                            :key="p.value"
                            :value="String(p.value)"
                            >{{ p.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>

            <div v-if="person" class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Semana anterior"
                    @click="go({ semana: shiftWeek(-7) })"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <span
                    class="min-w-36 text-center text-sm font-medium tabular-nums"
                    aria-live="polite"
                    >{{ weekLabel }}</span
                >
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Semana siguiente"
                    @click="go({ semana: shiftWeek(7) })"
                >
                    <ChevronRight class="size-4" />
                </Button>
                <Button
                    v-if="!isCurrentWeek"
                    variant="ghost"
                    size="sm"
                    @click="go({ semana: week.today })"
                    >Hoy</Button
                >
                <DatePicker
                    v-model="jumpDate"
                    class="w-auto"
                    placeholder="Ir a fecha"
                    :display-format="{ day: 'numeric', month: 'short' }"
                />
            </div>
        </div>

        <EmptyState
            v-if="!person"
            :icon="UserRound"
            :title="
                kind === 'visit'
                    ? 'Aún no hay técnicos activos'
                    : 'Aún no hay asesores'
            "
            :description="
                kind === 'visit'
                    ? 'Invita a los técnicos desde Usuarios con el rol Técnico; después configura aquí su disponibilidad.'
                    : 'Las personas con permiso para gestionar asesorías aparecerán aquí para configurar su horario.'
            "
        />

        <template v-else>
            <section aria-labelledby="week-heading" class="grid gap-3">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"
                >
                    <h2
                        id="week-heading"
                        class="font-display text-lg font-semibold"
                    >
                        Semana de {{ person.name }}
                    </h2>
                    <p class="text-sm text-muted-foreground tabular-nums">
                        {{ bookingsCount }}
                        {{ bookingsCount === 1 ? 'cita' : 'citas' }} ·
                        disponibilidad sombreada
                    </p>
                </div>
                <AgendaWeek :days="days" :today="week.today" />
            </section>

            <div
                class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]"
            >
                <!-- Horario semanal -->
                <SectionCard
                    title="Horario semanal"
                    :icon="CalendarClock"
                    :description="`Horas en que ${person.name} puede atender ${kindLabel}. ${weeklyHours} h por semana.`"
                >
                    <form class="grid gap-4" @submit.prevent="saveSchedule">
                        <fieldset
                            class="grid divide-y rounded-lg border"
                            :disabled="!can.manage"
                        >
                            <legend class="sr-only">Días y horarios</legend>
                            <div
                                v-for="day in WEEKDAYS"
                                :key="day.value"
                                class="grid gap-3 p-3 sm:grid-cols-[9rem_minmax(0,1fr)] sm:items-start"
                            >
                                <div class="flex min-h-9 items-center gap-3">
                                    <Switch
                                        :id="`day-${day.value}`"
                                        :model-value="
                                            schedule[day.value].length > 0
                                        "
                                        @update:model-value="
                                            (on: boolean) =>
                                                toggleDay(day.value, on)
                                        "
                                    />
                                    <Label
                                        :for="`day-${day.value}`"
                                        class="font-medium"
                                        >{{ day.label }}</Label
                                    >
                                    <span
                                        v-if="!schedule[day.value].length"
                                        class="ml-auto text-sm text-muted-foreground sm:hidden"
                                        >No disponible</span
                                    >
                                </div>
                                <div
                                    v-if="schedule[day.value].length"
                                    class="grid gap-2"
                                >
                                    <div
                                        v-for="range in schedule[day.value]"
                                        :key="range.key"
                                        class="grid gap-1"
                                    >
                                        <div class="flex items-center gap-2">
                                            <TimeSelect
                                                v-model="range.starts_at"
                                                class="w-32"
                                                :aria-label="`${day.label}: inicio`"
                                                :invalid="
                                                    Boolean(
                                                        rangeError(range.key),
                                                    )
                                                "
                                            />
                                            <span
                                                class="text-sm text-muted-foreground"
                                                aria-hidden="true"
                                                >a</span
                                            >
                                            <TimeSelect
                                                v-model="range.ends_at"
                                                class="w-32"
                                                :aria-label="`${day.label}: fin`"
                                                :invalid="
                                                    Boolean(
                                                        rangeError(range.key),
                                                    )
                                                "
                                            />
                                            <Button
                                                v-if="can.manage"
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                :aria-label="`Quitar horario del ${day.label.toLowerCase()}`"
                                                @click="
                                                    removeRange(
                                                        day.value,
                                                        range.key,
                                                    )
                                                "
                                            >
                                                <X class="size-4" />
                                            </Button>
                                        </div>
                                        <InputError
                                            :message="rangeError(range.key)"
                                        />
                                    </div>
                                    <div v-if="can.manage">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="-ml-2"
                                            @click="addRange(day.value)"
                                        >
                                            <Plus class="size-4" /> Agregar
                                            horario
                                        </Button>
                                    </div>
                                </div>
                                <p
                                    v-else
                                    class="hidden min-h-9 items-center text-sm text-muted-foreground sm:flex"
                                >
                                    No disponible
                                </p>
                            </div>
                        </fieldset>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="effective-from"
                                    >Vigente desde
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opcional)</span
                                    ></Label
                                >
                                <DatePicker
                                    id="effective-from"
                                    v-model="effectiveFrom"
                                    clearable
                                    :disabled="!can.manage"
                                    placeholder="Desde hoy"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="effective-until"
                                    >Vigente hasta
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opcional)</span
                                    ></Label
                                >
                                <DatePicker
                                    id="effective-until"
                                    v-model="effectiveUntil"
                                    clearable
                                    :min="effectiveFrom ?? undefined"
                                    :disabled="!can.manage"
                                    placeholder="Sin fecha final"
                                />
                                <InputError
                                    :message="
                                        scheduleForm.errors.effective_until
                                    "
                                />
                            </div>
                        </div>

                        <div
                            v-if="can.manage"
                            class="flex flex-wrap items-center gap-3"
                        >
                            <Button
                                type="submit"
                                :disabled="scheduleForm.processing"
                            >
                                <Spinner v-if="scheduleForm.processing" />
                                {{
                                    scheduleForm.processing
                                        ? 'Guardando…'
                                        : 'Guardar horario'
                                }}
                            </Button>
                            <span
                                v-if="scheduleDirty && !scheduleForm.processing"
                                class="text-sm text-warning-ink"
                                >Tienes cambios sin guardar.</span
                            >
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            Solo quien gestiona la disponibilidad puede editar
                            este horario.
                        </p>
                    </form>
                </SectionCard>

                <!-- Excepciones -->
                <SectionCard
                    title="Excepciones"
                    :icon="CalendarOff"
                    description="Bloqueos, vacaciones u horas extra que ajustan la semana."
                >
                    <template v-if="can.manage" #actions>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="openException(null)"
                            ><Plus class="size-4" /> Agregar</Button
                        >
                    </template>

                    <p
                        v-if="exceptions.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sin excepciones. Agrega un bloqueo cuando
                        {{ person.name }} no pueda atender, o un horario
                        adicional para abrir lugares extra.
                    </p>

                    <ul v-else class="grid gap-2">
                        <li
                            v-for="e in [
                                ...upcomingExceptions,
                                ...pastExceptions,
                            ]"
                            :key="e.id"
                            class="flex items-start gap-3 rounded-lg border p-3"
                            :class="e.isPast && 'opacity-60'"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        :tone="
                                            e.type.value === 'extra'
                                                ? 'success'
                                                : e.type.value === 'vacation'
                                                  ? 'info'
                                                  : 'warning'
                                        "
                                        :label="e.type.label"
                                        size="sm"
                                    />
                                    <span
                                        v-if="!e.kind"
                                        class="text-xs text-muted-foreground"
                                        >Todas las agendas</span
                                    >
                                    <span
                                        v-if="e.isPast"
                                        class="text-xs text-muted-foreground"
                                        >Pasada</span
                                    >
                                </div>
                                <p class="mt-1 text-sm tabular-nums">
                                    {{ exceptionRange(e) }}
                                </p>
                                <p
                                    v-if="e.reason"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ e.reason }}
                                </p>
                            </div>
                            <div v-if="can.manage" class="flex shrink-0 gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="`Editar ${e.type.label}`"
                                    @click="openException(e)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="`Eliminar ${e.type.label}`"
                                    @click="askDeleteException(e)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </li>
                    </ul>
                </SectionCard>
            </div>
        </template>
    </PageContainer>

    <FormSheet
        v-model:open="exceptionOpen"
        :title="editingException ? 'Editar excepción' : 'Nueva excepción'"
        :description="
            person
                ? `Agenda de ${person.name}. Las citas ya reservadas no se mueven solas: te avisaremos si hay alguna en el periodo.`
                : undefined
        "
    >
        <form
            id="exception-form"
            class="grid gap-5"
            @submit.prevent="submitException"
        >
            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">Tipo</legend>
                <RadioGroup v-model="exceptionForm.type" class="grid gap-2">
                    <label
                        v-for="t in exceptionTypes"
                        :key="t.value"
                        class="flex min-h-11 cursor-pointer items-start gap-3 rounded-md border p-3 has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary-soft/40"
                    >
                        <RadioGroupItem :value="t.value" class="mt-0.5" />
                        <span class="grid gap-0.5">
                            <span class="text-sm font-medium">{{
                                t.label
                            }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    t.value === 'extra'
                                        ? 'Abre horas fuera del horario semanal (mismo día).'
                                        : t.value === 'vacation'
                                          ? 'Días completos sin atender.'
                                          : 'Quita horas puntuales: juntas, trámites, traslados.'
                                }}
                            </span>
                        </span>
                    </label>
                </RadioGroup>
                <InputError :message="exceptionForm.errors.type" />
            </fieldset>

            <div class="grid gap-2">
                <Label>Aplica a</Label>
                <RadioGroup
                    v-model="exceptionForm.scope"
                    class="flex flex-wrap gap-4"
                >
                    <label class="flex items-center gap-2 text-sm"
                        ><RadioGroupItem value="kind" /> Solo
                        {{ kindLabel }}</label
                    >
                    <label class="flex items-center gap-2 text-sm"
                        ><RadioGroupItem value="all" /> Todas sus agendas</label
                    >
                </RadioGroup>
            </div>

            <div
                v-if="exceptionForm.type !== 'extra'"
                class="flex items-center gap-3"
            >
                <Switch id="all-day" v-model="exceptionForm.all_day" />
                <Label for="all-day" class="font-normal">Días completos</Label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="exc-start-date">{{
                        exceptionForm.type === 'extra' ? 'Fecha' : 'Desde'
                    }}</Label>
                    <DatePicker
                        id="exc-start-date"
                        v-model="exceptionForm.start_date"
                        :invalid="Boolean(excErrors.starts_at)"
                    />
                    <TimeSelect
                        v-if="!exceptionForm.all_day"
                        v-model="exceptionForm.start_time"
                        aria-label="Hora de inicio"
                        from="00:00"
                        to="23:30"
                    />
                    <InputError :message="excErrors.starts_at" />
                </div>
                <div class="grid gap-2">
                    <Label for="exc-end-date">{{
                        exceptionForm.type === 'extra' ? 'Hasta' : 'Hasta'
                    }}</Label>
                    <DatePicker
                        v-if="exceptionForm.type !== 'extra'"
                        id="exc-end-date"
                        v-model="exceptionForm.end_date"
                        :min="exceptionForm.start_date ?? undefined"
                        :invalid="Boolean(excErrors.ends_at)"
                    />
                    <TimeSelect
                        v-if="!exceptionForm.all_day"
                        v-model="exceptionForm.end_time"
                        aria-label="Hora de fin"
                        from="00:30"
                        to="23:59"
                    />
                    <InputError :message="excErrors.ends_at" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="exc-reason"
                    >Motivo
                    <span class="font-normal text-muted-foreground"
                        >(opcional, solo interno)</span
                    ></Label
                >
                <Input
                    id="exc-reason"
                    v-model="exceptionForm.reason"
                    maxlength="200"
                    placeholder="Ej. Capacitación de proveedores"
                />
                <InputError :message="exceptionForm.errors.reason" />
            </div>
        </form>
        <template #footer>
            <Button
                variant="outline"
                type="button"
                @click="exceptionOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="exception-form"
                :disabled="exceptionForm.processing"
            >
                <Spinner v-if="exceptionForm.processing" />
                {{
                    exceptionForm.processing
                        ? 'Guardando…'
                        : 'Guardar excepción'
                }}
            </Button>
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="¿Eliminar esta excepción?"
        description="La agenda vuelve a su horario semanal en ese periodo."
        confirm-label="Eliminar"
        loading-label="Eliminando…"
        tone="destructive"
        :confirm="confirmDeleteException"
    />
</template>
