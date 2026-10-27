<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CircleAlert,
    Clock,
    Loader2,
    MapPin,
    ReceiptText,
    UserRound,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';

type Option = { value: string; label: string; tone: string };

const props = defineProps<{
    visit: {
        folio: string;
        status: Option;
        whenText: string;
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
        errorMessage: string | null;
    };
    receiptUrl: string | null;
    payUrl: string | null;
    restartUrl: string;
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
            only: ['visit', 'payment', 'receiptUrl', 'payUrl'],
            onFinish: poll,
        });
    }, 4000);
}

onMounted(poll);
watch(state, poll);
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Head
        :title="state === 'paid' ? 'Visita confirmada' : 'Estado de tu pago'"
    />

    <section class="bg-background pb-20">
        <div class="container-site max-w-2xl pt-12 md:pt-20">
            <!-- Pagado -->
            <div v-if="state === 'paid'" class="space-y-6">
                <div>
                    <p class="text-sm font-medium text-oak-ink">
                        Visita {{ visit.folio }}
                    </p>
                    <h1
                        class="mt-2 text-display-md font-display tracking-tight"
                    >
                        {{
                            visit.needsReschedule
                                ? `Recibimos tu pago, ${visit.customerFirstName}`
                                : `¡Listo, ${visit.customerFirstName}! Tu visita está agendada`
                        }}
                    </h1>
                    <p class="mt-3 text-lg text-muted-foreground">
                        {{
                            visit.needsReschedule
                                ? 'El horario que elegiste se ocupó mientras se confirmaba el pago. Un asesor te llamará hoy para acordar una nueva fecha; tu pago está seguro.'
                                : 'Te enviamos la confirmación y el comprobante por correo.'
                        }}
                    </p>
                </div>

                <div class="divide-y rounded-xl border bg-card">
                    <ul
                        v-if="!visit.needsReschedule"
                        class="space-y-3 p-5 text-sm"
                    >
                        <li class="flex gap-3">
                            <CalendarCheck
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            /><span class="first-letter:uppercase">{{
                                visit.whenText
                            }}</span>
                        </li>
                        <li class="flex gap-3">
                            <MapPin
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />{{ visit.address }}
                        </li>
                        <li v-if="visit.technicianFirstName" class="flex gap-3">
                            <UserRound
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />Te visitará {{ visit.technicianFirstName }}
                        </li>
                    </ul>
                    <div class="flex items-center justify-between p-5 text-sm">
                        <span class="text-muted-foreground">
                            Pagado<template v-if="payment.cardLast4">
                                con {{ payment.cardBrand ?? 'tarjeta' }} ••••
                                {{ payment.cardLast4 }}</template
                            >
                        </span>
                        <span class="font-semibold tabular-nums">{{
                            formatMoney(payment.amount)
                        }}</span>
                    </div>
                    <p
                        v-if="
                            visit.isCreditable && Number(visit.creditAmount) > 0
                        "
                        class="p-5 text-sm text-muted-foreground"
                    >
                        Si contratas tu proyecto, abonamos
                        {{ formatMoney(visit.creditAmount) }} a tu
                        anticipo<template v-if="visit.creditValidityDays">
                            (vigente
                            {{ visit.creditValidityDays }} días)</template
                        >.
                    </p>
                </div>

                <Button
                    v-if="receiptUrl"
                    as-child
                    variant="outline"
                    class="h-11"
                >
                    <a :href="receiptUrl" target="_blank" rel="noopener"
                        ><ReceiptText aria-hidden="true" /> Ver comprobante</a
                    >
                </Button>
            </div>

            <!-- Confirmando -->
            <div
                v-else-if="state === 'processing'"
                class="space-y-4 text-center"
                role="status"
                aria-live="polite"
            >
                <Loader2
                    v-if="polls < MAX_POLLS"
                    class="mx-auto size-8 animate-spin text-primary"
                    aria-hidden="true"
                />
                <Clock
                    v-else
                    class="mx-auto size-8 text-primary"
                    aria-hidden="true"
                />
                <h1 class="text-display-md font-display tracking-tight">
                    Estamos confirmando tu pago
                </h1>
                <p class="text-muted-foreground">
                    {{
                        polls < MAX_POLLS
                            ? 'Tu banco está respondiendo. No cierres esta página ni vuelvas a pagar; se actualiza sola.'
                            : 'La confirmación está tardando más de lo normal. No vuelvas a pagar: te avisaremos por correo en cuanto se acredite.'
                    }}
                </p>
            </div>

            <!-- Rechazado -->
            <div v-else-if="state === 'failed'" class="space-y-5">
                <CircleAlert class="size-8 text-danger" aria-hidden="true" />
                <h1 class="text-display-md font-display tracking-tight">
                    No se completó el pago
                </h1>
                <p class="text-muted-foreground">
                    {{ payment.errorMessage ?? 'No se realizó ningún cargo.' }}
                </p>
                <Button v-if="payUrl" as-child class="h-11"
                    ><Link :href="payUrl"
                        >Intentar con otra tarjeta</Link
                    ></Button
                >
                <Button v-else as-child class="h-11"
                    ><Link :href="restartUrl">Volver a agendar</Link></Button
                >
            </div>

            <!-- Vencido -->
            <div v-else-if="state === 'expired'" class="space-y-5">
                <h1 class="text-display-md font-display tracking-tight">
                    Tu apartado venció
                </h1>
                <p class="text-muted-foreground">
                    El horario se liberó y no se realizó ningún cargo. Puedes
                    elegir otra fecha.
                </p>
                <Button as-child class="h-11"
                    ><Link :href="restartUrl">Volver a agendar</Link></Button
                >
            </div>

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
                    ><Link :href="restartUrl">Volver a agendar</Link></Button
                >
            </div>
        </div>
    </section>
</template>
