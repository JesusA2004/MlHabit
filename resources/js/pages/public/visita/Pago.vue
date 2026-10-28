<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProgressSteps from '@/components/ds/ProgressSteps.vue';
import type { ProgressStep } from '@/components/ds/ProgressSteps.vue';
import type { OpenpayClientConfig } from '@/components/payments/openpay';
import SecureCheckout from '@/components/payments/SecureCheckout.vue';
import VisitSummaryCard from '@/components/visits/VisitSummaryCard.vue';

type Option = { value: string; label: string; tone: string };

defineProps<{
    visit: {
        uuid: string;
        folio: string;
        status: Option;
        whenText: string;
        holdExpiresAt: string | null;
        address: string;
        zone: string;
        technicianFirstName: string | null;
        fee: string;
        isCreditable: boolean;
        creditAmount: string;
        creditValidityDays: number | null;
        cancellationPolicy: string;
        reschedulePolicy: string;
        customerFirstName: string;
    };
    payment: {
        uuid: string;
        folio: string;
        amount: string;
        description: string;
        status: Option;
        errorMessage: string | null;
    };
    openpay: OpenpayClientConfig;
    chargeUrl: string;
    statusUrl: string;
    restartUrl: string;
}>();

const steps: ProgressStep[] = [
    { key: 'cp', title: 'Cobertura' },
    { key: 'fecha', title: 'Fecha y hora' },
    { key: 'datos', title: 'Domicilio' },
    { key: 'revision', title: 'Revisión' },
    { key: 'pago', title: 'Pago' },
];
</script>

<template>
    <Head title="Paga tu visita técnica" />

    <SecureCheckout
        :eyebrow="`Visita ${visit.folio} · pago seguro`"
        :title="`${visit.customerFirstName}, confirma tu visita con el pago`"
        :hold-expires-at="visit.holdExpiresAt"
        :payment="payment"
        :openpay="openpay"
        :charge-url="chargeUrl"
        :restart-url="restartUrl"
        confirm-hint="Crédito o débito. Tu visita queda confirmada en cuanto el banco autoriza el cargo."
    >
        <template #steps>
            <ProgressSteps
                :steps="steps"
                current="pago"
                label="Pasos para agendar"
                orientation="horizontal"
                class="mt-8 hidden max-w-3xl sm:flex"
            />
        </template>

        <template #summary>
            <VisitSummaryCard
                :when="visit.whenText"
                :address="visit.address"
                :technician="visit.technicianFirstName"
                :fee="payment.amount"
                :is-creditable="visit.isCreditable"
                :credit-amount="visit.creditAmount"
                :credit-validity-days="visit.creditValidityDays"
                title="Estás pagando: visita técnica"
            />
        </template>

        <template #aside>
            <details class="rounded-2xl border bg-card p-4 text-sm">
                <summary class="cursor-pointer font-medium">
                    Cancelación y reprogramación
                </summary>
                <p class="mt-3 whitespace-pre-line text-muted-foreground">
                    {{ visit.cancellationPolicy }}
                </p>
                <p class="mt-3 whitespace-pre-line text-muted-foreground">
                    {{ visit.reschedulePolicy }}
                </p>
            </details>
        </template>
    </SecureCheckout>
</template>
