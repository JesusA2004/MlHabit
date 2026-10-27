<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Timer } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ProgressSteps from '@/components/ds/ProgressSteps.vue';
import type { ProgressStep } from '@/components/ds/ProgressSteps.vue';
import OpenpayCardForm from '@/components/payments/OpenpayCardForm.vue';
import type { OpenpayClientConfig } from '@/components/payments/openpay';
import { Button } from '@/components/ui/button';
import VisitSummaryCard from '@/components/visits/VisitSummaryCard.vue';
import { cn } from '@/lib/utils';

type Option = { value: string; label: string; tone: string };

const props = defineProps<{
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

// ---------------------------------------------------------------- Cuenta regresiva del apartado

const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => (now.value = Date.now()), 1000);
});
onBeforeUnmount(() => clearInterval(timer));

const remainingMs = computed(() =>
    props.visit.holdExpiresAt
        ? new Date(props.visit.holdExpiresAt).getTime() - now.value
        : 0,
);
const expired = computed(
    () => props.visit.holdExpiresAt !== null && remainingMs.value <= 0,
);
const countdown = computed(() => {
    const total = Math.max(0, Math.floor(remainingMs.value / 1000));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;
    const mmss = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

    return hours > 0 ? `${hours} h ${mmss}` : mmss;
});
const urgent = computed(
    () => remainingMs.value > 0 && remainingMs.value < 3 * 60 * 1000,
);
</script>

<template>
    <Head title="Paga tu visita técnica" />

    <section class="motion-calm bg-background pb-20">
        <div class="container-site pt-10 md:pt-16">
            <div class="max-w-2xl">
                <p class="text-sm font-medium text-oak-ink">
                    Visita {{ visit.folio }}
                </p>
                <h1 class="mt-2 text-display-md font-display tracking-tight">
                    {{ visit.customerFirstName }}, confirma tu visita con el
                    pago
                </h1>
            </div>

            <ProgressSteps
                :steps="steps"
                current="pago"
                label="Pasos para agendar"
                orientation="horizontal"
                class="mt-8 hidden max-w-3xl sm:flex"
            />

            <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_21rem]">
                <div class="min-w-0 space-y-6">
                    <div
                        v-if="visit.holdExpiresAt"
                        :class="
                            cn(
                                'flex items-center gap-3 rounded-xl border p-4 text-sm',
                                expired
                                    ? 'border-danger/25 bg-danger-soft text-danger-ink'
                                    : urgent
                                      ? 'border-warning/30 bg-warning-soft text-warning-ink'
                                      : 'bg-card',
                            )
                        "
                        role="status"
                    >
                        <Timer class="size-5 shrink-0" aria-hidden="true" />
                        <p v-if="!expired">
                            Tu horario está apartado. Completa el pago en
                            <strong
                                class="font-semibold tabular-nums"
                                aria-live="off"
                                >{{ countdown }}</strong
                            >.
                        </p>
                        <p v-else>
                            Tu apartado venció y el horario se liberó. No se
                            realizó ningún cargo.
                        </p>
                    </div>

                    <div v-if="expired" class="rounded-xl border bg-card p-6">
                        <p class="font-medium">Elige de nuevo tu fecha</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Es posible que el mismo horario siga disponible.
                        </p>
                        <Button as-child class="mt-4 h-11">
                            <Link :href="restartUrl">Volver a agendar</Link>
                        </Button>
                    </div>

                    <div
                        v-else
                        class="rounded-xl border bg-card p-5 shadow-xs sm:p-6"
                    >
                        <h2
                            class="font-display text-xl font-semibold tracking-tight"
                        >
                            Pago con tarjeta
                        </h2>
                        <p class="mt-1 mb-5 text-sm text-muted-foreground">
                            Crédito o débito. Tu visita queda confirmada en
                            cuanto el banco autoriza el cargo.
                        </p>
                        <p
                            v-if="payment.errorMessage"
                            class="mb-4 rounded-lg border border-danger/25 bg-danger-soft p-3 text-sm text-danger-ink"
                            role="alert"
                        >
                            Tu intento anterior no se completó:
                            {{ payment.errorMessage }}
                        </p>
                        <OpenpayCardForm
                            :payment-uuid="payment.uuid"
                            :amount="payment.amount"
                            :charge-url="chargeUrl"
                            :openpay="openpay"
                            :disabled="expired"
                            :description="`Folio ${payment.folio}`"
                        />
                    </div>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                    <VisitSummaryCard
                        :when="visit.whenText"
                        :address="visit.address"
                        :technician="visit.technicianFirstName"
                        :fee="payment.amount"
                        :is-creditable="visit.isCreditable"
                        :credit-amount="visit.creditAmount"
                        :credit-validity-days="visit.creditValidityDays"
                    />
                    <details class="rounded-xl border bg-card p-4 text-sm">
                        <summary class="cursor-pointer font-medium">
                            Cancelación y reprogramación
                        </summary>
                        <p
                            class="mt-3 whitespace-pre-line text-muted-foreground"
                        >
                            {{ visit.cancellationPolicy }}
                        </p>
                        <p
                            class="mt-3 whitespace-pre-line text-muted-foreground"
                        >
                            {{ visit.reschedulePolicy }}
                        </p>
                    </details>
                </aside>
            </div>
        </div>
    </section>
</template>
