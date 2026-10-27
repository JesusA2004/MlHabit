<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CircleCheck,
    CreditCard,
    LifeBuoy,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    DateText,
    Money,
    PageContainer,
    PageHeader,
    SectionCard,
} from '@/components/ds';
import OpenpayCardForm from '@/components/payments/OpenpayCardForm.vue';
import type { OpenpayClientConfig } from '@/components/payments/openpay';
import { Button } from '@/components/ui/button';
import { index as pagos } from '@/routes/portal/pagos';
import type { ContractData } from '@/types/projects';

const props = defineProps<{
    contract: ContractData & {
        project: { folio: string; name: string };
        remaining: string;
    };
    payment: { uuid: string; amount: string; charge_url: string } | null;
    paid: boolean;
    openpay: OpenpayClientConfig | null;
}>();

const hasCredit = computed(
    () => Number(props.contract.visit_credit_amount) > 0,
);
const partiallyPaid = computed(
    () =>
        Number(props.contract.remaining) <
        Number(props.contract.deposit_due_amount),
);

/** Sin credenciales de Openpay configuradas se ofrece pago asistido, nunca un formulario falso. */
const cardFormAvailable = computed(() =>
    Boolean(props.openpay?.merchantId && props.openpay?.publicKey),
);
</script>

<template>
    <Head title="Pagar anticipo" />

    <PageContainer width="narrow" class="flex flex-col gap-6">
        <Link
            :href="pagos().url"
            class="inline-flex items-center gap-1.5 self-start rounded-md text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" aria-hidden="true" /> Pagos
        </Link>

        <PageHeader
            :title="paid ? 'Tu anticipo está cubierto' : 'Paga tu anticipo'"
            :description="`${contract.project.name} · contrato ${contract.folio}`"
        />

        <div
            v-if="paid"
            class="flex items-start gap-3 rounded-2xl border border-success/30 bg-success-soft p-5 text-success-ink"
        >
            <CircleCheck class="mt-0.5 size-6 shrink-0" aria-hidden="true" />
            <div>
                <p class="text-base font-semibold">
                    ¡Gracias! Recibimos tu anticipo.
                </p>
                <p class="mt-1 text-sm">
                    Tu proyecto quedó autorizado y entra a la cola de
                    fabricación.
                    <template v-if="contract.deposit_paid_at"
                        >Cubierto el
                        <DateText
                            :value="contract.deposit_paid_at"
                            format="long"
                        />.</template
                    >
                </p>
                <Button as-child variant="outline" size="sm" class="mt-3"
                    ><Link href="/portal">Ver mi proyecto</Link></Button
                >
            </div>
        </div>

        <SectionCard title="Resumen">
            <dl class="flex flex-col gap-2.5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Total del proyecto</dt>
                    <dd class="tabular-nums">
                        <Money :value="contract.contract_amount" />
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">
                        Anticipo ({{ Number(contract.deposit_percent) }}%)
                    </dt>
                    <dd class="tabular-nums">
                        <Money :value="contract.deposit_amount" />
                    </dd>
                </div>
                <div v-if="hasCredit" class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">
                        Crédito de tu visita técnica
                    </dt>
                    <dd class="text-success-ink tabular-nums">
                        −<Money :value="contract.visit_credit_amount" />
                    </dd>
                </div>
                <div
                    v-if="partiallyPaid && !paid"
                    class="flex justify-between gap-4"
                >
                    <dt class="text-muted-foreground">Ya pagaste</dt>
                    <dd class="tabular-nums">
                        −<Money
                            :value="
                                (
                                    Number(contract.deposit_due_amount) -
                                    Number(contract.remaining)
                                ).toFixed(2)
                            "
                        />
                    </dd>
                </div>
                <div
                    class="mt-1 flex items-baseline justify-between gap-4 border-t pt-3"
                >
                    <dt class="font-semibold text-foreground">
                        {{ paid ? 'Anticipo pagado' : 'A pagar ahora' }}
                    </dt>
                    <dd
                        class="font-display text-2xl font-semibold tabular-nums"
                    >
                        <Money
                            :value="
                                paid
                                    ? contract.deposit_due_amount
                                    : contract.remaining
                            "
                        />
                    </dd>
                </div>
            </dl>
            <p v-if="hasCredit" class="mt-3 text-xs text-muted-foreground">
                El crédito de tu visita se descuenta del anticipo; el total de
                tu proyecto no cambia.
            </p>
        </SectionCard>

        <template v-if="!paid && payment">
            <SectionCard
                v-if="cardFormAvailable"
                title="Pago con tarjeta"
                :icon="CreditCard"
            >
                <OpenpayCardForm
                    :payment-uuid="payment.uuid"
                    :amount="payment.amount"
                    :charge-url="payment.charge_url"
                    :openpay="openpay"
                    :description="`Anticipo del contrato ${contract.folio}`"
                />
            </SectionCard>
            <div
                v-else
                class="flex flex-col gap-3 rounded-2xl border bg-card p-5"
            >
                <div class="flex items-start gap-3">
                    <LifeBuoy
                        class="mt-0.5 size-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="font-medium text-foreground">
                            El pago con tarjeta no está disponible por ahora
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Mientras tanto, contacta a tu asesor para pagar por
                            transferencia o en sucursal. En cuanto lo
                            registremos, verás tu proyecto autorizado aquí.
                        </p>
                    </div>
                </div>
                <Button as-child variant="outline" class="self-start"
                    ><Link href="/portal/ayuda"
                        >Contactar a mi asesor</Link
                    ></Button
                >
            </div>
            <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <ShieldCheck class="size-3.5" aria-hidden="true" /> Nunca
                guardamos los datos de tu tarjeta.
            </p>
        </template>
    </PageContainer>
</template>
