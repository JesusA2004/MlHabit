<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarClock,
    CalendarX,
    CheckCircle2,
    Copy,
    ExternalLink,
    Mail,
    MessageCircle,
    Phone,
    UserX,
    Video,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConsultationSlotPicker from '@/components/crm/ConsultationSlotPicker.vue';
import {
    ConfirmDialog,
    FormSheet,
    KeyValue,
    PageContainer,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import type { KeyValueItem, Tone } from '@/components/ds';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { Textarea } from '@/components/ui/textarea';
import { todayDate } from '@/lib/dates';
import { TIME_ZONE } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Option = { value: string; label: string; tone?: Tone };
type Ref = { uuid: string; folio: string } | null;

type Consultation = {
    uuid: string;
    folio: string;
    startsAt: string;
    endsAt: string;
    status: Option;
    channel: Option;
    outcome: Option | null;
    advisor: { id: number; name: string } | null;
    meetingUrl: string | null;
    isActive: boolean;
    customerNotes: string | null;
    internalNotes: string | null;
    outcomeNotes: string | null;
    cancelReason: string | null;
    completedAt: string | null;
    cancelledAt: string | null;
    rescheduledFrom: Ref;
    rescheduledTo: Ref;
    leadStatus: Option;
    customerPhone: string | null;
    lead: {
        uuid: string;
        folio: string;
        customer: {
            uuid: string;
            name: string;
            firstName: string;
            email: string | null;
            phone: string | null;
        };
    };
};

const props = defineProps<{
    consultation: Consultation;
    leadUrl: string | null;
    customerManageUrl: string;
    options: {
        outcomes: Option[];
        statuses: Option[];
        channels: { value: string; label: string; description: string }[];
        advisors: { value: number; label: string }[];
    };
    availabilityUrl: string;
    can: { update: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Asesorías', href: '/admin/asesorias' },
            { title: 'Detalle', href: '#' },
        ] as BreadcrumbItem[],
    },
});

const c = computed(() => props.consultation);
const base = computed(() => `/admin/asesorias/${c.value.uuid}`);

const dateFmt = new Intl.DateTimeFormat('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: TIME_ZONE,
});
const timeFmt = new Intl.DateTimeFormat('es-MX', {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: TIME_ZONE,
});
const longDate = computed(() => dateFmt.format(new Date(c.value.startsAt)));
const timeRange = computed(
    () =>
        `${timeFmt.format(new Date(c.value.startsAt))} – ${timeFmt.format(new Date(c.value.endsAt))}`,
);
const isFuture = computed(
    () => new Date(c.value.startsAt).getTime() > Date.now(),
);

const channelIcons: Record<string, Component> = {
    video: Video,
    phone: Phone,
    whatsapp: MessageCircle,
};

const phone = computed(
    () => c.value.lead.customer.phone ?? c.value.customerPhone,
);
const contactItems = computed<KeyValueItem[]>(() => [
    { key: 'name', label: 'Nombre', value: c.value.lead.customer.name },
    { key: 'phone', label: 'Teléfono', value: phone.value },
    { key: 'email', label: 'Correo', value: c.value.lead.customer.email },
    { key: 'lead', label: 'Prospecto', value: c.value.lead.folio },
]);

// ------------------------------------------------------------ Enlace y notas
const detailsForm = useForm({
    meeting_url: c.value.meetingUrl ?? '',
    internal_notes: c.value.internalNotes ?? '',
});

function saveDetails() {
    detailsForm
        .transform((data) => ({
            ...data,
            meeting_url: data.meeting_url || null,
        }))
        .patch(base.value, { preserveScroll: true });
}

// ------------------------------------------------------------ Estado
const statusBusy = ref<string | null>(null);
const noShowOpen = ref(false);

function setStatus(
    status: 'confirmed' | 'completed' | 'no_show',
): Promise<void> {
    statusBusy.value = status;

    return new Promise((resolve, reject) => {
        router.patch(
            `${base.value}/estado`,
            { status },
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (errors) =>
                    reject(
                        new Error(
                            Object.values(errors)[0] ??
                                'No se pudo actualizar el estado.',
                        ),
                    ),
                onFinish: () => (statusBusy.value = null),
            },
        );
    });
}

function setStatusSafe(status: 'confirmed' | 'completed') {
    setStatus(status).catch((e: Error) => toast.error(e.message));
}

