<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ExternalLink,
    FileText,
    HandCoins,
    RefreshCw,
    Undo2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import KeyValue from '@/components/ds/KeyValue.vue';
import type { KeyValueItem } from '@/components/ds/KeyValue.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import Timeline from '@/components/ds/Timeline.vue';
import type { TimelineEvent } from '@/components/ds/Timeline.vue';
import type { Tone } from '@/components/ds/tones';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { formatMoney } from '@/lib/format';

type Option = { value: string; label: string; tone: string };

type Payment = {
    uuid: string;
    folio: string;
    orderId: string;
    concept: Option;
    description: string;
    method: Option;
    provider: Option;
    amount: string;
    refundedAmount: string;
    refundableAmount: string;
    currency: string;
    status: Option;
    cardBrand: string | null;
    cardLast4: string | null;
    authorization: string | null;
    reference: string | null;
    paidAt: string | null;
    createdAt: string | null;
    errorMessage: string | null;
    customer: { name: string; email: string | null; phone: string | null };
    feeAmount: string | null;
    netAmount: string | null;
    transactionId: string | null;
    errorCode: string | null;
    failedAt: string | null;
    reconciledAt: string | null;
    recordedBy: string | null;
};

type Attempt = {
    id: number;
    number: number;
    status: Option;
    transactionId: string | null;
    httpStatus: number | null;
    errorCode: string | null;
    errorMessage: string | null;
    createdAt: string | null;
};

type RefundRow = {
    uuid: string;
    amount: string;
    reason: string;
    status: Option;
    errorMessage: string | null;
    requestedBy: string | null;
    processedAt: string | null;
    createdAt: string | null;
};

type Webhook = {
    id: number;
    type: string;
    status: Option;
    transactionId: string | null;
    error: string | null;
    attempts: number;
    receivedAt: string | null;
    processedAt: string | null;
};

const props = defineProps<{
    payment: Payment;
    attempts: Attempt[];
    events: (Omit<TimelineEvent, 'tone'> & { tone: string })[];
    refunds: RefundRow[];
    webhooks: Webhook[];
    links: {
        visit: { folio: string; url: string } | null;
        project: { url: string } | null;
        receipt: string | null;
    };
    can: { refund: boolean; record: boolean; reconcile: boolean };
    manualMethods: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Pagos', href: '/admin/pagos' },
            { title: 'Detalle', href: '#' },
        ],
    },
});

const tone = (o: { tone?: string } | null | undefined) =>
    (o?.tone ?? 'neutral') as Tone;
const base = computed(() => `/admin/pagos/${props.payment.uuid}`);

const timeline = computed<TimelineEvent[]>(() =>
    props.events.map((e) => ({ ...e, tone: e.tone as Tone })),
);

const summary = computed<KeyValueItem[]>(() => [
    { key: 'amount', label: 'Monto', value: props.payment.amount },
    {
        key: 'refunded',
        label: 'Reembolsado',
        value: props.payment.refundedAmount,
    },
    { key: 'concept', label: 'Concepto', value: props.payment.concept.label },
    {
        key: 'description',
        label: 'Descripción',
        value: props.payment.description,
        full: true,
    },
    {
        key: 'method',
        label: 'Método',
        value: `${props.payment.method.label} · ${props.payment.provider.label}`,
    },
    {
        key: 'card',
        label: 'Tarjeta',
        value: props.payment.cardLast4
            ? `${props.payment.cardBrand ?? 'Tarjeta'} ···· ${props.payment.cardLast4}`
            : null,
    },
    {
        key: 'authorization',
        label: 'Autorización',
        value: props.payment.authorization,
    },
    { key: 'reference', label: 'Referencia', value: props.payment.reference },
    { key: 'fee', label: 'Comisión Openpay', value: props.payment.feeAmount },
    { key: 'net', label: 'Neto', value: props.payment.netAmount },
    { key: 'paidAt', label: 'Pagado', value: props.payment.paidAt },
    { key: 'createdAt', label: 'Creado', value: props.payment.createdAt },
    { key: 'orderId', label: 'Orden interna', value: props.payment.orderId },
    {
        key: 'transactionId',
        label: 'Transacción Openpay',
        value: props.payment.transactionId,
    },
    {
        key: 'reconciledAt',
        label: 'Última conciliación',
        value: props.payment.reconciledAt,
    },
    {
        key: 'recordedBy',
        label: 'Registrado por',
        value: props.payment.recordedBy,
    },
]);

