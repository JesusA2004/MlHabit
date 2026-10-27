<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Copy,
    ExternalLink,
    FileText,
    Lock,
    Send,
    ShieldAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    ConfirmDialog,
    EmptyState,
    FormSheet,
    KeyValue,
    Money,
    PageContainer,
    PageHeader,
    SectionCard,
    StatusBadge,
    Timeline,
} from '@/components/ds';
import type { KeyValueItem, TimelineEvent, Tone } from '@/components/ds';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatBytes } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';

type Option = { value: string; label: string; tone?: Tone };

type Prequalification = {
    uuid: string;
    folio: string;
    status: Option;
    customer: { uuid: string; name: string };
    lead: { uuid: string; folio: string };
    reviewer: { id: number; name: string } | null;
    submittedAt: string | null;
    reviewedAt: string | null;
    createdAt: string | null;
    requestedAmount: string | null;
    preferredTermMonths: number | null;
    incomeRange: Option | null;
    employmentType: Option | null;
    applicantNotes: string | null;
    decisionNotes: string | null;
    customerPhone: string | null;
    customerEmail: string | null;
};

type Doc = {
    uuid: string;
    name: string;
    mime: string;
    size: number;
    isImage: boolean;
    url: string;
    thumbUrl: string | null;
    createdAt: string | null;
};

type AuditEntry = {
    id: number;
    title: string;
    description: string | null;
    actor: string;
    date: string | null;
};

const props = defineProps<{
    prequalification: Prequalification;
    documents: Doc[] | null;
    documentsCount: number;
    audit: AuditEntry[];
    transitions: Option[];
    customerLink: string;
    leadUrl: string | null;
    can: { review: boolean; viewDocuments: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Precalificaciones', href: '/admin/precalificaciones' },
            { title: 'Expediente', href: '#' },
        ] as BreadcrumbItem[],
    },
});

const p = computed(() => props.prequalification);
const base = computed(() => `/admin/precalificaciones/${p.value.uuid}`);
const awaitingCustomer = computed(() =>
    ['draft', 'info_requested'].includes(p.value.status.value),
);

const items = computed<KeyValueItem[]>(() => [
    {
        key: 'amount',
        label: 'Monto que solicita',
        value: p.value.requestedAmount,
    },
    {
        key: 'term',
        label: 'Plazo preferido',
        value: p.value.preferredTermMonths
            ? `${p.value.preferredTermMonths} meses`
            : null,
    },
    {
        key: 'income',
        label: 'Ingreso mensual',
        value: p.value.incomeRange?.label,
    },
    {
        key: 'employment',
        label: 'Ocupación',
        value: p.value.employmentType?.label,
    },
    {
        key: 'notes',
        label: 'Comentarios del cliente',
        value: p.value.applicantNotes,
        full: true,
    },
    {
        key: 'decision',
        label: 'Notas de revisión',
        value: p.value.decisionNotes,
        full: true,
    },
]);

const contactItems = computed<KeyValueItem[]>(() => [
    { key: 'name', label: 'Cliente', value: p.value.customer.name },
    { key: 'phone', label: 'Teléfono', value: p.value.customerPhone },
    { key: 'email', label: 'Correo', value: p.value.customerEmail },
    { key: 'lead', label: 'Prospecto', value: p.value.lead.folio },
    {
        key: 'reviewer',
        label: 'Revisa',
        value: p.value.reviewer?.name ?? 'Sin asignar',
    },
]);

const events = computed<TimelineEvent[]>(() =>
    props.audit.map((a) => ({
        id: a.id,
        title: a.title,
        description: a.description,
        date: a.date,
        actor: a.actor,
    })),
);

// ------------------------------------------------------------ Decisiones
const NEEDS_NOTES = ['info_requested', 'prequalified', 'not_prequalified'];
const sheetOpen = ref(false);
const confirmOpen = ref(false);
const target = ref<Option | null>(null);
const form = useForm({ status: '', notes: '', notify: true });

const sheetCopy = computed(() => {
    switch (target.value?.value) {
        case 'info_requested':
            return {
                title: 'Pedir información al cliente',
                description:
                    'Le enviamos un correo con tu nota y un enlace para completar su solicitud.',
                label: 'Qué necesitas del cliente',
                placeholder:
                    'Ej. Comprobante de domicilio reciente y los dos últimos recibos de nómina.',
                submit: 'Pedir información',
            };
        case 'prequalified':
            return {
                title: 'Marcar como precalificado',
                description:
                    'El cliente verá “precalificado, sujeto a evaluación final”. No es una aprobación de crédito.',
                label: 'Notas de la decisión',
                placeholder:
                    'Fundamento de la decisión; queda en el expediente.',
                submit: 'Marcar precalificado',
            };
        default:
            return {
                title: 'Marcar como no precalificado',
                description:
                    'Le explicamos al cliente, con cuidado, que por ahora no es posible ofrecerle plazos.',
                label: 'Notas de la decisión',
                placeholder:
                    'Fundamento de la decisión; queda en el expediente.',
                submit: 'Marcar no precalificado',
            };
    }
});

