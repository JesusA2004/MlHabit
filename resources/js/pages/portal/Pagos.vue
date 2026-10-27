<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ReceiptText, Wallet } from '@lucide/vue';
import {
    DateText,
    EmptyState,
    Money,
    PageContainer,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import type { ContractData, PaymentItem } from '@/types/projects';

defineProps<{
    payments: PaymentItem[];
    pendingDeposits: ContractData[];
    contracts: ContractData[];
}>();

const hasCredit = (c: ContractData) => Number(c.visit_credit_amount) > 0;
</script>

<template>
    <Head title="Mis pagos" />

    <PageContainer width="narrow" class="flex flex-col gap-6">
        <PageHeader
            title="Pagos"
            description="Tu anticipo, lo que ya pagaste y tus comprobantes."
        />

        <!-- Anticipos pendientes -->
        <div
            v-for="c in pendingDeposits"
            :key="c.uuid"
            class="flex flex-col gap-4 rounded-2xl border border-warning/30 bg-warning-soft p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-sm text-warning-ink">
                    Anticipo pendiente · {{ c.project?.name }}
                </p>
                <Money
                    :value="c.deposit_due_amount"
                    class="mt-1 block font-display text-2xl font-semibold text-foreground"
                />
                <p class="mt-1 text-sm text-warning-ink">
                    Con este pago apartamos la fabricación de tu proyecto.
                </p>
            </div>
            <Button v-if="c.pay_url" as-child size="lg" class="shrink-0">
                <Link :href="c.pay_url"
                    >Pagar anticipo
                    <ArrowRight class="size-4" aria-hidden="true"
                /></Link>
            </Button>
        </div>

        <!-- Contratos -->
        <SectionCard
            v-for="c in contracts"
            :key="`k-${c.uuid}`"
            :title="c.project?.name ?? 'Contrato'"
            :description="`Contrato ${c.folio}`"
        >
            <template #actions>
                <StatusBadge
                    :tone="c.status.tone"
                    :label="c.status.label"
                    size="sm"
                />
            </template>
            <dl class="flex flex-col gap-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Total del proyecto</dt>
                    <dd class="font-medium tabular-nums">
                        <Money :value="c.contract_amount" />
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">
                        Anticipo ({{ Number(c.deposit_percent) }}%)
                    </dt>
                    <dd class="tabular-nums">
                        <Money :value="c.deposit_amount" />
                    </dd>
                </div>
                <div v-if="hasCredit(c)" class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">
                        Crédito de tu visita técnica
                    </dt>
                    <dd class="text-success-ink tabular-nums">
                        −<Money :value="c.visit_credit_amount" />
                    </dd>
                </div>
                <div class="flex justify-between gap-4 border-t pt-2">
                    <dt class="font-medium text-foreground">
                        Anticipo a pagar
                    </dt>
                    <dd class="font-semibold tabular-nums">
                        <Money :value="c.deposit_due_amount" />
                    </dd>
                </div>
                <p
                    v-if="c.deposit_paid_at"
                    class="text-xs text-muted-foreground"
                >
                    Anticipo cubierto el
                    <DateText :value="c.deposit_paid_at" format="long" />
                </p>
            </dl>
        </SectionCard>

        <!-- Historial -->
        <SectionCard title="Pagos realizados" flush>
            <ul v-if="payments.length" class="divide-y">
                <li
                    v-for="p in payments"
                    :key="p.uuid"
                    class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5 px-4 py-4 sm:px-5"
                >
                    <p class="text-sm font-medium text-foreground">
                        {{ p.concept?.label ?? p.description }}
                    </p>
                    <Money
                        :value="p.amount"
                        class="justify-self-end text-sm font-semibold tabular-nums"
                    />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            {{ p.folio }}
                            <template v-if="p.paid_at">
                                · <DateText :value="p.paid_at" format="medium"
                            /></template>
                            <template v-if="p.card_last4">
                                ·
                                <span class="capitalize">{{
                                    p.card_brand
                                }}</span>
                                •••• {{ p.card_last4 }}</template
                            >
                            <template v-else-if="p.method">
                                · {{ p.method.label }}</template
                            >
                        </p>
                    </div>
                    <StatusBadge
                        v-if="p.status"
                        :tone="p.status.tone"
                        :label="p.status.label"
                        size="sm"
                        class="justify-self-end"
                    />
                    <a
                        v-if="p.receipt_url && p.paid_at"
                        :href="p.receipt_url"
                        target="_blank"
                        rel="noopener"
                        class="col-span-2 inline-flex min-h-11 items-center gap-1.5 justify-self-start text-sm text-primary underline-offset-4 hover:underline sm:min-h-0"
                    >
                        <ReceiptText class="size-3.5" aria-hidden="true" />
                        Comprobante
                    </a>
                </li>
            </ul>
            <EmptyState
                v-else
                compact
                :bordered="false"
                :icon="Wallet"
                title="Aún no hay pagos"
                description="Cuando pagues tu visita o tu anticipo, verás aquí cada pago con su comprobante."
            />
        </SectionCard>
    </PageContainer>
</template>