// ------------------------------------------------------------ Resultado
const outcomeOpen = ref(false);
const outcomeForm = useForm({
    outcome: c.value.outcome?.value ?? '',
    notes: c.value.outcomeNotes ?? '',
});
const advancingOutcomes = ['interested_visit', 'prequalification'];

function submitOutcome() {
    outcomeForm.post(`${base.value}/resultado`, {
        preserveScroll: true,
        onSuccess: () => (outcomeOpen.value = false),
    });
}

// ------------------------------------------------------------ Reprogramar
const ANY = '__cualquiera__';
const rescheduleOpen = ref(false);
const rescheduleDate = ref<string | null>(null);
const rescheduleAdvisor = ref<string>(
    c.value.advisor ? String(c.value.advisor.id) : ANY,
);
const rescheduleForm = useForm({
    starts_at: null as string | null,
    channel: c.value.channel.value,
    advisor_id: null as number | null,
});

const advisorId = computed(() =>
    rescheduleAdvisor.value === ANY ? null : Number(rescheduleAdvisor.value),
);
watch(advisorId, (id) => (rescheduleForm.advisor_id = id), { immediate: true });

const minDate = todayDate().toString();
const maxDate = todayDate().add({ days: 60 }).toString();

function openReschedule() {
    rescheduleForm.clearErrors();
    rescheduleForm.starts_at = null;
    rescheduleDate.value = null;
    rescheduleOpen.value = true;
}

function submitReschedule() {
    rescheduleForm.post(`${base.value}/reprogramar`, {
        preserveScroll: true,
        onSuccess: () => (rescheduleOpen.value = false),
    });
}

// ------------------------------------------------------------ Cancelar
const cancelOpen = ref(false);
const cancelForm = useForm({ reason: '' });

function submitCancel() {
    cancelForm.post(`${base.value}/cancelar`, {
        preserveScroll: true,
        onSuccess: () => {
            cancelOpen.value = false;
            cancelForm.reset();
        },
    });
}

// ------------------------------------------------------------ Enlace del cliente
async function copyManageLink() {
    try {
        await navigator.clipboard.writeText(props.customerManageUrl);
        toast.success('Enlace copiado', {
            description:
                'Compártelo con el cliente para que reprograme o cancele.',
        });
    } catch {
        toast.error(
            'No pudimos copiar el enlace. Selecciónalo y cópialo manualmente.',
        );
    }
}

const showActions = computed(() => props.can.update && c.value.isActive);
</script>

