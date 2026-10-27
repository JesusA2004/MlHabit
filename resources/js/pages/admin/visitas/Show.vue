<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarX2,
    CheckCheck,
    Copy,
    ExternalLink,
    FileText,
    HandCoins,
    MapPin,
    Send,
    ThumbsUp,
    TriangleAlert,
    UserRoundCog,
    UserRoundX,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateSlotPicker from '@/components/ds/DateSlotPicker.vue';
import type { TimeSlot } from '@/components/ds/DateSlotPicker.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import KeyValue from '@/components/ds/KeyValue.vue';
import type { KeyValueItem } from '@/components/ds/KeyValue.vue';
import Money from '@/components/ds/Money.vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatMoney } from '@/lib/format';
import { apiFetch } from '@/lib/http';

type Option = { value: string; label: string; tone?: string };

type Visit = {
    uuid: string;
    folio: string;
    zone: string;
    technician: string | null;
    startsAt: string;
    endsAt: string;
    status: Option;
    fee: string;
    attention: string | null;
    holdExpiresAt: string | null;
    customer: { name: string; email: string | null; phone: string | null };
    address: {
        oneLine: string;
        street: string;
        exteriorNumber: string;
        interiorNumber: string | null;
        neighborhood: string;
        postalCode: string;
        municipality: string;
        state: string;
        references: string | null;
        mapUrl: string;
    };
    technicianId: number | null;
    accessNotes: string | null;
    customerNotes: string | null;
    internalNotes: string | null;
    paidAt: string | null;
    startedAt: string | null;
    completedAt: string | null;
    cancelledAt: string | null;
    cancelReason: string | null;
    attentionAt: string | null;
    isCreditable: boolean;
    creditAmount: string;
    policyVersion: number;
    durationMinutes: number;
    postalCode: string;
    createdBy: string | null;
    lead: { folio: string; url: string } | null;
    project: { url: string } | null;
};

type Payment = {
    uuid: string;
    folio: string;
    amount: string;
    refundedAmount: string;
    refundableAmount: string;
    status: Option;
    method: Option;
    provider: Option;
    cardBrand: string | null;
    cardLast4: string | null;
    authorization: string | null;
    reference: string | null;
    paidAt: string | null;
    errorMessage: string | null;
    adminUrl: string | null;
    receiptUrl: string | null;
    payUrl: string | null;
};

type Can = {
    manage: boolean;
    assign: boolean;
    reschedule: boolean;
    cancel: boolean;
    noShow: boolean;
    confirm: boolean;
    complete: boolean;
    refund: boolean;
    record: boolean;
    sendLink: boolean;
};

const props = defineProps<{
    visit: Visit;
    payment: Payment | null;
    credit: {
        amount: string;
        status: Option;
        expiresOn: string | null;
        voidReason: string | null;
    } | null;
    timeline: (Omit<TimelineEvent, 'tone'> & { tone: string })[];
    technicians: Option[];
    manualMethods: Option[];
    can: Can;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Visitas', href: '/admin/visitas' },
            { title: 'Detalle', href: '#' },
        ],
    },
});

const tone = (o: { tone?: string } | null | undefined) =>
    (o?.tone ?? 'neutral') as Tone;
const base = computed(() => `/admin/visitas/${props.visit.uuid}`);
const events = computed<TimelineEvent[]>(() =>
    props.timeline.map((e) => ({ ...e, tone: e.tone as Tone })),
);

const agenda = computed<KeyValueItem[]>(() => [
    {
        key: 'date',
        label: 'Fecha',
        value: formatDate(props.visit.startsAt, 'full'),
    },
    {
        key: 'time',
        label: 'Horario',
        value: `${formatDate(props.visit.startsAt, 'time')} – ${formatDate(props.visit.endsAt, 'time')}`,
    },
    {
        key: 'technician',
        label: 'Técnico',
        value: props.visit.technician ?? 'Sin asignar',
    },
    { key: 'zone', label: 'Zona', value: props.visit.zone },
    {
        key: 'duration',
        label: 'Duración',
        value: `${props.visit.durationMinutes} min`,
    },
    {
        key: 'policy',
        label: 'Política',
        value: `Versión ${props.visit.policyVersion}`,
    },
    { key: 'fee', label: 'Tarifa congelada', value: props.visit.fee },
    {
        key: 'createdBy',
        label: 'Apartada por',
        value: props.visit.createdBy ?? 'Cliente (sitio web)',
    },
]);

