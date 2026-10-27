<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarClock,
    CalendarPlus,
    ClipboardList,
    Copy,
    FolderPlus,
    Home,
    ImageIcon,
    Mail,
    MapPin,
    MessageCircle,
    Phone,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConsultationSlotPicker from '@/components/crm/ConsultationSlotPicker.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import KeyValue from '@/components/ds/KeyValue.vue';
import type { KeyValueItem } from '@/components/ds/KeyValue.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import Timeline from '@/components/ds/Timeline.vue';
import type { TimelineEvent } from '@/components/ds/Timeline.vue';
import type { Tone } from '@/components/ds/tones';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { Textarea } from '@/components/ui/textarea';
import { calendarDateToIso, todayDate } from '@/lib/dates';
import { TIME_ZONE } from '@/lib/format';
import { cn } from '@/lib/utils';

type Option = { value: string; label: string; tone?: string };
type NumOption = { value: number; label: string };

type Consultation = {
    uuid: string;
    folio: string;
    startsAt: string;
    status: Option;
    channel: Option;
    outcome: Option | null;
    advisor: { id: number; name: string } | null;
    isActive: boolean;
};

const props = defineProps<{
    lead: {
        uuid: string;
        folio: string;
        status: Option;
        source: Option;
        customer: {
            uuid: string;
            name: string;
            firstName: string;
            email: string | null;
            phone: string | null;
            preferredContact: string | null;
        };
        assignee: { id: number; name: string } | null;
        postalCode: string | null;
        city: string | null;
        state: string | null;
        covered: boolean;
        budget: Option | null;
        paymentPreference: Option | null;
        timeframe: Option | null;
        nextFollowUpAt: string | null;
        firstContactedAt: string | null;
        createdAt: string | null;
        categories: string[];
        measurementsNote: string | null;
        comments: string | null;
        lostReason: Option | null;
        lostNotes: string | null;
        closedAt: string | null;
        coverage: { zone: string | null; acceptsVisits: boolean };
        attribution: {
            source: Option;
            form: string | null;
            utmSource: string | null;
            utmMedium: string | null;
            utmCampaign: string | null;
            utmTerm: string | null;
            utmContent: string | null;
            referrer: string | null;
            landingUrl: string | null;
            deviceType: string | null;
        };
        customerNotes: string | null;
    };
    customerUrl: string | null;
    photos: {
        uuid: string;
        name: string;
        url: string;
        thumbUrl: string | null;
        isImage: boolean;
    }[];
    activities: {
        id: number;
        type: Option;
        from: Option | null;
        to: Option | null;
        body: string | null;
        actor: string | null;
        createdAt: string;
    }[];
    consultations: Consultation[];
    visits: {
        uuid: string;
        folio: string;
        startsAt: string | null;
        status: Option;
        url: string | null;
    }[];
    prequalification: {
        uuid: string;
        folio: string;
        status: Option;
        url: string;
    } | null;
    project: { folio: string; url: string | null } | null;
    options: {
        transitions: Option[];
        lostReasons: Option[];
        activityTypes: Option[];
        assignees: NumOption[];
        channels: { value: string; label: string; description: string }[];
        advisors: NumOption[];
    };
    consultationSettings: { durationMinutes: number };
    can: {
        update: boolean;
        assign: boolean;
        scheduleConsultation: boolean;
        startPrequalification: boolean;
        scheduleVisit: boolean;
        createProject: boolean;
    };
    links: {
        whatsapp: string | null;
        tel: string | null;
        mailto: string | null;
        scheduleVisit: string;
        createProject: string;
        customerBooking: string | null;
        customerVisit: string | null;
        availability: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Prospectos', href: '/admin/prospectos' },
            { title: 'Detalle', href: '' },
        ],
    },
});

const base = computed(() => `/admin/prospectos/${props.lead.uuid}`);
const contactLabels: Record<string, string> = {
    whatsapp: 'WhatsApp',
    phone: 'Llamada',
    email: 'Correo',
};
const tone = (o: { tone?: string } | null | undefined) =>
    (o?.tone ?? 'neutral') as Tone;

