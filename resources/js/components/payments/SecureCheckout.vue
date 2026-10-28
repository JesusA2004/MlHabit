<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarCheck,
    Clock,
    Lock,
    MapPin,
    MessageCircle,
    Phone,
    ReceiptText,
    ShieldCheck,
    Timer,
    UserRound,
    Video,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import CardBrandMarks from './CardBrandMarks.vue';
import OpenpayCardForm from './OpenpayCardForm.vue';
import type { OpenpayClientConfig } from './openpay';

/**
 * Checkout de tarjeta compartido (visitas técnicas y asesorías): cuenta
 * regresiva del apartado, formulario seguro (OpenpayCardForm) y resumen de
 * lo que se paga. Cargo único: no se guarda la tarjeta ni se crea un
 * cliente en Openpay.
 *
 * El resumen se arma con `summary` o se reemplaza con el slot `summary`;
 * el slot `aside` agrega contenido bajo el resumen (políticas, etc.).
 */
export type CheckoutSummaryItem = {
    icon?:
        | 'calendar'
        | 'clock'
        | 'video'
        | 'phone'
        | 'whatsapp'
        | 'map'
        | 'user';
    label: string;
    value: string;
};

const props = withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        subtitle?: string;
        holdExpiresAt?: string | null;
        payment: {
            uuid: string;
            folio: string;
            amount: string;
            description?: string;
            errorMessage?: string | null;
        };
        openpay: OpenpayClientConfig;
        chargeUrl: string;
        restartUrl: string;
        restartLabel?: string;
        summary?: {
            concept: string;
            items: CheckoutSummaryItem[];
            note?: string | null;
        } | null;
        /** Texto bajo "Pago con tarjeta" (qué pasa al autorizar el cargo). */
        confirmHint?: string;
    }>(),
    {
        eyebrow: undefined,
        subtitle: undefined,
        holdExpiresAt: null,
        restartLabel: 'Volver a agendar',
        summary: null,
        confirmHint:
            'Crédito o débito. Queda confirmado en cuanto el banco autoriza el cargo.',
    },
);

const icons: Record<NonNullable<CheckoutSummaryItem['icon']>, Component> = {
    calendar: CalendarCheck,
    clock: Clock,
    video: Video,
    phone: Phone,
    whatsapp: MessageCircle,
    map: MapPin,
    user: UserRound,
};

// ---------------------------------------------------------------- Cuenta regresiva del apartado

const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => (now.value = Date.now()), 1000);
});
onBeforeUnmount(() => clearInterval(timer));