function choose(option: Option) {
    target.value = option;
    form.clearErrors();
    form.status = option.value;
    form.notes =
        option.value === 'info_requested' ? '' : (p.value.decisionNotes ?? '');
    form.notify = true;

    if (NEEDS_NOTES.includes(option.value)) {
        sheetOpen.value = true;
    } else {
        confirmOpen.value = true;
    }
}

function submitSheet() {
    form.post(`${base.value}/estado`, {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    });
}

function confirmSimple(): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(
            `${base.value}/estado`,
            { status: target.value?.value, notify: true },
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (errors) =>
                    reject(
                        new Error(
                            Object.values(errors)[0] ??
                                'No se pudo cambiar el estado.',
                        ),
                    ),
            },
        );
    });
}

const confirmCopy = computed(() =>
    target.value?.value === 'in_review'
        ? {
              title: 'Tomar para revisión',
              description:
                  'Quedarás como responsable de revisar esta solicitud.',
              label: 'Tomar revisión',
          }
        : target.value?.value === 'withdrawn'
          ? {
                title: '¿Retirar la solicitud?',
                description:
                    'Se cierra el expediente. El cliente ya no podrá enviar información con su enlace.',
                label: 'Retirar',
            }
          : {
                title: `¿Cambiar a «${target.value?.label}»?`,
                description: 'El cambio queda registrado en la auditoría.',
                label: 'Confirmar',
            },
);

const buttonVariant = (value: string) =>
    value === 'prequalified'
        ? 'default'
        : value === 'not_prequalified' || value === 'withdrawn'
          ? 'outline'
          : 'secondary';

// ------------------------------------------------------------ Enlace del cliente
async function copyLink() {
    try {
        await navigator.clipboard.writeText(props.customerLink);
        toast.success('Enlace copiado', {
            description:
                'El cliente puede llenar o completar su solicitud desde ahí.',
        });
    } catch {
        toast.error('No pudimos copiar el enlace.');
    }
}