// ---------------------------------------------------------------- Bitácora

const events = computed<TimelineEvent[]>(() =>
    props.activities.map((a) => ({
        id: a.id,
        title:
            a.type.value === 'status_changed' && a.to
                ? a.from
                    ? `De «${a.from.label}» a «${a.to.label}»`
                    : `Etapa: ${a.to.label}`
                : a.type.label,
        description: a.body,
        date: a.createdAt,
        actor: a.actor ?? 'Sistema',
        tone: tone(a.type),
    })),
);

const noteForm = useForm({ type: 'note', body: '' });

function submitNote() {
    noteForm.post(`${base.value}/bitacora`, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset('body'),
    });
}

// ---------------------------------------------------------------- Etapa

const statusOpen = ref(false);
const statusForm = useForm({
    status: null as string | null,
    note: '',
    lost_reason: null as string | null,
    lost_notes: '',
});

function openStatus() {
    statusForm.reset();
    statusForm.clearErrors();
    statusOpen.value = true;
}

function submitStatus() {
    statusForm
        .transform((data) => ({
            ...data,
            lost_reason: data.status === 'lost' ? data.lost_reason : null,
            lost_notes: data.status === 'lost' ? data.lost_notes : null,
        }))
        .patch(`${base.value}/etapa`, {
            preserveScroll: true,
            onSuccess: () => (statusOpen.value = false),
        });
}

// ---------------------------------------------------------------- Responsable

const assignee = ref<number | null>(props.lead.assignee?.id ?? null);
const assigning = ref(false);

function saveAssignee(value: number | null) {
    assignee.value = value;
    router.patch(
        `${base.value}/responsable`,
        { assigned_to: value },
        {
            preserveScroll: true,
            onStart: () => (assigning.value = true),
            onFinish: () => (assigning.value = false),
            onError: () => (assignee.value = props.lead.assignee?.id ?? null),
        },
    );
}

// ---------------------------------------------------------------- Seguimiento

const timeFmt = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: TIME_ZONE,
});
const dateFmt = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: TIME_ZONE,
});

const followForm = useForm({
    date: props.lead.nextFollowUpAt
        ? dateFmt.format(new Date(props.lead.nextFollowUpAt))
        : (null as string | null),
    time: props.lead.nextFollowUpAt
        ? timeFmt.format(new Date(props.lead.nextFollowUpAt))
        : '10:00',
    note: '',
});