const remainingMs = computed(() =>
    props.holdExpiresAt
        ? new Date(props.holdExpiresAt).getTime() - now.value
        : 0,
);
const expired = computed(
    () => props.holdExpiresAt !== null && remainingMs.value <= 0,
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
    <section class="motion-calm bg-background pb-20">
        <div class="container-site pt-10 md:pt-16">
            <div class="checkout-rise max-w-2xl">
                <p
                    v-if="eyebrow"
                    class="flex items-center gap-2 text-sm font-medium text-oak-ink"
                >
                    <Lock class="size-3.5" aria-hidden="true" />
                    {{ eyebrow }}
                </p>
                <h1 class="mt-2 text-display-md font-display tracking-tight">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="mt-3 text-lg text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>

            <slot name="steps" />

            <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="min-w-0 space-y-6">
                    <div
                        v-if="holdExpiresAt"
                        :class="
                            cn(
                                'checkout-rise flex items-center gap-3 rounded-xl border p-4 text-sm transition-colors duration-500',
                                expired
                                    ? 'border-danger/25 bg-danger-soft text-danger-ink'
                                    : urgent
                                      ? 'border-warning/30 bg-warning-soft text-warning-ink'
                                      : 'bg-card',
                            )
                        "
                        role="status"
                    >
                        <span
                            :class="
                                cn(
                                    'grid size-9 shrink-0 place-items-center rounded-full',
                                    expired
                                        ? 'bg-danger/10'
                                        : urgent
                                          ? 'bg-warning/15'
                                          : 'bg-muted',
                                )
                            "
                        >
                            <Timer
                                :class="cn('size-4', urgent && 'timer-pulse')"
                                aria-hidden="true"
                            />
                        </span>
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

                    <div
                        v-if="expired"
                        class="checkout-rise rounded-xl border bg-card p-6"
                    >
                        <p class="font-medium">Elige de nuevo tu fecha</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Es posible que el mismo horario siga disponible.
                        </p>
                        <Button as-child class="mt-4 h-11">
                            <Link :href="restartUrl">{{ restartLabel }}</Link>
                        </Button>
                    </div>

                    <div
                        v-else
                        class="checkout-rise checkout-delay overflow-hidden rounded-2xl border bg-card shadow-sm"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/40 px-5 py-4 sm:px-6"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="grid size-10 place-items-center rounded-full bg-success-soft text-success-ink"
                                >
                                    <Lock class="size-4" aria-hidden="true" />
                                </span>
                                <div>
                                    <h2
                                        class="font-display text-lg font-semibold tracking-tight"
                                    >
                                        Pago seguro con tarjeta
                                    </h2>
                                    <p class="text-xs text-muted-foreground">
                                        {{ confirmHint }}
                                    </p>
                                </div>
                            </div>
                            <CardBrandMarks size="md" />
                        </div>

                        <div class="p-5 sm:p-6">
                            <p
                                v-if="payment.errorMessage"
                                class="mb-5 rounded-lg border border-danger/25 bg-danger-soft p-3 text-sm text-danger-ink"
                                role="alert"
                            >
                                Tu intento anterior no se completó:
                                {{ payment.errorMessage }} Puedes intentarlo de
                                nuevo con la misma u otra tarjeta.
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
                </div>

                <aside
                    class="checkout-rise checkout-delay-2 space-y-4 lg:sticky lg:top-24 lg:self-start"
                >
                    <slot name="summary">
                        <div
                            v-if="summary"
                            class="overflow-hidden rounded-2xl border bg-card shadow-xs"
                        >
                            <div class="p-5">
                                <p
                                    class="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    <ReceiptText
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Estás pagando
                                </p>
                                <h2
                                    class="mt-2 font-display text-lg font-semibold tracking-tight"
                                >
                                    {{ summary.concept }}
                                </h2>
                                <dl class="mt-4 space-y-3 text-sm">
                                    <div
                                        v-for="item in summary.items"
                                        :key="item.label"
                                        class="flex gap-3"
                                    >
                                        <component
                                            :is="icons[item.icon ?? 'calendar']"
                                            class="mt-0.5 size-4 shrink-0 text-primary"
                                            aria-hidden="true"
                                        />
                                        <div class="min-w-0">
                                            <dt
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ item.label }}
                                            </dt>
                                            <dd
                                                class="font-medium first-letter:uppercase"
                                            >
                                                {{ item.value }}
                                            </dd>
                                        </div>
                                    </div>
                                </dl>
                            </div>
                            <div
                                class="flex items-end justify-between gap-3 border-t border-dashed bg-muted/40 p-5"
                            >
                                <div>
                                    <p class="text-sm font-medium">Total</p>
                                    <p class="text-xs text-muted-foreground">
                                        Pago único · folio {{ payment.folio }}
                                    </p>
                                </div>
                                <p
                                    class="font-display text-2xl font-semibold tracking-tight tabular-nums"
                                >
                                    {{ formatMoney(payment.amount) }}
                                    <span
                                        class="text-xs font-medium text-muted-foreground"
                                        >MXN</span
                                    >
                                </p>
                            </div>
                            <p
                                v-if="summary.note"
                                class="border-t px-5 py-3 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{ summary.note }}
                            </p>
                        </div>
                    </slot>

                    <ul
                        class="space-y-2 rounded-2xl border bg-card p-4 text-xs text-muted-foreground"
                    >
                        <li class="flex gap-2">
                            <ShieldCheck
                                class="size-4 shrink-0 text-success"
                                aria-hidden="true"
                            />
                            <span
                                ><strong class="font-semibold text-foreground"
                                    >No guardamos los datos de tu
                                    tarjeta.</strong
                                >
                                Se usan una sola vez para este cargo.</span
                            >
                        </li>
                        <li class="flex gap-2">
                            <Lock
                                class="size-4 shrink-0 text-success"
                                aria-hidden="true"
                            />
                            <span
                                >Conexión cifrada; el cobro lo procesa
                                Openpay.</span
                            >
                        </li>
                        <li class="flex gap-2">
                            <ReceiptText
                                class="size-4 shrink-0 text-success"
                                aria-hidden="true"
                            />
                            <span
                                >Recibes tu comprobante por correo al
                                instante.</span
                            >
                        </li>
                    </ul>

                    <slot name="aside" />
                </aside>
            </div>
        </div>
    </section>
</template>

<style scoped>
.checkout-rise {
    animation: checkout-rise 520ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.checkout-delay {
    animation-delay: 80ms;
}

.checkout-delay-2 {
    animation-delay: 160ms;
}

.timer-pulse {
    animation: timer-pulse 1s ease-in-out infinite;
}

@keyframes checkout-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@keyframes timer-pulse {
    50% {
        transform: scale(1.15);
    }
}

@media (prefers-reduced-motion: reduce) {
    .checkout-rise,
    .timer-pulse {
        animation: none;
    }
}
</style>
