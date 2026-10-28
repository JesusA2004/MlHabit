<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarX,
    CircleCheck,
    Clock,
    CreditCard,
    ExternalLink,
    Loader2,
    Lock,
    MessageCircle,
    Phone,
    Receipt,
    Timer,
    UserRound,
    Video,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ConsultationSlotPicker from '@/components/crm/ConsultationSlotPicker.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney, TIME_ZONE } from '@/lib/format';
import { cn } from '@/lib/utils';

type Opt = { value: string; label: string; tone?: Tone };

const props = defineProps<{
    consultation: {
        uuid: string;
        folio: string;
        startsAt: string;
        endsAt: string;
        status: Opt;
        channel: Opt;
        advisor: { name: string };
        meetingUrl: string | null;
        price: string;
        isActive: boolean;
        durationMinutes: number;
        holdExpiresAt?: string | null;
        paidAt?: string | null;
    };
    payment?: {
        uuid: string;
        folio: string;
        amount: string;
        status: Opt;
        cardBrand: string | null;
        cardLast4: string | null;
        paidAt: string | null;
        errorMessage: string | null;
    } | null;
    payUrl?: string | null;
    receiptUrl?: string | null;
    lead: { folio: string; firstName: string };
    canChange: boolean;
    canCancel?: boolean;
    settings: { enabled: boolean; durationMinutes: number };
    window: { min: string; max: string };
    availabilityUrl: string;
    rescheduleUrl: string;
    cancelUrl: string;
    bookAgainUrl: string | null;
}>();

const tz = { timeZone: TIME_ZONE } as const;
const dayText = computed(() =>
    new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        ...tz,
    }).format(new Date(props.consultation.startsAt)),
);
const timeText = computed(() => {
    const f = new Intl.DateTimeFormat('es-MX', {
        hour: 'numeric',
        minute: '2-digit',
        ...tz,
    });

    return `${f.format(new Date(props.consultation.startsAt))} – ${f.format(new Date(props.consultation.endsAt))}`;
});

const channelIcon = computed(
    () =>
        ({ video: Video, phone: Phone, whatsapp: MessageCircle })[
            props.consultation.channel.value
        ] ?? Video,
);

// ---------------------------------------------------------------- Pago

const awaitingPayment = computed(
    () => props.consultation.status.value === 'pending_payment',
);
const isBooked = computed(
    () => props.consultation.isActive && !awaitingPayment.value,
);
const processing = computed(() => props.payment?.status.value === 'processing');
const paid = computed(() =>
    ['paid', 'partially_refunded', 'refunded', 'disputed'].includes(
        props.payment?.status.value ?? '',
    ),
);
const canCancel = computed(() => props.canCancel ?? props.canChange);

const now = ref(Date.now());
let clock: ReturnType<typeof setInterval> | undefined;
let poll: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    clock = setInterval(() => (now.value = Date.now()), 1000);
    // Nunca se muestra éxito sin el backend: mientras el banco confirma, se consulta cada 4 s.
    poll = setInterval(() => {
        if (processing.value) {
            router.reload({
                only: [
                    'consultation',
                    'payment',
                    'payUrl',
                    'receiptUrl',
                    'canChange',
                    'canCancel',
                ],
            });
        }
    }, 4000);
});
onBeforeUnmount(() => {
    clearInterval(clock);
    clearInterval(poll);
});