// ---------------------------------------------------------------- Reembolso

const refundOpen = ref(false);
const refundConfirm = ref(false);
const refundForm = useForm({ amount: '', reason: '', idempotency_key: '' });

function openRefund() {
    refundForm.reset();
    refundForm.clearErrors();
    refundForm.amount = props.payment.refundableAmount;
    refundForm.idempotency_key = crypto.randomUUID();
    refundOpen.value = true;
}

function submitRefund(): Promise<void> {
    return new Promise((resolve, reject) => {
        refundForm.post(`${base.value}/reembolsos`, {
            preserveScroll: true,
            onSuccess: () => {
                refundOpen.value = false;
                resolve();
            },
            onError: (errors) =>
                reject(
                    new Error(
                        Object.values(errors)[0] ?? 'No se pudo reembolsar.',
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

// ---------------------------------------------------------------- Conciliar

const reconciling = ref(false);

function reconcile() {
    router.post(
        `${base.value}/conciliar`,
        {},
        {
            preserveScroll: true,
            onStart: () => (reconciling.value = true),
            onFinish: () => (reconciling.value = false),
        },
    );
}
</script>

<template>
    <Head :title="`Pago ${payment.folio}`" />

    <PageContainer>
        <PageHeader :title="payment.folio" :description="payment.customer.name">
            <template #meta>
                <StatusBadge
                    :tone="tone(payment.status)"
                    :label="payment.status.label"
                />
            </template>
            <template #actions>
                <Button
                    v-if="can.reconcile"
                    variant="outline"
                    :disabled="reconciling"
                    @click="reconcile"
                >
                    <Spinner v-if="reconciling" /><RefreshCw
                        v-else
                        class="size-4"
                    />
                    Reconciliar con Openpay
                </Button>
                <Button
                    v-if="can.record"
                    variant="outline"
                    @click="manualOpen = true"
                    ><HandCoins class="size-4" /> Registrar pago manual</Button
                >
                <Button
                    v-if="can.refund"
                    variant="destructive"
                    @click="openRefund"
                    ><Undo2 class="size-4" /> Reembolsar</Button
                >
            </template>
        </PageHeader>

        <FormStatus
            v-if="payment.status.value === 'failed' && payment.errorMessage"
            tone="danger"
            :message="`${payment.errorMessage}${payment.errorCode ? ` (código ${payment.errorCode})` : ''}`"
        />
        <FormStatus
            v-if="payment.status.value === 'disputed'"
            tone="danger"
            message="Este cargo tiene un contracargo abierto. Reúne evidencia (comprobante, visita realizada) y atiéndelo desde el panel de Openpay."
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                <SectionCard title="Resumen">
                    <KeyValue :items="summary" :columns="2">
                        <template #value-amount
                            ><Money
                                :value="payment.amount"
                                class="font-semibold"
                        /></template>
                        <template #value-refunded
                            ><Money :value="payment.refundedAmount"
                        /></template>
                        <template #value-fee
                            ><Money :value="payment.feeAmount"
                        /></template>
                        <template #value-net
                            ><Money :value="payment.netAmount"
                        /></template>
                        <template #value-paidAt
                            ><DateText
                                :value="payment.paidAt"
                                format="datetime"
                        /></template>
                        <template #value-createdAt
                            ><DateText
                                :value="payment.createdAt"
                                format="datetime"
                        /></template>
                        <template #value-reconciledAt
                            ><DateText :value="payment.reconciledAt" relative
                        /></template>
                        <template #value-orderId
                            ><span class="font-mono text-xs">{{
                                payment.orderId
                            }}</span></template
                        >
                        <template #value-transactionId
                            ><span class="font-mono text-xs">{{
                                payment.transactionId ?? '—'
                            }}</span></template
                        >
                    </KeyValue>
                </SectionCard>

                <SectionCard
                    title="Intentos de cobro"
                    description="Cada envío del formulario de tarjeta es un intento con su propia clave."
                    flush
                >
                    <ul v-if="attempts.length" class="divide-y">
                        <li
                            v-for="a in attempts"
                            :key="a.id"
                            class="flex flex-wrap items-start justify-between gap-3 px-5 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium">
                                    Intento {{ a.number }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <DateText
                                        :value="a.createdAt"
                                        format="datetime"
                                    /><template v-if="a.transactionId">
                                        ·
                                        <span class="font-mono">{{
                                            a.transactionId
                                        }}</span></template
                                    >
                                </p>
                                <p
                                    v-if="a.errorMessage"
                                    class="mt-1 text-xs text-danger-ink"
                                >
                                    {{ a.errorMessage
                                    }}<template v-if="a.errorCode">
                                        (código {{ a.errorCode }})</template
                                    >
                                </p>
                            </div>
                            <StatusBadge
                                :tone="tone(a.status)"
                                :label="a.status.label"
                                size="sm"
                            />
                        </li>
                    </ul>
                    <p v-else class="px-5 py-4 text-sm text-muted-foreground">
                        Sin intentos con tarjeta.
                    </p>
                </SectionCard>

                <SectionCard title="Reembolsos" flush>
                    <ul v-if="refunds.length" class="divide-y">
                        <li
                            v-for="r in refunds"
                            :key="r.uuid"
                            class="flex flex-wrap items-start justify-between gap-3 px-5 py-3"
                        >
                            <div class="min-w-0">
                                <Money
                                    :value="r.amount"
                                    class="text-sm font-medium"
                                />
                                <p class="text-sm text-muted-foreground">
                                    {{ r.reason }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <DateText
                                        :value="r.processedAt ?? r.createdAt"
                                        format="datetime"
                                    /><template v-if="r.requestedBy">
                                        · {{ r.requestedBy }}</template
                                    >
                                </p>
                                <p
                                    v-if="r.errorMessage"
                                    class="mt-1 text-xs text-danger-ink"
                                >
                                    {{ r.errorMessage }}
                                </p>
                            </div>
                            <StatusBadge
                                :tone="tone(r.status)"
                                :label="r.status.label"
                                size="sm"
                            />
                        </li>
                    </ul>
                    <p v-else class="px-5 py-4 text-sm text-muted-foreground">
                        Sin reembolsos.
                    </p>
                </SectionCard>

                <SectionCard
                    title="Webhooks recibidos"
                    description="Avisos de Openpay; el estado siempre se confirma consultando el API."
                    flush
                >
                    <ul v-if="webhooks.length" class="divide-y">
                        <li
                            v-for="w in webhooks"
                            :key="w.id"
                            class="flex flex-wrap items-start justify-between gap-3 px-5 py-3"
                        >
                            <div class="min-w-0">
                                <p class="font-mono text-sm">{{ w.type }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <DateText
                                        :value="w.receivedAt"
                                        format="datetime"
                                    />
                                    · {{ w.attempts }}
                                    {{
                                        w.attempts === 1
                                            ? 'intento'
                                            : 'intentos'
                                    }}
                                </p>
                                <p
                                    v-if="w.error"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ w.error }}
                                </p>
                            </div>
                            <StatusBadge
                                :tone="tone(w.status)"
                                :label="w.status.label"
                                size="sm"
                            />
                        </li>
                    </ul>
                    <p v-else class="px-5 py-4 text-sm text-muted-foreground">
                        Sin webhooks para este pago.
                    </p>
                </SectionCard>
            </div>

            <aside class="space-y-6">
                <SectionCard title="Cliente">
                    <p class="font-medium">{{ payment.customer.name }}</p>
                    <a
                        v-if="payment.customer.email"
                        :href="`mailto:${payment.customer.email}`"
                        class="block text-sm text-muted-foreground hover:underline"
                        >{{ payment.customer.email }}</a
                    >
                    <a
                        v-if="payment.customer.phone"
                        :href="`tel:${payment.customer.phone}`"
                        class="block text-sm text-muted-foreground hover:underline"
                        >{{ payment.customer.phone }}</a
                    >
                </SectionCard>

                <SectionCard title="Vínculos">
                    <ul class="space-y-2 text-sm">
                        <li v-if="links.visit">
                            <Link
                                :href="links.visit.url"
                                class="underline-offset-4 hover:underline"
                                >Visita {{ links.visit.folio }}</Link
                            >
                        </li>
                        <li v-if="links.project">
                            <a
                                :href="links.project.url"
                                class="underline-offset-4 hover:underline"
                                >Proyecto</a
                            >
                        </li>
                        <li v-if="links.receipt">
                            <a
                                :href="links.receipt"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 underline-offset-4 hover:underline"
                            >
                                <FileText class="size-4" aria-hidden="true" />
                                Comprobante
                                <ExternalLink
                                    class="size-3"
                                    aria-hidden="true"
                                />
                            </a>
                        </li>
                        <li
                            v-if="
                                !links.visit && !links.project && !links.receipt
                            "
                            class="text-muted-foreground"
                        >
                            Sin vínculos.
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard title="Historial">
                    <Timeline v-if="timeline.length" :events="timeline" />
                    <EmptyState
                        v-else
                        compact
                        :bordered="false"
                        title="Sin eventos"
                    />
                </SectionCard>
            </aside>
        </div>
    </PageContainer>

    <FormSheet
        v-model:open="refundOpen"
        title="Reembolsar pago"
        :description="`Disponible para reembolso: ${formatMoney(payment.refundableAmount) ?? ''}`"
    >
        <form
            id="refund-form"
            class="space-y-5"
            novalidate
            @submit.prevent="refundConfirm = true"
        >
            <div class="space-y-2">
                <Label for="refund-amount">Monto a reembolsar (MXN)</Label>
                <Input
                    id="refund-amount"
                    v-model="refundForm.amount"
                    inputmode="decimal"
                    autocomplete="off"
                    :aria-invalid="!!refundForm.errors.amount"
                />
                <InputError :message="refundForm.errors.amount" />
            </div>
            <div class="space-y-2">
                <Label for="refund-reason">Motivo</Label>
                <Textarea
                    id="refund-reason"
                    v-model="refundForm.reason"
                    rows="3"
                    maxlength="300"
                    placeholder="Ej. el cliente canceló con anticipación"
                />
                <InputError :message="refundForm.errors.reason" />
            </div>
            <p class="text-xs text-muted-foreground">
                El reembolso se solicita a Openpay y puede tardar de 1 a 3 días
                hábiles en reflejarse en el estado de cuenta del cliente.
            </p>
        </form>
        <template #footer>
            <Button variant="outline" @click="refundOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="refund-form"
                variant="destructive"
                :disabled="!refundForm.amount || refundForm.reason.length < 5"
                >Continuar</Button
            >
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="refundConfirm"
        title="¿Confirmas el reembolso?"
        :description="`Se devolverán ${formatMoney(refundForm.amount) ?? refundForm.amount} a la tarjeta del cliente. Esta acción no se puede deshacer.`"
        confirm-label="Reembolsar"
        loading-label="Reembolsando…"
        tone="destructive"
        :confirm="submitRefund"
    />

    <FormSheet
        v-model:open="manualOpen"
        title="Registrar pago manual"
        description="Transferencia o efectivo recibido fuera de Openpay. Queda en la bitácora con tu nombre."
    >
        <form
            id="manual-form"
            class="space-y-5"
            novalidate
            @submit.prevent="submitManual"
        >
            <div class="space-y-2">
                <Label for="manual-method">Método</Label>
                <Select v-model="manualForm.method">
                    <SelectTrigger id="manual-method" class="w-full"
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
                <InputError :message="manualForm.errors.method" />
            </div>
            <div class="space-y-2">
                <Label for="manual-reference">Referencia</Label>
                <Input
                    id="manual-reference"
                    v-model="manualForm.reference"
                    maxlength="120"
                    placeholder="Ej. SPEI 0045821 o recibo 112"
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
                form="manual-form"
                :disabled="
                    manualForm.processing || manualForm.reference.length < 3
                "
            >
                <Spinner v-if="manualForm.processing" /> Registrar pago
            </Button>
        </template>
    </FormSheet>
</template>
