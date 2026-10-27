<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileSignature, HandCoins } from '@lucide/vue';
import { computed, ref } from 'vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import Money from '@/components/ds/Money.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import contratos from '@/routes/admin/contratos';
import cotizaciones from '@/routes/admin/cotizaciones';
import type { ContractData, QuoteSummary } from '@/types/projects';
import type { HubCan, HubProject } from './types';

const props = defineProps<{
    project: HubProject;
    contract: ContractData | null;
    quotes: QuoteSummary[];
    paymentMethods: { value: string; label: string }[];
    can: HubCan;
}>();

const sentQuotes = computed(() =>
    props.quotes.filter((q) => q.status.value === 'sent'),
);
const hasCredit = computed(
    () => props.contract && Number(props.contract.visit_credit_amount) > 0,
);
const payable = computed(
    () =>
        props.contract &&
        ['pending_deposit', 'deposit_refunded'].includes(
            props.contract.status.value,
        ),
);

// ---- Aceptación a nombre del cliente
const acceptOpen = ref(false);
const acceptQuote = ref<QuoteSummary | null>(null);
const acceptForm = useForm({ reason: '' });

function openAccept(quote: QuoteSummary) {
    acceptQuote.value = quote;
    acceptForm.reset();
    acceptForm.clearErrors();
    acceptOpen.value = true;
}

function submitAccept() {
    if (!acceptQuote.value) return;
    acceptForm.post(cotizaciones.aceptar(acceptQuote.value.uuid).url, {
        preserveScroll: true,
        onSuccess: () => (acceptOpen.value = false),
    });
}

// ---- Pago manual
const payOpen = ref(false);
const payForm = useForm({
    amount: '',
    method: 'transfer' as string | null,
    reference: '',
});

function openPay() {
    payForm.reset();
    payForm.clearErrors();
    payForm.amount = props.contract?.remaining ?? '';
    payOpen.value = true;
}