const resending = ref(false);
function resend() {
    router.post(
        `${base.value}/enlace`,
        {},
        {
            preserveScroll: true,
            onStart: () => (resending.value = true),
            onFinish: () => (resending.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`Precalificación ${prequalification.folio}`" />

    <PageContainer>
        <PageHeader :title="prequalification.customer.name">
            <template #meta>
                <StatusBadge
                    :tone="prequalification.status.tone ?? 'neutral'"
                    :label="prequalification.status.label"
                />
                <span class="tabular-nums">{{ prequalification.folio }}</span>
            </template>
            <template #actions>
                <Button v-if="leadUrl" variant="outline" as-child>
                    <Link :href="leadUrl"
                        >Ver prospecto {{ prequalification.lead.folio }}</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <div
            role="note"
            class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning-ink"
        >
            <ShieldAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <p>
                <strong class="font-semibold">Revisión manual.</strong>
                Nunca comuniques una precalificación como crédito aprobado:
                siempre es sujeta a evaluación final.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="flex flex-col gap-6">
                <SectionCard
                    title="Información del cliente"
                    description="Datos declarados por el cliente; no están verificados."
                >
                    <KeyValue
                        :items="items"
                        :columns="2"
                        layout="stacked"
                        fallback="Sin dato"
                    >
                        <template #value-amount="{ item }">
                            <Money
                                v-if="item.value"
                                :value="item.value"
                                class="font-medium"
                            />
                            <span v-else class="text-muted-foreground"
                                >Sin dato</span
                            >
                        </template>
                        <template #value-notes="{ item }">
                            <span class="whitespace-pre-line">{{
                                item.value || 'Sin comentarios'
                            }}</span>
                        </template>
                        <template #value-decision="{ item }">
                            <span class="whitespace-pre-line">{{
                                item.value || 'Aún sin notas'
                            }}</span>
                        </template>
                    </KeyValue>
                </SectionCard>

                <SectionCard
                    title="Documentos"
                    :description="
                        documents === null
                            ? undefined
                            : 'Archivos privados. Se abren en una pestaña nueva y quedan registrados.'
                    "
                >
                    <EmptyState
                        v-if="documents === null"
                        :icon="Lock"
                        compact
                        title="Documentos protegidos"
                        :description="`Necesitas permiso de datos sensibles para ver los documentos (${documentsCount}).`"
                    />
                    <EmptyState
                        v-else-if="documents.length === 0"
                        :icon="FileText"
                        compact
                        title="Sin documentos"
                        description="El cliente no ha adjuntado documentos. Puedes pedírselos con “Información solicitada”."
                    />
                    <ul v-else class="grid gap-2 sm:grid-cols-2">
                        <li v-for="doc in documents" :key="doc.uuid">
                            <a
                                :href="doc.url"
                                target="_blank"
                                rel="noopener"
                                class="group flex items-center gap-3 rounded-lg border p-2.5 transition-colors hover:border-primary/40 hover:bg-muted/40"
                            >
                                <img
                                    v-if="doc.isImage && doc.thumbUrl"
                                    :src="doc.thumbUrl"
                                    :alt="`Vista previa de ${doc.name}`"
                                    class="size-12 shrink-0 rounded-md bg-muted object-cover"
                                    loading="lazy"
                                    @error="
                                        (
                                            $event.target as HTMLImageElement
                                        ).src = doc.url
                                    "
                                />
                                <span
                                    v-else
                                    class="flex size-12 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground"
                                >
                                    <FileText
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span
                                        class="truncate text-sm font-medium group-hover:underline"
                                        >{{ doc.name }}</span
                                    >
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ formatBytes(doc.size) }}</span
                                    >
                                </span>
                                <ExternalLink
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span class="sr-only"
                                    >(se abre en una pestaña nueva)</span
                                >
                            </a>
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard title="Auditoría">
                    <Timeline v-if="events.length" :events="events" />
                    <p v-else class="text-sm text-muted-foreground">
                        Sin movimientos registrados.
                    </p>
                </SectionCard>
            </div>

            <aside class="flex flex-col gap-6">
                <SectionCard
                    v-if="can.review"
                    title="Decisión"
                    description="Avanza el expediente. Cada cambio queda auditado."
                >
                    <div v-if="transitions.length" class="flex flex-col gap-2">
                        <Button
                            v-for="t in transitions"
                            :key="t.value"
                            :variant="buttonVariant(t.value)"
                            class="w-full justify-start"
                            @click="choose(t)"
                        >
                            {{ t.label }}
                        </Button>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Este expediente está cerrado.
                    </p>
                </SectionCard>

                <SectionCard title="Contacto">
                    <KeyValue :items="contactItems" layout="stacked">
                        <template #value-phone="{ item }">
                            <a
                                v-if="item.value"
                                :href="`tel:+52${item.value}`"
                                class="text-primary tabular-nums underline-offset-4 hover:underline"
                                >{{ item.value }}</a
                            >
                            <span v-else class="text-muted-foreground">—</span>
                        </template>
                        <template #value-email="{ item }">
                            <a
                                v-if="item.value"
                                :href="`mailto:${item.value}`"
                                class="break-all text-primary underline-offset-4 hover:underline"
                                >{{ item.value }}</a
                            >
                            <span v-else class="text-muted-foreground">—</span>
                        </template>
                    </KeyValue>
                </SectionCard>

                <SectionCard
                    v-if="awaitingCustomer"
                    title="Enlace para el cliente"
                    description="Con este enlace el cliente llena o completa su información, sin iniciar sesión."
                >
                    <div class="flex flex-col gap-2">
                        <Button
                            variant="outline"
                            class="w-full"
                            @click="copyLink"
                            ><Copy class="size-4" /> Copiar enlace para el
                            cliente</Button
                        >
                        <Button
                            variant="ghost"
                            class="w-full"
                            :disabled="resending"
                            @click="resend"
                        >
                            <Spinner v-if="resending" /><Send
                                v-else
                                class="size-4"
                            />
                            {{
                                resending
                                    ? 'Enviando…'
                                    : 'Reenviar enlace por correo'
                            }}
                        </Button>
                    </div>
                </SectionCard>
            </aside>
        </div>
    </PageContainer>

    <FormSheet
        v-model:open="sheetOpen"
        :title="sheetCopy.title"
        :description="sheetCopy.description"
    >
        <form
            id="decision-form"
            class="flex flex-col gap-5"
            @submit.prevent="submitSheet"
        >
            <div class="grid gap-1.5">
                <Label for="decision-notes">{{ sheetCopy.label }}</Label>
                <Textarea
                    id="decision-notes"
                    v-model="form.notes"
                    rows="5"
                    required
                    :placeholder="sheetCopy.placeholder"
                    aria-describedby="decision-notes-error"
                />
                <InputError
                    id="decision-notes-error"
                    :message="form.errors.notes || form.errors.status"
                />
            </div>
            <label class="flex items-start gap-3 rounded-lg border p-3 text-sm">
                <Switch v-model="form.notify" class="mt-0.5" />
                <span class="flex flex-col">
                    <span class="font-medium"
                        >Avisar al cliente por correo</span
                    >
                    <span class="text-xs text-muted-foreground">
                        {{
                            target?.value === 'info_requested'
                                ? 'Incluye tu nota y el enlace para completar.'
                                : 'Mensaje prudente, sin prometer aprobación.'
                        }}
                    </span>
                </span>
            </label>
        </form>
        <template #footer>
            <Button variant="outline" @click="sheetOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="decision-form"
                :disabled="form.processing || !form.notes.trim()"
            >
                <Spinner v-if="form.processing" />
                {{ form.processing ? 'Guardando…' : sheetCopy.submit }}
            </Button>
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="confirmOpen"
        :title="confirmCopy.title"
        :description="confirmCopy.description"
        :confirm-label="confirmCopy.label"
        :tone="target?.value === 'withdrawn' ? 'destructive' : 'default'"
        loading-label="Guardando…"
        :confirm="confirmSimple"
    />
</template>