function post(url: string, data: Record<string, unknown> = {}): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(url, data as never, {
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

async function copy(text: string) {
    try {
        await navigator.clipboard.writeText(text);
        toast.success('Enlace copiado.');
    } catch {
        toast.error('No se pudo copiar el enlace.');
    }
}

// ---------------------------------------------------------------- Confirmaciones simples

const confirmOpen = ref(false);
const completeOpen = ref(false);
const noShowOpen = ref(false);
const noShowReason = ref('');
const sendingLink = ref(false);

function sendLink() {
    router.post(
        `${base.value}/enlace-pago`,
        {},
        {
            preserveScroll: true,
            onStart: () => (sendingLink.value = true),
            onFinish: () => (sendingLink.value = false),
        },
    );
}

// ---------------------------------------------------------------- Asignar técnico

const assignOpen = ref(false);
const assignForm = useForm({ technician_id: null as string | null });

function openAssign() {
    assignForm.clearErrors();
    assignForm.technician_id = props.visit.technicianId
        ? String(props.visit.technicianId)
        : null;
    assignOpen.value = true;
}

function submitAssign() {
    assignForm
        .transform((d) => ({
            technician_id: d.technician_id ? Number(d.technician_id) : null,
        }))
        .post(`${base.value}/tecnico`, {
            preserveScroll: true,
            onSuccess: () => (assignOpen.value = false),
        });
}

// ---------------------------------------------------------------- Reprogramar

const rescheduleOpen = ref(false);
const rescheduleForm = useForm({
    date: null as string | null,
    start: null as string | null,
    technician_id: null as string | null,
});
const availableDates = ref<string[]>([]);
const slots = ref<TimeSlot[]>([]);
const loadingDates = ref(false);
const loadingSlots = ref(false);
const availabilityError = ref<string | null>(null);

function availabilityUrl(extra: Record<string, string>): string {
    const params = new URLSearchParams({
        cp: props.visit.postalCode,
        visita: props.visit.uuid,
        ...extra,
    });

    return `/admin/visitas/disponibilidad?${params.toString()}`;
}

async function loadDates() {
    loadingDates.value = true;
    availabilityError.value = null;

    try {
        const res = await apiFetch(availabilityUrl({}));
        const json = (await res.json()) as {
            covered: boolean;
            message: string | null;
            dates: string[];
        };
        availableDates.value = json.dates ?? [];
        availabilityError.value = json.covered ? null : json.message;
    } catch {
        availabilityError.value = 'No se pudo cargar la disponibilidad.';
    } finally {
        loadingDates.value = false;
    }
}

async function loadSlots() {
    slots.value = [];
    rescheduleForm.start = null;

    if (!rescheduleForm.date) {
        return;
    }

    loadingSlots.value = true;

    try {
        const extra: Record<string, string> = { fecha: rescheduleForm.date };
        if (rescheduleForm.technician_id) {
            extra.tecnico = rescheduleForm.technician_id;
        }
        const res = await apiFetch(availabilityUrl(extra));
        const json = (await res.json()) as {
            slots: { start: string; end: string; technicians: number }[];
        };
        slots.value = (json.slots ?? []).map((s) => ({
            value: s.start,
            label: `${s.start} – ${s.end}`,
            hint:
                s.technicians > 1
                    ? `${s.technicians} técnicos libres`
                    : undefined,
        }));
    } catch {
        availabilityError.value = 'No se pudieron cargar los horarios.';
    } finally {
        loadingSlots.value = false;
    }
}

watch(() => [rescheduleForm.date, rescheduleForm.technician_id], loadSlots);

function openReschedule() {
    rescheduleForm.reset();
    rescheduleForm.clearErrors();
    slots.value = [];
    rescheduleOpen.value = true;
    void loadDates();
}

const isDateDisabled = (iso: string) => !availableDates.value.includes(iso);

function submitReschedule() {
    rescheduleForm
        .transform((d) => ({
            date: d.date,
            start: d.start,
            technician_id: d.technician_id ? Number(d.technician_id) : null,
        }))
        .post(`${base.value}/reprogramar`, {
            preserveScroll: true,
            onSuccess: () => (rescheduleOpen.value = false),
        });
}

// ---------------------------------------------------------------- Cancelar

const cancelOpen = ref(false);
const cancelConfirm = ref(false);
const cancelForm = useForm({
    reason: '',
    refund: false,
    refund_amount: '',
    idempotency_key: '',
});

function openCancel() {
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelForm.refund_amount = props.payment?.refundableAmount ?? '';
    cancelForm.idempotency_key = crypto.randomUUID();
    cancelOpen.value = true;
}

function submitCancel(): Promise<void> {
    return new Promise((resolve, reject) => {
        cancelForm
            .transform((d) => ({
                reason: d.reason,
                refund: d.refund,
                refund_amount: d.refund ? d.refund_amount : null,
                idempotency_key: d.refund ? d.idempotency_key : null,
            }))
            .post(`${base.value}/cancelar`, {
                preserveScroll: true,
                onSuccess: () => {
                    cancelOpen.value = false;
                    resolve();
                },
                onError: (errors) =>
                    reject(
                        new Error(
                            Object.values(errors)[0] ?? 'No se pudo cancelar.',
                        ),
                    ),
            });
    });
}

// ---------------------------------------------------------------- Pago manual

const manualOpen = ref(false);
const manualForm = useForm({
    method: (props.manualMethods[0]?.value ?? 'transfer') as string,
    reference: '',
});

function submitManual() {
    manualForm.post(`${base.value}/pago-manual`, {
        preserveScroll: true,
        onSuccess: () => (manualOpen.value = false),
    });
}
</script>

<template>
    <Head :title="`Visita ${visit.folio}`" />

    <PageContainer>
        <PageHeader
            :title="visit.customer.name"
            :description="`Visita ${visit.folio}`"
        >
            <template #meta>
                <StatusBadge
                    :tone="tone(visit.status)"
                    :label="visit.status.label"
                />
            </template>
            <template #actions>
                <Button
                    v-if="can.confirm"
                    variant="outline"
                    @click="confirmOpen = true"
                    ><ThumbsUp class="size-4" /> Confirmar</Button
                >
                <Button
                    v-if="can.reschedule"
                    variant="outline"
                    @click="openReschedule"
                    ><CalendarClock class="size-4" /> Reprogramar</Button
                >
                <Button v-if="can.assign" variant="outline" @click="openAssign"
                    ><UserRoundCog class="size-4" /> Técnico</Button
                >
                <Button v-if="can.complete" @click="completeOpen = true"
                    ><CheckCheck class="size-4" /> Realizada</Button
                >
                <Button
                    v-if="can.noShow"
                    variant="outline"
                    @click="noShowOpen = true"
                    ><UserRoundX class="size-4" /> Cliente ausente</Button
                >
                <Button
                    v-if="can.cancel"
                    variant="outline"
                    class="text-danger-ink hover:bg-danger-soft hover:text-danger-ink"
                    @click="openCancel"
                    ><CalendarX2 class="size-4" /> Cancelar</Button
                >
            </template>
        </PageHeader>

        <div
            v-if="visit.attention"
            role="alert"
            class="flex gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-warning-ink"
        >
            <TriangleAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div class="text-sm">
                <p class="font-medium">Requiere atención</p>
                <p>{{ visit.attention }}</p>
                <p v-if="visit.attentionAt" class="mt-1 text-xs opacity-80">
                    <DateText :value="visit.attentionAt" relative />
                </p>
            </div>
        </div>
        <FormStatus
            v-if="visit.cancelReason"
            tone="info"
            :message="`Cancelada: ${visit.cancelReason}`"
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                <SectionCard title="Agenda">
                    <KeyValue :items="agenda" :columns="2">
                        <template #value-date
                            ><span class="first-letter:uppercase">{{
                                formatDate(visit.startsAt, 'full')
                            }}</span></template
                        >
                        <template #value-fee
                            ><Money :value="visit.fee"
                        /></template>
                    </KeyValue>
                    <p
                        v-if="visit.holdExpiresAt"
                        class="mt-4 text-sm text-muted-foreground"
                    >
                        Horario apartado sin pagar hasta
                        <DateText
                            :value="visit.holdExpiresAt"
                            format="datetime"
                        />.
                    </p>
                </SectionCard>

                <SectionCard title="Domicilio" :icon="MapPin">
                    <p class="font-medium">{{ visit.address.oneLine }}</p>
                    <p
                        v-if="visit.address.references"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Referencias: {{ visit.address.references }}
                    </p>
                    <p
                        v-if="visit.accessNotes"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Acceso: {{ visit.accessNotes }}
                    </p>
                    <a
                        :href="visit.address.mapUrl"
                        target="_blank"
                        rel="noopener"
                        class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium underline-offset-4 hover:underline"
                    >
                        Abrir en Google Maps
                        <ExternalLink class="size-3.5" aria-hidden="true" />
                    </a>
                </SectionCard>

                <SectionCard
                    v-if="visit.customerNotes || visit.internalNotes"
                    title="Notas"
                >
                    <p v-if="visit.customerNotes" class="text-sm">
                        <span class="font-medium">Del cliente:</span>
                        {{ visit.customerNotes }}
                    </p>
                    <p
                        v-if="visit.internalNotes"
                        class="mt-2 text-sm whitespace-pre-line"
                    >
                        <span class="font-medium">Internas:</span>
                        {{ visit.internalNotes }}
                    </p>
                </SectionCard>

                <SectionCard title="Historial">
                    <Timeline v-if="events.length" :events="events" />
                    <EmptyState
                        v-else
                        compact
                        :bordered="false"
                        title="Sin movimientos registrados"
                    />
                </SectionCard>
            </div>

            <aside class="space-y-6">
                <SectionCard title="Cliente">
                    <p class="font-medium">{{ visit.customer.name }}</p>
                    <a
                        v-if="visit.customer.phone"
                        :href="`tel:${visit.customer.phone}`"
                        class="block text-sm text-muted-foreground hover:underline"
                        >{{ visit.customer.phone }}</a
                    >
                    <a
                        v-if="visit.customer.email"
                        :href="`mailto:${visit.customer.email}`"
                        class="block text-sm text-muted-foreground hover:underline"
                        >{{ visit.customer.email }}</a
                    >
                    <ul
                        v-if="visit.lead || visit.project"
                        class="mt-3 space-y-1 text-sm"
                    >
                        <li v-if="visit.lead">
                            <a
                                :href="visit.lead.url"
                                class="underline-offset-4 hover:underline"
                                >Prospecto {{ visit.lead.folio }}</a
                            >
                        </li>
                        <li v-if="visit.project">
                            <a
                                :href="visit.project.url"
                                class="underline-offset-4 hover:underline"
                                >Ver proyecto y medición</a
                            >
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard title="Pago">
                    <template v-if="payment">
                        <div class="flex items-center justify-between gap-2">
                            <Money
                                :value="payment.amount"
                                class="font-display text-xl font-semibold"
                            />
                            <StatusBadge
                                :tone="tone(payment.status)"
                                :label="payment.status.label"
                                size="sm"
                            />
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ payment.method.label
                            }}<template v-if="payment.cardLast4">
                                · {{ payment.cardBrand }} ····
                                {{ payment.cardLast4 }}</template
                            >
                            <template v-if="payment.reference">
                                · {{ payment.reference }}</template
                            >
                        </p>
                        <p
                            v-if="payment.paidAt"
                            class="text-sm text-muted-foreground"
                        >
                            Pagado
                            <DateText
                                :value="payment.paidAt"
                                format="datetime"
                            />
                        </p>
                        <p
                            v-if="Number(payment.refundedAmount) > 0"
                            class="text-sm text-muted-foreground"
                        >
                            Reembolsado:
                            <Money :value="payment.refundedAmount" />
                        </p>
                        <p
                            v-if="
                                payment.errorMessage &&
                                payment.status.value === 'failed'
                            "
                            class="mt-2 text-sm text-danger-ink"
                        >
                            {{ payment.errorMessage }}
                        </p>

                        <div class="mt-4 flex flex-col gap-2">
                            <Button
                                v-if="payment.adminUrl"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link :href="payment.adminUrl"
                                    >Ver pago {{ payment.folio }}</Link
                                >
                            </Button>
                            <Button
                                v-if="payment.receiptUrl"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <a
                                    :href="payment.receiptUrl"
                                    target="_blank"
                                    rel="noopener"
                                    ><FileText class="size-4" /> Comprobante</a
                                >
                            </Button>
                            <Button
                                v-if="payment.payUrl"
                                variant="outline"
                                size="sm"
                                @click="copy(payment.payUrl)"
                                ><Copy class="size-4" /> Copiar enlace de
                                pago</Button
                            >
                            <Button
                                v-if="can.sendLink"
                                variant="outline"
                                size="sm"
                                :disabled="sendingLink"
                                @click="sendLink"
                            >
                                <Spinner v-if="sendingLink" /><Send
                                    v-else
                                    class="size-4"
                                />
                                Reenviar enlace de pago
                            </Button>
                            <Button
                                v-if="can.record"
                                size="sm"
                                @click="manualOpen = true"
                                ><HandCoins class="size-4" /> Registrar pago
                                manual</Button
                            >
                        </div>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        Visita sin cobro.
                    </p>
                </SectionCard>

                <SectionCard
                    v-if="credit || visit.isCreditable"
                    title="Crédito para el anticipo"
                >
                    <template v-if="credit">
                        <div class="flex items-center justify-between gap-2">
                            <Money
                                :value="credit.amount"
                                class="font-semibold"
                            />
                            <StatusBadge
                                :tone="tone(credit.status)"
                                :label="credit.status.label"
                                size="sm"
                            />
                        </div>
                        <p
                            v-if="credit.expiresOn"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Vigente hasta
                            <DateText :value="credit.expiresOn" format="long" />
                        </p>
                        <p
                            v-if="credit.voidReason"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ credit.voidReason }}
                        </p>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        Se generará un crédito de
                        {{ formatMoney(visit.creditAmount) }} al confirmarse el
                        pago.
                    </p>
                </SectionCard>
            </aside>
        </div>
    </PageContainer>

    <ConfirmDialog
        v-model:open="confirmOpen"
        title="¿Confirmar la visita?"
        description="Marca que el cliente confirmó fecha y horario."
        confirm-label="Confirmar"
        :confirm="() => post(`${base}/confirmar`)"
    />
    <ConfirmDialog
        v-model:open="completeOpen"
        title="¿Marcar como realizada?"
        description="La visita se registra como realizada y el prospecto avanza a «Visita realizada»."
        confirm-label="Marcar realizada"
        :confirm="() => post(`${base}/realizada`)"
    />
    <ConfirmDialog
        v-model:open="noShowOpen"
        title="¿El cliente no estuvo?"
        confirm-label="Marcar ausente"
        tone="destructive"
        :confirm="
            () => post(`${base}/ausente`, { reason: noShowReason || null })
        "
    >
        <div class="space-y-2 text-left">
            <p>
                La visita queda como «Cliente ausente». Puedes agregar una nota.
            </p>
            <Label for="noshow-reason" class="sr-only">Nota</Label>
            <Textarea
                id="noshow-reason"
                v-model="noShowReason"
                rows="2"
                maxlength="300"
                placeholder="Ej. nadie abrió tras 20 minutos de espera"
            />
        </div>
    </ConfirmDialog>

    <FormSheet
        v-model:open="assignOpen"
        title="Asignar técnico"
        description="Se re-aparta el mismo horario en la agenda del técnico elegido."
    >
        <form
            id="assign-form"
            class="space-y-2"
            novalidate
            @submit.prevent="submitAssign"
        >
            <Label for="assign-technician">Técnico</Label>
            <SearchSelect
                id="assign-technician"
                v-model="assignForm.technician_id"
                :options="technicians"
                placeholder="Elige un técnico"
            />
            <InputError :message="assignForm.errors.technician_id" />
        </form>
        <template #footer>
            <Button variant="outline" @click="assignOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="assign-form"
                :disabled="assignForm.processing || !assignForm.technician_id"
            >
                <Spinner v-if="assignForm.processing" /> Asignar
            </Button>
        </template>
    </FormSheet>

    <FormSheet
        v-model:open="rescheduleOpen"
        title="Reprogramar visita"
        description="El horario anterior se libera solo si el nuevo queda apartado."
        size="xl"
    >
        <form
            id="reschedule-form"
            class="space-y-5"
            novalidate
            @submit.prevent="submitReschedule"
        >
            <FormStatus
                v-if="availabilityError"
                tone="danger"
                :message="availabilityError"
            />
            <div class="space-y-2">
                <Label for="reschedule-technician">Técnico (opcional)</Label>
                <SearchSelect
                    id="reschedule-technician"
                    v-model="rescheduleForm.technician_id"
                    :options="technicians"
                    placeholder="El menos cargado"
                    clearable
                />
            </div>
            <DateSlotPicker
                v-model:date="rescheduleForm.date"
                v-model:slot="rescheduleForm.start"
                :slots="slots"
                :loading="loadingSlots || loadingDates"
                :is-date-disabled="isDateDisabled"
                :min="availableDates[0]"
                :max="availableDates[availableDates.length - 1]"
            />
            <InputError
                :message="
                    rescheduleForm.errors.start ?? rescheduleForm.errors.date
                "
            />
        </form>
        <template #footer>
            <Button variant="outline" @click="rescheduleOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="reschedule-form"
                :disabled="
                    rescheduleForm.processing ||
                    !rescheduleForm.date ||
                    !rescheduleForm.start
                "
            >
                <Spinner v-if="rescheduleForm.processing" /> Reprogramar
            </Button>
        </template>
    </FormSheet>

    <FormSheet
        v-model:open="cancelOpen"
        title="Cancelar visita"
        description="El horario se libera de inmediato."
    >
        <form
            id="cancel-form"
            class="space-y-5"
            novalidate
            @submit.prevent="cancelConfirm = true"
        >
            <div class="space-y-2">
                <Label for="cancel-reason">Motivo</Label>
                <Textarea
                    id="cancel-reason"
                    v-model="cancelForm.reason"
                    rows="3"
                    maxlength="200"
                    placeholder="Ej. el cliente pospuso su proyecto"
                />
                <InputError :message="cancelForm.errors.reason" />
            </div>
            <template v-if="can.refund && payment">
                <div class="flex items-start gap-3">
                    <Checkbox id="cancel-refund" v-model="cancelForm.refund" />
                    <Label for="cancel-refund" class="leading-snug"
                        >Reembolsar al cliente (según la política de
                        cancelación)</Label
                    >
                </div>
                <div v-if="cancelForm.refund" class="space-y-2">
                    <Label for="cancel-amount"
                        >Monto a reembolsar (máx.
                        {{ formatMoney(payment.refundableAmount) }})</Label
                    >
                    <Input
                        id="cancel-amount"
                        v-model="cancelForm.refund_amount"
                        inputmode="decimal"
                    />
                    <InputError :message="cancelForm.errors.refund_amount" />
                </div>
            </template>
        </form>
        <template #footer>
            <Button variant="outline" @click="cancelOpen = false"
                >Volver</Button
            >
            <Button
                type="submit"
                form="cancel-form"
                variant="destructive"
                :disabled="cancelForm.reason.length < 5"
                >Continuar</Button
            >
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="cancelConfirm"
        title="¿Cancelar esta visita?"
        :description="
            cancelForm.refund
                ? `Se liberará el horario y se reembolsarán ${formatMoney(cancelForm.refund_amount) ?? ''} al cliente.`
                : 'Se liberará el horario. No se hará ningún reembolso.'
        "
        confirm-label="Cancelar visita"
        cancel-label="Volver"
        loading-label="Cancelando…"
        tone="destructive"
        :confirm="submitCancel"
    />

    <FormSheet
        v-model:open="manualOpen"
        title="Registrar pago manual"
        description="Transferencia o efectivo. La visita quedará agendada al registrarlo."
    >
        <form
            id="visit-manual-form"
            class="space-y-5"
            novalidate
            @submit.prevent="submitManual"
        >
            <div class="space-y-2">
                <Label for="visit-manual-method">Método</Label>
                <Select v-model="manualForm.method">
                    <SelectTrigger id="visit-manual-method" class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="m in manualMethods"
                            :key="m.value"
                            :value="m.value"
                            >{{ m.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-2">
                <Label for="visit-manual-reference">Referencia</Label>
                <Input
                    id="visit-manual-reference"
                    v-model="manualForm.reference"
                    maxlength="120"
                    placeholder="Ej. SPEI 0045821"
                />
                <InputError :message="manualForm.errors.reference" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="manualOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="visit-manual-form"
                :disabled="
                    manualForm.processing || manualForm.reference.length < 3
                "
            >
                <Spinner v-if="manualForm.processing" /> Registrar pago
            </Button>
        </template>
    </FormSheet>
</template>