const countdown = computed(() => {
    if (!props.consultation.holdExpiresAt) {
        return null;
    }

    const total = Math.max(
        0,
        Math.floor(
            (new Date(props.consultation.holdExpiresAt).getTime() - now.value) /
                1000,
        ),
    );
    const hours = Math.floor(total / 3600);
    const mmss = `${String(Math.floor((total % 3600) / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;

    if (total === 0) {
        return null;
    }

    return hours > 0 ? `${hours} h ${mmss}` : mmss;
});

// ---------------------------------------------------------------- Reprogramar / cancelar

const rescheduling = ref(false);
const date = ref<string | null>(null);
const form = useForm({ starts_at: null as string | null });

function reschedule() {
    form.post(props.rescheduleUrl, {
        preserveScroll: true,
        onSuccess: () => {
            rescheduling.value = false;
            date.value = null;
        },
    });
}

const cancelOpen = ref(false);
const reason = ref('');

function cancel(): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(
            props.cancelUrl,
            { reason: reason.value || null },
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (errors) =>
                    reject(
                        new Error(
                            Object.values(errors)[0] ??
                                'No pudimos cancelar. Inténtalo de nuevo.',
                        ),
                    ),
            },
        );
    });
}
</script>

<template>
    <Head title="Tu asesoría" />

    <section class="motion-calm bg-background pb-16">
        <div class="container-site pt-10 md:pt-16">
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-oak-ink">
                    Asesoría {{ consultation.folio }}
                </p>
                <h1 class="mt-2 text-display-md font-display tracking-tight">
                    <template v-if="awaitingPayment"
                        >{{ lead.firstName }}, confirma tu asesoría con el
                        pago</template
                    >
                    <template v-else-if="consultation.isActive"
                        >{{ lead.firstName }}, tu asesoría está
                        confirmada</template
                    >
                    <template
                        v-else-if="consultation.status.value === 'cancelled'"
                        >Tu asesoría se canceló</template
                    >
                    <template v-else>Tu asesoría</template>
                </h1>

                <div
                    v-if="payment && (awaitingPayment || paid)"
                    :class="
                        cn(
                            'pay-panel mt-8 flex flex-wrap items-center gap-4 rounded-xl border p-5',
                            paid
                                ? 'border-success/25 bg-success-soft'
                                : 'border-warning/30 bg-warning-soft',
                        )
                    "
                    role="status"
                >
                    <span
                        :class="
                            cn(
                                'grid size-11 shrink-0 place-items-center rounded-full',
                                paid
                                    ? 'bg-success text-success-foreground'
                                    : 'bg-card text-warning-ink',
                            )
                        "
                        aria-hidden="true"
                    >
                        <CircleCheck v-if="paid" class="size-5" />
                        <Loader2
                            v-else-if="processing"
                            class="size-5 animate-spin"
                        />
                        <CreditCard v-else class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1 text-sm">
                        <template v-if="paid">
                            <p class="font-semibold text-success-ink">
                                Pago recibido ·
                                {{ formatMoney(payment.amount) }} MXN
                            </p>
                            <p class="mt-0.5 text-success-ink/80">
                                Folio {{ payment.folio
                                }}<template v-if="payment.cardLast4">
                                    · tarjeta ••••
                                    {{ payment.cardLast4 }}</template
                                >. No guardamos los datos de tu tarjeta.
                            </p>
                        </template>
                        <template v-else-if="processing">
                            <p class="font-semibold text-warning-ink">
                                Estamos confirmando tu pago con el banco
                            </p>
                            <p class="mt-0.5 text-warning-ink/80">
                                Esta página se actualiza sola. No vuelvas a
                                pagar.
                            </p>
                        </template>
                        <template v-else>
                            <p class="font-semibold text-warning-ink">
                                Pendiente de pago ·
                                {{ formatMoney(payment.amount) }} MXN
                            </p>
                            <p
                                class="mt-0.5 flex items-center gap-1.5 text-warning-ink/80"
                            >
                                <Timer class="size-3.5" aria-hidden="true" />
                                <span v-if="countdown"
                                    >Tu horario está apartado
                                    <strong class="tabular-nums">{{
                                        countdown
                                    }}</strong>
                                    más.</span
                                >
                                <span v-else>El apartado está por vencer.</span>
                            </p>
                            <p
                                v-if="payment.errorMessage"
                                class="mt-1 text-danger-ink"
                            >
                                Intento anterior: {{ payment.errorMessage }}
                            </p>
                        </template>
                    </div>
                    <Button v-if="payUrl && !processing" as-child class="h-11">
                        <Link :href="payUrl">
                            <Lock aria-hidden="true" /> Pagar
                            {{ formatMoney(payment.amount) }}
                        </Link>
                    </Button>
                    <Button
                        v-else-if="receiptUrl"
                        as-child
                        variant="outline"
                        class="h-11 bg-card"
                    >
                        <a :href="receiptUrl" target="_blank" rel="noopener">
                            <Receipt aria-hidden="true" /> Ver comprobante
                        </a>
                    </Button>
                </div>

                <article
                    class="mt-8 overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-4 border-b p-6"
                    >
                        <div>
                            <p
                                class="font-display text-2xl font-semibold tracking-tight first-letter:uppercase"
                            >
                                {{ dayText }}
                            </p>
                            <p
                                class="mt-1 text-lg text-muted-foreground tabular-nums"
                            >
                                {{ timeText }}
                                <span class="text-sm"
                                    >(hora del centro de México)</span
                                >
                            </p>
                        </div>
                        <StatusBadge
                            :label="consultation.status.label"
                            :tone="consultation.status.tone ?? 'neutral'"
                        />
                    </div>
                    <dl class="grid gap-5 p-6 sm:grid-cols-3">
                        <div class="flex gap-3">
                            <component
                                :is="channelIcon"
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Medio
                                </dt>
                                <dd class="text-sm font-medium">
                                    {{ consultation.channel.label }}
                                </dd>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <UserRound
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Te atiende
                                </dt>
                                <dd class="text-sm font-medium">
                                    {{ consultation.advisor.name }}
                                </dd>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <Clock
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Duración
                                </dt>
                                <dd class="text-sm font-medium">
                                    {{ consultation.durationMinutes }} minutos
                                </dd>
                            </div>
                        </div>
                    </dl>
                    <div
                        v-if="isBooked"
                        class="border-t bg-muted/40 px-6 py-4 text-sm"
                    >
                        <template v-if="consultation.meetingUrl">
                            <Button as-child class="h-11">
                                <a
                                    :href="consultation.meetingUrl"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Entrar a la reunión
                                    <ExternalLink aria-hidden="true" />
                                </a>
                            </Button>
                        </template>
                        <p
                            v-else-if="consultation.channel.value === 'video'"
                            class="text-muted-foreground"
                        >
                            Te enviaremos el enlace de la videollamada por
                            correo antes de la cita.
                        </p>
                        <p v-else class="text-muted-foreground">
                            Tu asesor se comunicará contigo a la hora acordada.
                        </p>
                    </div>
                </article>

                <div
                    v-if="(canChange || canCancel) && !rescheduling"
                    class="mt-6 flex flex-wrap gap-3"
                >
                    <Button
                        v-if="canChange"
                        variant="outline"
                        class="h-11"
                        @click="rescheduling = true"
                    >
                        <CalendarClock aria-hidden="true" /> Reprogramar
                    </Button>
                    <Button
                        v-if="canCancel"
                        variant="ghost"
                        class="h-11 text-danger-ink"
                        @click="cancelOpen = true"
                    >
                        <CalendarX aria-hidden="true" /> Cancelar asesoría
                    </Button>
                </div>

                <section
                    v-if="canChange && rescheduling"
                    class="mt-8 space-y-4"
                    aria-labelledby="reschedule-title"
                >
                    <div>
                        <h2
                            id="reschedule-title"
                            class="font-display text-xl font-semibold tracking-tight"
                        >
                            Elige una nueva fecha
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Tu horario actual se libera solo cuando confirmes el
                            nuevo. Tu pago se conserva.
                        </p>
                    </div>
                    <ConsultationSlotPicker
                        v-model:date="date"
                        v-model:slot="form.starts_at"
                        :availability-url="availabilityUrl"
                        :min="window.min"
                        :max="window.max"
                        :exclude-slot="consultation.startsAt"
                    />
                    <InputError :message="form.errors.starts_at" />
                    <div class="flex flex-wrap gap-3">
                        <Button
                            class="h-11"
                            :disabled="!form.starts_at || form.processing"
                            @click="reschedule"
                        >
                            <Loader2
                                v-if="form.processing"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            {{
                                form.processing
                                    ? 'Reprogramando…'
                                    : 'Confirmar nueva fecha'
                            }}
                        </Button>
                        <Button
                            variant="ghost"
                            class="h-11"
                            :disabled="form.processing"
                            @click="rescheduling = false"
                            >Mantener mi horario</Button
                        >
                    </div>
                </section>

                <div
                    v-if="bookAgainUrl"
                    class="mt-8 rounded-xl border bg-card p-6"
                >
                    <h2
                        class="font-display text-lg font-semibold tracking-tight"
                    >
                        ¿Quieres agendar otra fecha?
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Elige un nuevo horario cuando te acomode.
                    </p>
                    <Button as-child class="mt-4 h-11">
                        <a :href="bookAgainUrl">Agendar asesoría</a>
                    </Button>
                </div>

                <p
                    v-if="!consultation.isActive && !bookAgainUrl"
                    class="mt-6 text-sm text-muted-foreground"
                >
                    Si necesitas algo más, responde al correo de confirmación y
                    con gusto te ayudamos.
                </p>
            </div>

            <ConfirmDialog
                v-model:open="cancelOpen"
                title="¿Cancelar tu asesoría?"
                confirm-label="Sí, cancelar"
                loading-label="Cancelando…"
                cancel-label="Mantener asesoría"
                tone="destructive"
                :confirm="cancel"
            >
                <span class="block"
                    >Liberaremos tu horario del {{ dayText }}. Puedes agendar
                    otra fecha cuando quieras.</span
                >
                <span v-if="paid" class="mt-2 block"
                    >Tu pago ya se recibió: un asesor te contactará para el
                    reembolso según la política vigente.</span
                >
                <span v-else-if="awaitingPayment" class="mt-2 block"
                    >No se realizará ningún cargo.</span
                >
                <label
                    for="cancel-reason"
                    class="mt-4 block text-sm font-medium text-foreground"
                    >Motivo (opcional)</label
                >
                <Textarea
                    id="cancel-reason"
                    v-model="reason"
                    class="mt-2"
                    rows="2"
                    maxlength="200"
                    placeholder="Nos ayuda a mejorar"
                />
            </ConfirmDialog>
        </div>
    </section>
</template>

<style scoped>
.pay-panel {
    animation: pay-panel-in 420ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes pay-panel-in {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .pay-panel {
        animation: none;
    }
}
</style>