function submitPay() {
    if (!props.contract) return;
    payForm.post(contratos.anticipoManual(props.contract.uuid).url, {
        preserveScroll: true,
        onSuccess: () => (payOpen.value = false),
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <template v-if="!contract">
            <EmptyState
                :icon="FileSignature"
                title="Sin contrato todavía"
                description="El contrato nace cuando el cliente acepta una cotización en su portal. Si aceptó por otro medio, regístralo aquí con el motivo."
            />
            <SectionCard
                v-if="sentQuotes.length && can.quotes_manage"
                title="Cotizaciones esperando respuesta"
                flush
            >
                <ul class="flex flex-col divide-y">
                    <li
                        v-for="q in sentQuotes"
                        :key="q.uuid"
                        class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span class="text-sm"
                            ><span class="font-medium"
                                >v{{ q.version }} · {{ q.title }}</span
                            >
                            · <Money :value="q.total"
                        /></span>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="openAccept(q)"
                            >Registrar aceptación del cliente</Button
                        >
                    </li>
                </ul>
            </SectionCard>
        </template>

        <template v-else>
            <SectionCard :title="`Contrato ${contract.folio}`">
                <template #actions>
                    <StatusBadge
                        :tone="contract.status.tone"
                        :label="contract.status.label"
                    />
                </template>
                <div class="grid gap-6 md:grid-cols-2">
                    <dl class="flex flex-col gap-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">
                                Total del contrato
                            </dt>
                            <dd>
                                <Money
                                    :value="contract.contract_amount"
                                    class="font-semibold"
                                />
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">
                                Anticipo ({{
                                    Number(contract.deposit_percent)
                                }}%)
                            </dt>
                            <dd><Money :value="contract.deposit_amount" /></dd>
                        </div>
                        <div
                            v-if="hasCredit"
                            class="flex justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">
                                Crédito de visita aplicado
                            </dt>
                            <dd class="text-success-ink">
                                −<Money :value="contract.visit_credit_amount" />
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 border-t pt-3">
                            <dt class="font-medium">Anticipo por cobrar</dt>
                            <dd>
                                <Money
                                    :value="contract.deposit_due_amount"
                                    class="font-semibold"
                                />
                            </dd>
                        </div>
                        <div
                            v-if="
                                contract.paid !== null &&
                                contract.paid !== undefined
                            "
                            class="flex justify-between gap-3"
                        >
                            <dt class="text-muted-foreground">Cobrado</dt>
                            <dd><Money :value="contract.paid" /></dd>
                        </div>
                        <div v-if="payable" class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Pendiente</dt>
                            <dd>
                                <Money
                                    :value="contract.remaining"
                                    class="font-semibold text-warning-ink"
                                />
                            </dd>
                        </div>
                    </dl>
                    <dl class="flex flex-col gap-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Cotización</dt>
                            <dd>
                                <a
                                    v-if="contract.quote_uuid"
                                    :href="
                                        cotizaciones.edit(contract.quote_uuid)
                                            .url
                                    "
                                    class="text-primary tabular-nums hover:underline"
                                    >{{ contract.quote_folio }}</a
                                >
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Firmado</dt>
                            <dd>
                                <DateText
                                    :value="contract.signed_at"
                                    format="datetime"
                                />
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">
                                Anticipo cubierto
                            </dt>
                            <dd>
                                <DateText
                                    :value="contract.deposit_paid_at"
                                    format="datetime"
                                    fallback="Pendiente"
                                />
                            </dd>
                        </div>
                    </dl>
                </div>
                <p
                    v-if="contract.status.value === 'deposit_refunded'"
                    class="mt-4 rounded-lg bg-danger-soft p-3 text-sm text-danger-ink"
                >
                    El anticipo se reembolsó y ya no cubre el monto pactado.
                    Decide si se cobra de nuevo o se cancela el proyecto; el
                    proyecto no retrocede solo.
                </p>
                <p class="mt-4 text-xs text-muted-foreground">
                    Los montos se congelaron al contratar: cambios en la
                    configuración comercial no los afectan.
                </p>
            </SectionCard>

            <SectionCard title="Pagos del contrato" flush>
                <template #actions>
                    <Button
                        v-if="can.record_payment && payable"
                        size="sm"
                        @click="openPay"
                    >
                        <HandCoins class="size-4" aria-hidden="true" />
                        Registrar pago manual
                    </Button>
                </template>
                <p
                    v-if="!contract.payments?.length"
                    class="px-4 py-4 text-sm text-muted-foreground"
                >
                    Aún no hay pagos de este contrato.
                </p>
                <ul v-else class="flex flex-col divide-y">
                    <li
                        v-for="pay in contract.payments"
                        :key="pay.uuid"
                        class="flex flex-col gap-2 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-medium">
                                {{ pay.concept?.label ?? 'Pago' }} ·
                                <span class="text-xs tabular-nums">{{
                                    pay.folio
                                }}</span>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ pay.method?.label
                                }}<template v-if="pay.card_last4">
                                    •••• {{ pay.card_last4 }}</template
                                ><template v-if="pay.reference">
                                    · Ref. {{ pay.reference }}</template
                                >
                                ·
                                <DateText
                                    :value="pay.paid_at ?? pay.created_at"
                                    format="datetime"
                                />
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Money :value="pay.amount" class="font-semibold" />
                            <StatusBadge
                                v-if="pay.status"
                                :tone="pay.status.tone"
                                :label="pay.status.label"
                                size="sm"
                            />
                        </div>
                    </li>
                </ul>
            </SectionCard>
        </template>

        <FormSheet
            v-model:open="acceptOpen"
            title="Registrar aceptación del cliente"
            description="Crea el contrato con el anticipo vigente y aplica el crédito de visita si existe. Queda en bitácora con tu nombre."
        >
            <form
                id="accept-form"
                class="flex flex-col gap-4"
                @submit.prevent="submitAccept"
            >
                <p v-if="acceptQuote" class="rounded-lg bg-muted p-3 text-sm">
                    {{ acceptQuote.folio }} · v{{ acceptQuote.version }} ·
                    <Money :value="acceptQuote.total" class="font-semibold" />
                </p>
                <div class="grid gap-1.5">
                    <Label for="acc-reason">¿Cómo aceptó el cliente?</Label>
                    <Textarea
                        id="acc-reason"
                        v-model="acceptForm.reason"
                        rows="4"
                        maxlength="500"
                        placeholder="Ej. Firmó la cotización impresa en sucursal el 12/11."
                    />
                    <InputError
                        :message="
                            acceptForm.errors.reason ||
                            (acceptForm.errors as Record<string, string>).quote
                        "
                    />
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="acceptOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="accept-form"
                    :disabled="
                        acceptForm.reason.trim().length < 5 ||
                        acceptForm.processing
                    "
                >
                    {{
                        acceptForm.processing
                            ? 'Creando contrato…'
                            : 'Crear contrato'
                    }}
                </Button>
            </template>
        </FormSheet>

        <FormSheet
            v-model:open="payOpen"
            title="Registrar pago de anticipo"
            description="Para transferencias o efectivo ya recibidos. Al cubrir el anticipo, el proyecto se autoriza."
        >
            <form
                id="pay-form"
                class="flex flex-col gap-4"
                @submit.prevent="submitPay"
            >
                <div class="grid gap-1.5">
                    <Label for="pay-amount">Monto recibido (MXN)</Label>
                    <Input
                        id="pay-amount"
                        v-model="payForm.amount"
                        inputmode="decimal"
                        placeholder="9850.00"
                    />
                    <p
                        v-if="contract?.remaining"
                        class="text-xs text-muted-foreground"
                    >
                        Pendiente: <Money :value="contract.remaining" />
                    </p>
                    <InputError :message="payForm.errors.amount" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="pay-method">Forma de pago</Label>
                    <SearchSelect
                        id="pay-method"
                        v-model="payForm.method"
                        :options="paymentMethods"
                    />
                    <InputError :message="payForm.errors.method" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="pay-ref">Referencia</Label>
                    <Input
                        id="pay-ref"
                        v-model="payForm.reference"
                        maxlength="120"
                        placeholder="Folio de transferencia o recibo"
                    />
                    <InputError :message="payForm.errors.reference" />
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="payOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="pay-form"
                    :disabled="payForm.processing"
                    >{{
                        payForm.processing ? 'Registrando…' : 'Registrar pago'
                    }}</Button
                >
            </template>
        </FormSheet>
    </div>
</template>