const timeOptions = Array.from({ length: 27 }, (_, i) => {
    const minutes = 7 * 60 + i * 30;
    const value = `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
    const label = new Intl.DateTimeFormat('es-MX', {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'UTC',
    }).format(new Date(`1970-01-01T${value}:00Z`));

    return { value, label };
});

const todayIso = calendarDateToIso(todayDate()) ?? '';
const isFollowOverdue = computed(
    () =>
        !!props.lead.nextFollowUpAt &&
        new Date(props.lead.nextFollowUpAt).getTime() < Date.now(),
);

function saveFollowUp() {
    followForm.patch(`${base.value}/seguimiento`, {
        preserveScroll: true,
        onSuccess: () => followForm.reset('note'),
    });
}

function clearFollowUp() {
    router.patch(
        `${base.value}/seguimiento`,
        { date: null, time: null, note: null },
        {
            preserveScroll: true,
            onSuccess: () => {
                followForm.date = null;
            },
        },
    );
}

// ---------------------------------------------------------------- Datos

const requestItems = computed<KeyValueItem[]>(() => [
    {
        key: 'categories',
        label: 'Quiere renovar',
        value: props.lead.categories.join(', ') || null,
        full: true,
    },
    { key: 'budget', label: 'Presupuesto', value: props.lead.budget?.label },
    {
        key: 'payment',
        label: 'Forma de pago',
        value: props.lead.paymentPreference?.label,
    },
    {
        key: 'timeframe',
        label: '¿Para cuándo?',
        value: props.lead.timeframe?.label,
    },
    {
        key: 'location',
        label: 'Ubicación',
        value:
            [
                props.lead.postalCode && `C.P. ${props.lead.postalCode}`,
                props.lead.city,
                props.lead.state,
            ]
                .filter(Boolean)
                .join(' · ') || null,
    },
    {
        key: 'coverage',
        label: 'Cobertura',
        value: props.lead.coverage.zone ?? 'Sin zona',
    },
    {
        key: 'measurements',
        label: 'Medidas orientativas',
        value: props.lead.measurementsNote,
        full: true,
    },
    {
        key: 'comments',
        label: 'Comentarios del cliente',
        value: props.lead.comments,
        full: true,
    },
]);

const attributionItems = computed<KeyValueItem[]>(() => {
    const a = props.lead.attribution;
    const devices: Record<string, string> = {
        mobile: 'Celular',
        tablet: 'Tableta',
        desktop: 'Computadora',
    };

    return [
        { key: 'source', label: 'Origen', value: a.source.label },
        { key: 'form', label: 'Formulario', value: a.form },
        { key: 'utmSource', label: 'utm_source', value: a.utmSource },
        { key: 'utmMedium', label: 'utm_medium', value: a.utmMedium },
        { key: 'utmCampaign', label: 'utm_campaign', value: a.utmCampaign },
        { key: 'utmTerm', label: 'utm_term', value: a.utmTerm },
        { key: 'utmContent', label: 'utm_content', value: a.utmContent },
        {
            key: 'device',
            label: 'Dispositivo',
            value: a.deviceType
                ? (devices[a.deviceType] ?? a.deviceType)
                : null,
        },
        {
            key: 'referrer',
            label: 'Llegó desde',
            value: a.referrer,
            full: true,
        },
        {
            key: 'landing',
            label: 'Página de llegada',
            value: a.landingUrl,
            full: true,
        },
    ];
});

// ---------------------------------------------------------------- Asesoría

const bookOpen = ref(false);
const AUTO = '__auto';
const advisorChoice = ref<string>(AUTO);
const bookDate = ref<string | null>(null);
const maxIso = computed(
    () => calendarDateToIso(todayDate().add({ days: 60 })) ?? todayIso,
);
const bookForm = useForm({
    starts_at: null as string | null,
    channel: props.options.channels[0]?.value ?? 'video',
    advisor_id: null as number | null,
    notes: '',
});
const advisorId = computed(() =>
    advisorChoice.value === AUTO ? null : Number(advisorChoice.value),
);

function openBooking() {
    bookForm.reset();
    bookForm.clearErrors();
    bookDate.value = null;
    advisorChoice.value = AUTO;
    bookOpen.value = true;
}

function submitBooking() {
    bookForm
        .transform((data) => ({ ...data, advisor_id: advisorId.value }))
        .post(`${base.value}/asesorias`, {
            preserveScroll: true,
            onSuccess: () => (bookOpen.value = false),
        });
}

// ---------------------------------------------------------------- Acciones

const sendLink = ref(true);
const prequalOpen = ref(false);
const projectOpen = ref(false);

function postAsPromise(
    url: string,
    data: Record<string, string | number | boolean | null> = {},
) {
    return new Promise<void>((resolve, reject) => {
        router.post(url, data, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) =>
                reject(
                    new Error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                ),
        });
    });
}

async function copy(text: string, label: string) {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(`${label} copiado`, {
            description: 'Pégalo en WhatsApp o en un correo para el cliente.',
        });
    } catch {
        toast.error('No se pudo copiar. Selecciona el enlace manualmente.');
    }
}

const isClosed = computed(() =>
    ['won', 'lost', 'cancelled'].includes(props.lead.status.value),
);
</script>

<template>
    <Head :title="`${lead.customer.name} · ${lead.folio}`" />

    <PageContainer>
        <PageHeader :title="lead.customer.name">
            <template #meta>
                <span class="tabular-nums">{{ lead.folio }}</span>
                <StatusBadge
                    :tone="tone(lead.status)"
                    :label="lead.status.label"
                />
                <span class="inline-flex items-center gap-1.5"
                    ><UserRound class="size-3.5" aria-hidden="true" />{{
                        lead.assignee?.name ?? 'Sin responsable'
                    }}</span
                >
                <span
                    >Recibido <DateText :value="lead.createdAt" relative
                /></span>
            </template>
            <template #actions>
                <Button v-if="links.tel" variant="outline" as-child>
                    <a :href="links.tel"><Phone class="size-4" /> Llamar</a>
                </Button>
                <Button v-if="links.whatsapp" variant="outline" as-child>
                    <a :href="links.whatsapp" target="_blank" rel="noopener"
                        ><MessageCircle class="size-4" /> WhatsApp</a
                    >
                </Button>
                <Button v-if="links.mailto" variant="outline" as-child>
                    <a :href="links.mailto"><Mail class="size-4" /> Correo</a>
                </Button>
            </template>
        </PageHeader>

        <p
            v-if="lead.status.value === 'lost' && lead.lostReason"
            class="rounded-lg border border-danger/25 bg-danger-soft px-4 py-3 text-sm text-danger-ink"
        >
            Perdido: <strong>{{ lead.lostReason.label }}</strong
            ><template v-if="lead.lostNotes"> — {{ lead.lostNotes }}</template>
        </p>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <!-- Columna principal -->
            <div class="flex min-w-0 flex-col gap-6">
                <SectionCard title="Datos de la solicitud">
                    <KeyValue
                        :items="requestItems"
                        :columns="2"
                        layout="stacked"
                    >
                        <template #value-coverage>
                            <span
                                class="inline-flex flex-wrap items-center gap-2"
                            >
                                <span>{{
                                    lead.coverage.zone ?? 'Sin zona operativa'
                                }}</span>
                                <StatusBadge
                                    size="sm"
                                    :tone="
                                        lead.coverage.acceptsVisits
                                            ? 'success'
                                            : lead.covered
                                              ? 'info'
                                              : 'warning'
                                    "
                                    :label="
                                        lead.coverage.acceptsVisits
                                            ? 'Acepta visitas'
                                            : lead.covered
                                              ? 'Zona sin visitas por ahora'
                                              : 'Fuera de cobertura'
                                    "
                                />
                            </span>
                        </template>
                    </KeyValue>
                    <div
                        v-if="lead.customerNotes"
                        class="mt-4 rounded-lg bg-muted/60 p-3 text-sm"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            Notas en la ficha del cliente
                        </p>
                        <p class="mt-1 whitespace-pre-line">
                            {{ lead.customerNotes }}
                        </p>
                    </div>
                </SectionCard>

                <SectionCard
                    v-if="photos.length"
                    title="Fotos del espacio"
                    :icon="ImageIcon"
                    description="Privadas: solo las ve el equipo."
                >
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <li v-for="(photo, i) in photos" :key="photo.uuid">
                            <a
                                :href="photo.url"
                                target="_blank"
                                rel="noopener"
                                class="group block overflow-hidden rounded-lg border bg-muted focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none"
                            >
                                <img
                                    :src="photo.thumbUrl ?? photo.url"
                                    :alt="`Foto ${i + 1} del espacio enviada por ${lead.customer.firstName}`"
                                    loading="lazy"
                                    class="aspect-[4/3] w-full object-cover transition-transform duration-200 group-hover:scale-[1.02]"
                                    @error="
                                        (e) =>
                                            ((
                                                e.target as HTMLImageElement
                                            ).src = photo.url)
                                    "
                                />
                                <span class="sr-only"
                                    >Abrir en tamaño completo</span
                                >
                            </a>
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard title="Asesorías" :icon="CalendarClock">
                    <template
                        v-if="can.scheduleConsultation && !isClosed"
                        #actions
                    >
                        <Button size="sm" variant="outline" @click="openBooking"
                            ><CalendarPlus class="size-4" /> Programar</Button
                        >
                    </template>
                    <ul v-if="consultations.length" class="divide-y">
                        <li v-for="c in consultations" :key="c.uuid">
                            <Link
                                :href="`/admin/asesorias/${c.uuid}`"
                                class="flex items-center justify-between gap-3 py-3 hover:text-primary"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="font-medium first-letter:uppercase"
                                    >
                                        <DateText
                                            :value="c.startsAt"
                                            format="full"
                                        />
                                        ·
                                        <DateText
                                            :value="c.startsAt"
                                            format="time"
                                        />
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ c.channel.label }} ·
                                        {{ c.advisor?.name ?? 'Sin asesor' }} ·
                                        {{ c.folio }}
                                    </p>
                                </div>
                                <StatusBadge
                                    :tone="tone(c.status)"
                                    :label="c.outcome?.label ?? c.status.label"
                                    size="sm"
                                />
                            </Link>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        compact
                        :bordered="false"
                        title="Sin asesorías"
                        description="Cuando agendes una asesoría remota aparecerá aquí."
                    />
                </SectionCard>

                <SectionCard
                    v-if="visits.length"
                    title="Visitas técnicas"
                    :icon="Home"
                >
                    <ul class="divide-y">
                        <li
                            v-for="v in visits"
                            :key="v.uuid"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div>
                                <component
                                    :is="v.url ? Link : 'span'"
                                    :href="v.url ?? undefined"
                                    class="font-medium hover:text-primary"
                                    >{{ v.folio }}</component
                                >
                                <p class="text-xs text-muted-foreground">
                                    <DateText
                                        :value="v.startsAt"
                                        format="datetime"
                                    />
                                </p>
                            </div>
                            <StatusBadge
                                :tone="tone(v.status)"
                                :label="v.status.label"
                                size="sm"
                            />
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard title="Bitácora" :icon="ClipboardList">
                    <form
                        v-if="can.update"
                        class="mb-6 grid gap-3 rounded-lg border bg-muted/30 p-3"
                        @submit.prevent="submitNote"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <div class="grid gap-1.5 sm:w-44">
                                <Label for="note-type">Tipo</Label>
                                <Select v-model="noteForm.type">
                                    <SelectTrigger id="note-type" class="w-full"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="t in options.activityTypes"
                                            :key="t.value"
                                            :value="t.value"
                                            >{{ t.label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid flex-1 gap-1.5">
                                <Label for="note-body">¿Qué pasó?</Label>
                                <Textarea
                                    id="note-body"
                                    v-model="noteForm.body"
                                    rows="2"
                                    placeholder="Ej. Le llamé, quiere cotizar cocina en L de 3.2 m"
                                    :aria-invalid="!!noteForm.errors.body"
                                />
                                <InputError :message="noteForm.errors.body" />
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    noteForm.processing || !noteForm.body.trim()
                                "
                            >
                                <Spinner v-if="noteForm.processing" /> Registrar
                            </Button>
                        </div>
                    </form>
                    <Timeline
                        v-if="events.length"
                        :events="events"
                        relative-dates
                    />
                    <EmptyState
                        v-else
                        compact
                        :bordered="false"
                        title="Sin actividad todavía"
                    />
                </SectionCard>
            </div>

            <!-- Columna lateral -->
            <aside class="flex flex-col gap-6">
                <SectionCard title="Etapa">
                    <div class="flex items-center justify-between gap-3">
                        <StatusBadge
                            :tone="tone(lead.status)"
                            :label="lead.status.label"
                        />
                        <Button
                            v-if="can.update && options.transitions.length"
                            size="sm"
                            variant="outline"
                            @click="openStatus"
                            >Cambiar etapa</Button
                        >
                    </div>
                    <p
                        v-if="lead.firstContactedAt"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Primer contacto
                        <DateText
                            :value="lead.firstContactedAt"
                            format="datetime"
                        />
                    </p>
                    <p v-else class="mt-3 text-xs text-warning-ink">
                        Aún sin primer contacto.
                    </p>
                </SectionCard>

                <SectionCard title="Responsable">
                    <SearchSelect
                        v-if="can.assign"
                        :model-value="assignee"
                        :options="options.assignees"
                        placeholder="Sin responsable"
                        clearable
                        :disabled="assigning"
                        aria-label="Responsable del prospecto"
                        @update:model-value="
                            (v) => saveAssignee(v as number | null)
                        "
                    />
                    <p v-else class="text-sm">
                        {{ lead.assignee?.name ?? 'Sin responsable' }}
                    </p>
                </SectionCard>

                <SectionCard title="Próximo seguimiento">
                    <p
                        v-if="lead.nextFollowUpAt"
                        :class="
                            cn(
                                'mb-3 text-sm font-medium',
                                isFollowOverdue && 'text-danger-ink',
                            )
                        "
                    >
                        <DateText
                            :value="lead.nextFollowUpAt"
                            format="datetime"
                        />
                        <span v-if="isFollowOverdue"> · vencido</span>
                    </p>
                    <form
                        v-if="can.update"
                        class="grid gap-3"
                        @submit.prevent="saveFollowUp"
                    >
                        <div class="grid grid-cols-[1fr_7.5rem] gap-2">
                            <DatePicker
                                v-model="followForm.date"
                                :min="todayIso"
                                placeholder="Fecha"
                                aria-label="Fecha de seguimiento"
                            />
                            <Select v-model="followForm.time">
                                <SelectTrigger class="w-full" aria-label="Hora"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="t in timeOptions"
                                        :key="t.value"
                                        :value="t.value"
                                        >{{ t.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <InputError :message="followForm.errors.date" />
                        <Textarea
                            v-model="followForm.note"
                            rows="2"
                            placeholder="Nota (opcional)"
                            aria-label="Nota del seguimiento"
                        />
                        <div class="flex justify-end gap-2">
                            <Button
                                v-if="lead.nextFollowUpAt"
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="clearFollowUp"
                                >Quitar</Button
                            >
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    !followForm.date || followForm.processing
                                "
                                >Guardar</Button
                            >
                        </div>
                    </form>
                </SectionCard>

                <SectionCard title="Acciones">
                    <div class="grid gap-2">
                        <Button
                            v-if="can.scheduleConsultation && !isClosed"
                            class="justify-start"
                            @click="openBooking"
                        >
                            <CalendarPlus class="size-4" /> Programar asesoría
                        </Button>

                        <div
                            v-if="prequalification"
                            class="flex items-center justify-between gap-2 rounded-lg border p-3"
                        >
                            <div class="min-w-0">
                                <p class="text-xs text-muted-foreground">
                                    Precalificación {{ prequalification.folio }}
                                </p>
                                <StatusBadge
                                    :tone="tone(prequalification.status)"
                                    :label="prequalification.status.label"
                                    size="sm"
                                    class="mt-1"
                                />
                            </div>
                            <Button size="sm" variant="ghost" as-child>
                                <Link
                                    :href="prequalification.url"
                                    aria-label="Abrir precalificación"
                                    ><ArrowRight class="size-4"
                                /></Link>
                            </Button>
                        </div>
                        <div
                            v-else-if="can.startPrequalification"
                            class="grid gap-2 rounded-lg border p-3"
                        >
                            <Button
                                variant="outline"
                                class="justify-start"
                                @click="prequalOpen = true"
                                >Iniciar precalificación</Button
                            >
                            <label
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <span>Enviar enlace al cliente por correo</span>
                                <Switch v-model="sendLink" />
                            </label>
                        </div>

                        <Button
                            v-if="can.scheduleVisit"
                            variant="outline"
                            class="justify-start"
                            as-child
                        >
                            <a :href="links.scheduleVisit"
                                ><Home class="size-4" /> Agendar visita</a
                            >
                        </Button>

                        <Button
                            v-if="can.createProject"
                            variant="outline"
                            class="justify-start"
                            @click="projectOpen = true"
                        >
                            <FolderPlus class="size-4" /> Crear proyecto
                        </Button>
                        <Button
                            v-if="project?.url"
                            variant="outline"
                            class="justify-start"
                            as-child
                        >
                            <Link :href="project.url"
                                ><FolderPlus class="size-4" /> Proyecto
                                {{ project.folio }}</Link
                            >
                        </Button>
                        <p
                            v-else-if="project"
                            class="text-sm text-muted-foreground"
                        >
                            Proyecto {{ project.folio }}
                        </p>

                        <Button
                            v-if="customerUrl"
                            variant="ghost"
                            class="justify-start"
                            as-child
                        >
                            <Link :href="customerUrl"
                                ><UserRound class="size-4" /> Ver ficha del
                                cliente</Link
                            >
                        </Button>
                    </div>

                    <div
                        v-if="links.customerBooking || links.customerVisit"
                        class="mt-4 grid gap-2 border-t pt-4"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            Enlaces para el cliente
                        </p>
                        <Button
                            v-if="links.customerBooking"
                            size="sm"
                            variant="ghost"
                            class="justify-start"
                            @click="
                                copy(
                                    links.customerBooking!,
                                    'Enlace de asesoría',
                                )
                            "
                        >
                            <Copy class="size-4" /> Copiar enlace para agendar
                            asesoría
                        </Button>
                        <Button
                            v-if="links.customerVisit"
                            size="sm"
                            variant="ghost"
                            class="justify-start"
                            @click="
                                copy(links.customerVisit!, 'Enlace de visita')
                            "
                        >
                            <Copy class="size-4" /> Copiar enlace para pagar
                            visita
                        </Button>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Atribución"
                    :description="lead.attribution.source.label"
                >
                    <Collapsible v-slot="{ open }">
                        <CollapsibleTrigger as-child>
                            <Button variant="ghost" size="sm" class="-ml-2">{{
                                open
                                    ? 'Ocultar detalle'
                                    : 'Ver UTM y página de llegada'
                            }}</Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent class="pt-3">
                            <KeyValue
                                :items="attributionItems"
                                layout="stacked"
                            >
                                <template #value-landing="{ item }">
                                    <span class="break-all">{{
                                        item.value ?? '—'
                                    }}</span>
                                </template>
                                <template #value-referrer="{ item }">
                                    <span class="break-all">{{
                                        item.value ?? '—'
                                    }}</span>
                                </template>
                            </KeyValue>
                        </CollapsibleContent>
                    </Collapsible>
                </SectionCard>

                <p
                    class="flex items-center gap-1.5 px-1 text-xs text-muted-foreground"
                >
                    <MapPin class="size-3.5" aria-hidden="true" /> Contacto
                    preferido:
                    {{
                        contactLabels[lead.customer.preferredContact ?? ''] ??
                        'sin indicar'
                    }}
                    <template v-if="lead.customer.phone">
                        · {{ lead.customer.phone }}</template
                    >
                </p>
            </aside>
        </div>
    </PageContainer>

    <!-- Cambiar etapa -->
    <FormSheet
        v-model:open="statusOpen"
        title="Cambiar etapa"
        :description="`Actual: ${lead.status.label}`"
    >
        <form
            id="status-form"
            class="grid gap-4"
            @submit.prevent="submitStatus"
        >
            <div class="grid gap-1.5">
                <Label for="st-status">Nueva etapa</Label>
                <Select v-model="statusForm.status">
                    <SelectTrigger
                        id="st-status"
                        class="w-full"
                        :aria-invalid="!!statusForm.errors.status"
                        ><SelectValue placeholder="Elige una etapa"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="t in options.transitions"
                            :key="t.value"
                            :value="t.value"
                            >{{ t.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="statusForm.errors.status" />
            </div>
            <template v-if="statusForm.status === 'lost'">
                <div class="grid gap-1.5">
                    <Label for="st-reason">Motivo</Label>
                    <Select v-model="statusForm.lost_reason">
                        <SelectTrigger
                            id="st-reason"
                            class="w-full"
                            :aria-invalid="!!statusForm.errors.lost_reason"
                            ><SelectValue placeholder="¿Por qué se perdió?"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="r in options.lostReasons"
                                :key="r.value"
                                :value="r.value"
                                >{{ r.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="statusForm.errors.lost_reason" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="st-lost-notes"
                        >Detalle del motivo
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Textarea
                        id="st-lost-notes"
                        v-model="statusForm.lost_notes"
                        rows="2"
                    />
                </div>
            </template>
            <div class="grid gap-1.5">
                <Label for="st-note"
                    >Nota
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    ></Label
                >
                <Textarea
                    id="st-note"
                    v-model="statusForm.note"
                    rows="3"
                    placeholder="Queda en la bitácora del prospecto."
                />
                <InputError :message="statusForm.errors.note" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="statusOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="status-form"
                :disabled="!statusForm.status || statusForm.processing"
            >
                <Spinner v-if="statusForm.processing" /> Guardar etapa
            </Button>
        </template>
    </FormSheet>

    <!-- Programar asesoría -->
    <FormSheet
        v-model:open="bookOpen"
        title="Programar asesoría"
        :description="`Asesoría remota de ${consultationSettings.durationMinutes} min con ${lead.customer.firstName}. Le enviamos la confirmación por correo.`"
        size="xl"
    >
        <form id="book-form" class="grid gap-5" @submit.prevent="submitBooking">
            <div class="grid gap-1.5">
                <Label for="bk-advisor">Asesor</Label>
                <Select v-model="advisorChoice">
                    <SelectTrigger id="bk-advisor" class="w-full sm:w-72"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="AUTO"
                            >Asignar automáticamente</SelectItem
                        >
                        <SelectItem
                            v-for="a in options.advisors"
                            :key="a.value"
                            :value="String(a.value)"
                            >{{ a.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="bookForm.errors.advisor_id" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">Medio</legend>
                <RadioGroup
                    v-model="bookForm.channel"
                    class="grid gap-2 sm:grid-cols-3"
                >
                    <label
                        v-for="ch in options.channels"
                        :key="ch.value"
                        :class="
                            cn(
                                'flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm transition-colors',
                                bookForm.channel === ch.value
                                    ? 'border-primary bg-primary-soft/50'
                                    : 'hover:bg-muted/50',
                            )
                        "
                    >
                        <RadioGroupItem :value="ch.value" class="mt-0.5" />
                        <span class="font-medium">{{ ch.label }}</span>
                    </label>
                </RadioGroup>
                <InputError :message="bookForm.errors.channel" />
            </fieldset>

            <div class="grid gap-1.5">
                <span class="text-sm font-medium">Fecha y hora</span>
                <ConsultationSlotPicker
                    v-if="bookOpen"
                    v-model:date="bookDate"
                    v-model:slot="bookForm.starts_at"
                    :availability-url="links.availability"
                    :min="todayIso"
                    :max="maxIso"
                    :advisor-id="advisorId"
                />
                <InputError :message="bookForm.errors.starts_at" />
            </div>

            <div class="grid gap-1.5">
                <Label for="bk-notes"
                    >Notas para el asesor
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    ></Label
                >
                <Textarea id="bk-notes" v-model="bookForm.notes" rows="2" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="bookOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="book-form"
                :disabled="!bookForm.starts_at || bookForm.processing"
            >
                <Spinner v-if="bookForm.processing" />
                {{ bookForm.processing ? 'Agendando…' : 'Agendar asesoría' }}
            </Button>
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="prequalOpen"
        title="¿Iniciar precalificación?"
        :description="
            sendLink
                ? `Crearemos el expediente y enviaremos a ${lead.customer.firstName} un enlace para compartir su información. No es una aprobación de crédito.`
                : 'Crearemos el expediente sin avisar al cliente. Podrás compartirle el enlace después.'
        "
        confirm-label="Iniciar"
        loading-label="Iniciando…"
        :confirm="
            () =>
                postAsPromise(`${base}/precalificacion`, {
                    send_link: sendLink,
                })
        "
    />

    <ConfirmDialog
        v-model:open="projectOpen"
        title="¿Crear proyecto?"
        :description="`Se abrirá un proyecto para ${lead.customer.name} a partir de esta solicitud.`"
        confirm-label="Crear proyecto"
        loading-label="Creando…"
        :confirm="() => postAsPromise(links.createProject)"
    />
</template>
