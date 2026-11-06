<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ReceiptText } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BookingConfirmation from '@/components/payments/BookingConfirmation.vue';
import PaymentStateNotice from '@/components/payments/PaymentStateNotice.vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';

type Option = { value: string; label: string; tone: string };

const props = defineProps<{
    visit: {
        folio: string;
        status: Option;
        startsAt: string;
        endsAt: string;
        whenText: string;
        holdExpiresAt: string | null;
        address: string;
        technicianFirstName: string | null;
        isCreditable: boolean;
        creditAmount: string;
        creditValidityDays: number | null;
        needsReschedule: boolean;
        customerFirstName: string;
    };
    payment: {
        folio: string;
        amount: string;
        status: Option;
        cardBrand: string | null;
        cardLast4: string | null;
        paidAt: string | null;
        errorMessage: string | null;
    };
    receiptUrl: string | null;
    payUrl: string | null;
    restartUrl: string;
    calendar?: { icsUrl: string; googleUrl: string } | null;
    portalUrl?: string | null;
}>();

const SETTLED = ['paid', 'partially_refunded', 'refunded', 'disputed'];

/**
 * El estado SIEMPRE viene del backend: "confirmando" sondea el servidor
 * (que concilia con Openpay) y nunca muestra éxito por su cuenta.
 */
const state = computed(() => {
    if (SETTLED.includes(props.payment.status.value)) return 'paid';
    if (props.payment.status.value === 'processing') return 'processing';
    if (
        props.payment.status.value === 'cancelled' ||
        props.visit.status.value === 'expired'
    )
        return 'expired';
    if (props.payment.status.value === 'failed') return 'failed';

    return 'pending';
});

const polls = ref(0);
const MAX_POLLS = 45;
let timer: ReturnType<typeof setTimeout> | undefined;

function poll() {
    clearTimeout(timer);

    if (state.value !== 'processing' || polls.value >= MAX_POLLS) {
        return;
    }

    timer = setTimeout(() => {
        polls.value++;
        router.reload({
            only: ['visit', 'payment', 'receiptUrl', 'payUrl', 'calendar'],
            onFinish: poll,
        });
    }, 4000);
}

onMounted(poll);
watch(state, poll);
onBeforeUnmount(() => clearTimeout(timer));

const nextSteps = computed(() => {
    const steps = [
        'Te enviamos la confirmación, el comprobante y el archivo de calendario por correo.',
        props.visit.technicianFirstName
            ? `Un día antes te recordamos la cita; ${props.visit.technicianFirstName} llegará dentro de la ventana indicada.`
            : 'Un día antes te recordamos la cita y el nombre de quien te visitará.',
        'Ten despejado el espacio a medir y, si puedes, fotos o planos a la mano.',
        'Con las medidas preparamos tu diseño y tu cotización.',
    ];

    if (props.visit.isCreditable && Number(props.visit.creditAmount) > 0) {
        steps.push(
            `Si contratas tu proyecto, abonamos ${formatMoney(props.visit.creditAmount)} a tu anticipo${props.visit.creditValidityDays ? ` (vigente ${props.visit.creditValidityDays} días)` : ''}.`,
        );
    }

    return steps;
});
</script>

<template>
    <Head
        :title="state === 'paid' ? 'Visita confirmada' : 'Estado de tu pago'"
    />

    <section class="motion-calm bg-background pb-20">
        <div class="container-site pt-12 md:pt-20">
            <div class="max-w-3xl">
                <!-- Pagado y con horario firme -->
                <BookingConfirmation
                    v-if="state === 'paid' && !visit.needsReschedule"
                    :eyebrow="`Pago confirmado · visita ${visit.folio}`"
                    :title="`¡Listo, ${visit.customerFirstName}! Tu visita técnica está agendada`"
                    subtitle="Te enviamos la confirmación y el comprobante por correo."
                    :starts-at="visit.startsAt"
                    :ends-at="visit.endsAt"
                    person-label="Te visita"
                    :person-name="visit.technicianFirstName"
                    :place="{
                        icon: 'map',
                        label: 'Domicilio',
                        value: visit.address,
                    }"
                    :payment="payment"
                    :calendar="calendar ?? null"
                    :receipt-url="receiptUrl"
                    :portal-url="portalUrl ?? null"
                    :next-steps="nextSteps"
                />

                <!-- Pagado, pero el horario se ocupó -->
                <div
                    v-else-if="state === 'paid'"
                    class="space-y-6"
                    role="status"
                >
                    <p class="text-sm font-medium text-oak-ink">
                        Visita {{ visit.folio }}
                    </p>
                    <h1 class="text-display-md font-display tracking-tight">
                        Recibimos tu pago, {{ visit.customerFirstName }}
                    </h1>
                    <p class="text-lg text-muted-foreground">
                        El horario que elegiste se ocupó mientras se confirmaba
                        el pago. Un asesor te llamará hoy para acordar una nueva
                        fecha; tu pago de
                        {{ formatMoney(payment.amount) }} MXN está seguro.
                    </p>
                    <Button
                        v-if="receiptUrl"
                        as-child
                        variant="outline"
                        class="h-11"
                    >
                        <a :href="receiptUrl" target="_blank" rel="noopener"
                            ><ReceiptText aria-hidden="true" /> Ver
                            comprobante</a
                        >
                    </Button>
                </div>

                <PaymentStateNotice
                    v-else-if="state === 'processing'"
                    :state="polls < MAX_POLLS ? 'processing' : 'stalled'"
                    :eyebrow="`Visita ${visit.folio}`"
                />

                <PaymentStateNotice
                    v-else-if="state === 'failed'"
                    state="failed"
                    :eyebrow="`Visita ${visit.folio}`"
                    :message="payment.errorMessage"
                    :hold-expires-at="visit.holdExpiresAt"
                    :retry-url="payUrl"
                    :restart-url="restartUrl"
                    restart-label="Volver a agendar"
                />

                <PaymentStateNotice
                    v-else-if="state === 'expired'"
                    state="expired"
                    :eyebrow="`Visita ${visit.folio}`"
                    :restart-url="restartUrl"
                    restart-label="Volver a agendar"
                />

                <!-- Pendiente -->
                <div v-else class="space-y-5">
                    <h1 class="text-display-md font-display tracking-tight">
                        Tu visita está apartada
                    </h1>
                    <p class="text-muted-foreground">
                        Falta el pago para confirmarla: {{ visit.whenText }}.
                    </p>
                    <Button v-if="payUrl" as-child class="h-11"
                        ><Link :href="payUrl"
                            >Pagar {{ formatMoney(payment.amount) }}</Link
                        ></Button
                    >
                    <Button v-else as-child class="h-11"
                        ><Link :href="restartUrl"
                            >Volver a agendar</Link
                        ></Button
                    >
                </div>
            </div>
        </div>
    </section>
</template>