<template>
    <Head :title="`Asesoría ${consultation.folio}`" />

    <PageContainer>
        <PageHeader
            :title="`Asesoría con ${consultation.lead.customer.firstName}`"
        >
            <template #meta>
                <StatusBadge
                    :tone="consultation.status.tone ?? 'neutral'"
                    :label="consultation.status.label"
                />
                <span class="tabular-nums">{{ consultation.folio }}</span>
                <span aria-hidden="true">·</span>
                <span class="first-letter:uppercase">{{ longDate }}</span>
                <span class="tabular-nums">{{ timeRange }}</span>
            </template>
            <template v-if="showActions" #actions>
                <Button variant="outline" @click="openReschedule"
                    ><CalendarClock class="size-4" /> Reprogramar</Button
                >
                <Button variant="outline" @click="cancelOpen = true"
                    ><CalendarX class="size-4" /> Cancelar</Button
                >
                <Button @click="outcomeOpen = true"
                    ><CheckCircle2 class="size-4" /> Registrar resultado</Button
                >
            </template>
            <template
                v-else-if="
                    can.update &&
                    consultation.status.value !== 'cancelled' &&
                    consultation.status.value !== 'rescheduled'
                "
                #actions
            >
                <Button variant="outline" @click="outcomeOpen = true">
                    <CheckCircle2 class="size-4" />
                    {{
                        consultation.outcome
                            ? 'Editar resultado'
                            : 'Registrar resultado'
                    }}
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="consultation.rescheduledTo || consultation.rescheduledFrom"
            class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-xl border bg-muted/50 px-4 py-3 text-sm"
        >
            <Link
                v-if="consultation.rescheduledTo"
                :href="`/admin/asesorias/${consultation.rescheduledTo.uuid}`"
                class="inline-flex items-center gap-1.5 font-medium text-primary underline-offset-4 hover:underline"
            >
                Esta cita se movió. Ver la nueva ({{
                    consultation.rescheduledTo.folio
                }}) <ArrowRight class="size-4" />
            </Link>
            <Link
                v-if="consultation.rescheduledFrom"
                :href="`/admin/asesorias/${consultation.rescheduledFrom.uuid}`"
                class="text-muted-foreground underline-offset-4 hover:underline"
            >
                Reprogramada desde {{ consultation.rescheduledFrom.folio }}
            </Link>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="flex flex-col gap-6">
                <SectionCard title="Cita">
                    <dl class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Medio
                            </dt>
                            <dd
                                class="mt-1 inline-flex items-center gap-1.5 text-sm"
                            >
                                <component
                                    :is="
                                        channelIcons[
                                            consultation.channel.value
                                        ] ?? Video
                                    "
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                {{ consultation.channel.label }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Asesor
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ consultation.advisor?.name ?? 'Sin asesor' }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Etapa del prospecto
                            </dt>
                            <dd class="mt-1">
                                <StatusBadge
                                    :tone="
                                        consultation.leadStatus.tone ??
                                        'neutral'
                                    "
                                    :label="consultation.leadStatus.label"
                                    size="sm"
                                />
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="consultation.customerNotes"
                        class="mt-5 rounded-lg bg-muted/60 p-4"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            Lo que nos contó el cliente al agendar
                        </p>
                        <p class="mt-1 text-sm whitespace-pre-line">
                            {{ consultation.customerNotes }}
                        </p>
                    </div>

                    <div
                        v-if="consultation.cancelReason"
                        class="mt-5 rounded-lg bg-danger-soft p-4 text-sm text-danger-ink"
                    >
                        <p class="font-medium">Motivo de cancelación</p>
                        <p class="mt-1">{{ consultation.cancelReason }}</p>
                    </div>

                    <div
                        v-if="consultation.outcome"
                        class="mt-5 rounded-lg border p-4"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            Resultado
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ consultation.outcome.label }}
                        </p>
                        <p
                            v-if="consultation.outcomeNotes"
                            class="mt-1 text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {{ consultation.outcomeNotes }}
                        </p>
                    </div>

                    <div
                        v-if="showActions"
                        class="mt-5 flex flex-wrap gap-2 border-t pt-4"
                    >
                        <Button
                            v-if="
                                consultation.status.value === 'scheduled' &&
                                isFuture
                            "
                            variant="outline"
                            size="sm"
                            :disabled="statusBusy !== null"
                            @click="setStatusSafe('confirmed')"
                        >
                            <Spinner v-if="statusBusy === 'confirmed'" /> Marcar
                            confirmada
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="statusBusy !== null"
                            @click="setStatusSafe('completed')"
                        >
                            <Spinner v-if="statusBusy === 'completed'" /> Marcar
                            realizada
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            :disabled="statusBusy !== null"
                            @click="noShowOpen = true"
                        >
                            <UserX class="size-4" /> No se presentó
                        </Button>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Enlace de reunión y notas internas"
                    description="El enlace se incluye en el recordatorio que recibe el cliente."
                >
                    <form
                        v-if="can.update"
                        class="flex flex-col gap-4"
                        @submit.prevent="saveDetails"
                    >
                        <div class="grid gap-1.5">
                            <Label for="meeting-url"
                                >Enlace de la videollamada</Label
                            >
                            <Input
                                id="meeting-url"
                                v-model="detailsForm.meeting_url"
                                type="url"
                                inputmode="url"
                                placeholder="https://meet.google.com/…"
                                :aria-invalid="!!detailsForm.errors.meeting_url"
                                aria-describedby="meeting-url-error"
                            />
                            <InputError
                                id="meeting-url-error"
                                :message="detailsForm.errors.meeting_url"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="internal-notes">Notas internas</Label>
                            <Textarea
                                id="internal-notes"
                                v-model="detailsForm.internal_notes"
                                rows="4"
                                placeholder="Solo las ve el equipo de ML Habitat."
                            />
                            <InputError
                                :message="detailsForm.errors.internal_notes"
                            />
                        </div>
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="
                                    detailsForm.processing ||
                                    !detailsForm.isDirty
                                "
                            >
                                <Spinner v-if="detailsForm.processing" />
                                {{
                                    detailsForm.processing
                                        ? 'Guardando…'
                                        : 'Guardar cambios'
                                }}
                            </Button>
                        </div>
                    </form>
                    <div v-else class="flex flex-col gap-3 text-sm">
                        <a
                            v-if="consultation.meetingUrl"
                            :href="consultation.meetingUrl"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 text-primary underline-offset-4 hover:underline"
                        >
                            Abrir reunión <ExternalLink class="size-4" />
                        </a>
                        <p v-else class="text-muted-foreground">
                            Sin enlace de reunión.
                        </p>
                        <p
                            v-if="consultation.internalNotes"
                            class="whitespace-pre-line"
                        >
                            {{ consultation.internalNotes }}
                        </p>
                    </div>
                </SectionCard>
            </div>

            <aside class="flex flex-col gap-6">
                <SectionCard title="Cliente">
                    <KeyValue :items="contactItems" layout="stacked">
                        <template #value-phone="{ item }">
                            <span
                                v-if="item.value"
                                class="flex flex-wrap gap-x-3 gap-y-1"
                            >
                                <a
                                    :href="`tel:+52${item.value}`"
                                    class="inline-flex items-center gap-1 text-primary tabular-nums underline-offset-4 hover:underline"
                                >
                                    <Phone
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />{{ item.value }}
                                </a>
                                <a
                                    :href="`https://wa.me/52${item.value}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 text-primary underline-offset-4 hover:underline"
                                >
                                    <MessageCircle
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />WhatsApp
                                </a>
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </template>
                        <template #value-email="{ item }">
                            <a
                                v-if="item.value"
                                :href="`mailto:${item.value}`"
                                class="inline-flex items-center gap-1 break-all text-primary underline-offset-4 hover:underline"
                            >
                                <Mail
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />{{ item.value }}
                            </a>
                            <span v-else class="text-muted-foreground">—</span>
                        </template>
                        <template #value-lead="{ item }">
                            <Link
                                v-if="leadUrl"
                                :href="leadUrl"
                                class="text-primary tabular-nums underline-offset-4 hover:underline"
                                >{{ item.value }}</Link
                            >
                            <span v-else class="tabular-nums">{{
                                item.value
                            }}</span>
                        </template>
                    </KeyValue>
                </SectionCard>

                <SectionCard
                    title="Enlace del cliente para reprogramar"
                    description="El cliente puede mover o cancelar su cita desde este enlace, sin iniciar sesión."
                >
                    <Button
                        variant="outline"
                        class="w-full"
                        @click="copyManageLink"
                        ><Copy class="size-4" /> Copiar enlace</Button
                    >
                </SectionCard>
            </aside>
        </div>
    </PageContainer>

    <!-- No se presentó -->
    <ConfirmDialog
        v-model:open="noShowOpen"
        title="¿Marcar que no se presentó?"
        description="Queda en el historial del prospecto. Después puedes reprogramar con él una nueva asesoría."
        confirm-label="Sí, no se presentó"
        loading-label="Guardando…"
        :confirm="() => setStatus('no_show')"
    />

    <!-- Resultado -->
    <FormSheet
        v-model:open="outcomeOpen"
        title="Resultado de la asesoría"
        description="Registra cómo terminó la conversación. La asesoría queda como realizada."
    >
        <form
            id="outcome-form"
            class="flex flex-col gap-5"
            @submit.prevent="submitOutcome"
        >
            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">¿Cómo terminó?</legend>
                <RadioGroup
                    v-model="outcomeForm.outcome"
                    class="grid gap-2"
                    aria-label="Resultado"
                >
                    <Label
                        v-for="o in options.outcomes"
                        :key="o.value"
                        :for="`outcome-${o.value}`"
                        :class="
                            cn(
                                'flex cursor-pointer items-start gap-3 rounded-lg border p-3 font-normal transition-colors hover:bg-muted/50',
                                outcomeForm.outcome === o.value &&
                                    'border-primary bg-primary-soft/60',
                            )
                        "
                    >
                        <RadioGroupItem
                            :id="`outcome-${o.value}`"
                            :value="o.value"
                            class="mt-0.5"
                        />
                        <span class="flex flex-col gap-0.5">
                            <span class="text-sm font-medium">{{
                                o.label
                            }}</span>
                            <span
                                v-if="advancingOutcomes.includes(o.value)"
                                class="text-xs text-muted-foreground"
                            >
                                El prospecto avanza de etapa automáticamente.
                            </span>
                        </span>
                    </Label>
                </RadioGroup>
                <InputError :message="outcomeForm.errors.outcome" />
            </fieldset>
            <div class="grid gap-1.5">
                <Label for="outcome-notes">Notas (opcional)</Label>
                <Textarea
                    id="outcome-notes"
                    v-model="outcomeForm.notes"
                    rows="4"
                    placeholder="Qué le interesa, medidas, presupuesto, siguiente paso…"
                />
                <InputError :message="outcomeForm.errors.notes" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="outcomeOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="outcome-form"
                :disabled="outcomeForm.processing || !outcomeForm.outcome"
            >
                <Spinner v-if="outcomeForm.processing" />
                {{
                    outcomeForm.processing ? 'Guardando…' : 'Guardar resultado'
                }}
            </Button>
        </template>
    </FormSheet>

    <!-- Reprogramar -->
    <FormSheet
        v-model:open="rescheduleOpen"
        title="Reprogramar asesoría"
        description="Elige el nuevo horario. Avisamos al cliente por correo con la nueva fecha."
        size="xl"
    >
        <form
            id="reschedule-form"
            class="flex flex-col gap-5"
            @submit.prevent="submitReschedule"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label>Asesor</Label>
                    <Select v-model="rescheduleAdvisor">
                        <SelectTrigger class="w-full" aria-label="Asesor"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ANY"
                                >Cualquier asesor disponible</SelectItem
                            >
                            <SelectItem
                                v-for="a in options.advisors"
                                :key="a.value"
                                :value="String(a.value)"
                                >{{ a.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Filtra los horarios. Si el asesor actual está libre, se
                        conserva.
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label>Medio</Label>
                    <Select v-model="rescheduleForm.channel">
                        <SelectTrigger class="w-full" aria-label="Medio"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="ch in options.channels"
                                :key="ch.value"
                                :value="ch.value"
                                >{{ ch.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="rescheduleForm.errors.channel" />
                </div>
            </div>

            <ConsultationSlotPicker
                v-if="rescheduleOpen"
                v-model:date="rescheduleDate"
                v-model:slot="rescheduleForm.starts_at"
                :availability-url="availabilityUrl"
                :min="minDate"
                :max="maxDate"
                :advisor-id="advisorId"
                :exclude-slot="consultation.startsAt"
            />
            <InputError :message="rescheduleForm.errors.starts_at" />
        </form>
        <template #footer>
            <Button variant="outline" @click="rescheduleOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="reschedule-form"
                :disabled="
                    rescheduleForm.processing || !rescheduleForm.starts_at
                "
            >
                <Spinner v-if="rescheduleForm.processing" />
                {{
                    rescheduleForm.processing ? 'Reprogramando…' : 'Reprogramar'
                }}
            </Button>
        </template>
    </FormSheet>

    <!-- Cancelar -->
    <FormSheet
        v-model:open="cancelOpen"
        title="Cancelar asesoría"
        description="Liberamos el horario y avisamos al cliente por correo con un enlace para agendar otra fecha."
    >
        <form
            id="cancel-form"
            class="flex flex-col gap-4"
            @submit.prevent="submitCancel"
        >
            <div class="grid gap-1.5">
                <Label for="cancel-reason">Motivo</Label>
                <Textarea
                    id="cancel-reason"
                    v-model="cancelForm.reason"
                    rows="3"
                    maxlength="200"
                    required
                    placeholder="Ej. El cliente pidió posponer hasta diciembre"
                    aria-describedby="cancel-reason-error"
                />
                <InputError
                    id="cancel-reason-error"
                    :message="cancelForm.errors.reason"
                />
                <p class="text-xs text-muted-foreground">
                    Se guarda en el historial del prospecto.
                </p>
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="cancelOpen = false"
                >Volver</Button
            >
            <Button
                type="submit"
                form="cancel-form"
                variant="destructive"
                :disabled="cancelForm.processing || !cancelForm.reason.trim()"
            >
                <Spinner v-if="cancelForm.processing" />
                {{
                    cancelForm.processing ? 'Cancelando…' : 'Cancelar asesoría'
                }}
            </Button>
        </template>
    </FormSheet>
</template>
