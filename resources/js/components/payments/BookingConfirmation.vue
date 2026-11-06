<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import {
    CalendarCheck,
    CalendarPlus,
    Clock,
    CreditCard,
    Download,
    ExternalLink,
    LayoutDashboard,
    MapPin,
    MessageCircle,
    Phone,
    ReceiptText,
    UserRound,
    Video,
} from "@lucide/vue";
import type { Component } from "vue";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Button } from "@/components/ui/button";
import SuccessMark from "@/components/web/visual/SuccessMark.vue";
import { formatMoney, TIME_ZONE } from "@/lib/format";
import { cn } from "@/lib/utils";

/**
 * Pantalla de éxito tras un pago confirmado POR EL BACKEND (asesorías y
 * visitas técnicas): marca animada, folio, fecha y hora en la zona del
 * centro de México, quién atiende, monto y tarjeta, botón para unirse a la
 * videollamada (activo cerca de la hora), agregar al calendario,
 * comprobante, qué sigue y enlace al portal.
 */
const props = withDefaults(
    defineProps<{
        eyebrow: string;
        title: string;
        subtitle?: string | null;
        startsAt: string;
        endsAt: string;
        /** Persona que atiende: "Te atiende Ana" / "Te visita Luis". */
        personLabel: string;
        personName?: string | null;
        /** Medio (videollamada, llamada…) o domicilio. */
        place?: {
            icon: "video" | "phone" | "whatsapp" | "map";
            label: string;
            value: string;
        } | null;
        payment: {
            folio: string;
            amount: string;
            cardBrand: string | null;
            cardLast4: string | null;
            paidAt?: string | null;
        };
        meeting?: {
            url: string;
            opensAt: string;
            closesAt: string;
        } | null;
        /** Mensaje cuando es videollamada pero aún no hay enlace. */
        meetingPending?: string | null;
        calendar?: { icsUrl: string; googleUrl: string } | null;
        receiptUrl?: string | null;
        portalUrl?: string | null;
        nextSteps: string[];
    }>(),
    {
        subtitle: null,
        personName: null,
        place: null,
        meeting: null,
        meetingPending: null,
        calendar: null,
        receiptUrl: null,
        portalUrl: null,
    },
);

const placeIcons: Record<string, Component> = {
    video: Video,
    phone: Phone,
    whatsapp: MessageCircle,
    map: MapPin,
};

const tz = { timeZone: TIME_ZONE } as const;

const dayText = computed(() =>
    new Intl.DateTimeFormat("es-MX", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
        ...tz,
    }).format(new Date(props.startsAt)),
);

const timeText = computed(() => {
    const f = new Intl.DateTimeFormat("es-MX", {
        hour: "numeric",
        minute: "2-digit",
        ...tz,
    });

    return `${f.format(new Date(props.startsAt))} – ${f.format(new Date(props.endsAt))}`;
});

// ---------------------------------------------------------------- Videollamada

const now = ref(Date.now());
let clock: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    clock = setInterval(() => (now.value = Date.now()), 30_000);
});
onBeforeUnmount(() => clearInterval(clock));

const meetingState = computed<"early" | "open" | "closed" | null>(() => {
    if (!props.meeting) {
        return null;
    }

    if (now.value < new Date(props.meeting.opensAt).getTime()) {
        return "early";
    }

    return now.value <= new Date(props.meeting.closesAt).getTime()
        ? "open"
        : "closed";
});

const opensText = computed(() =>
    props.meeting
        ? new Intl.DateTimeFormat("es-MX", {
              weekday: "long",
              day: "numeric",
              month: "long",
              hour: "numeric",
              minute: "2-digit",
              ...tz,
          }).format(new Date(props.meeting.opensAt))
        : "",
);

const cardText = computed(() => {
    if (!props.payment.cardLast4) {
        return null;
    }

    const brand = props.payment.cardBrand
        ? props.payment.cardBrand.charAt(0).toUpperCase() +
          props.payment.cardBrand.slice(1)
        : "Tarjeta";

    return `${brand} •••• ${props.payment.cardLast4}`;
});
</script>

<template>
    <div class="space-y-8">
        <header class="confirm-rise text-center sm:text-left">
            <SuccessMark class="mx-auto sm:mx-0" />
            <p
                class="mt-5 text-sm font-medium tracking-wide text-success-ink"
                role="status"
            >
                {{ eyebrow }}
            </p>
            <h1 class="mt-2 text-display-md font-display tracking-tight">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="mt-3 text-lg text-muted-foreground">
                {{ subtitle }}
            </p>
        </header>

        <article
            class="confirm-rise confirm-delay-1 overflow-hidden rounded-2xl border bg-card shadow-sm"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-4 border-b p-6"
            >
                <div class="flex gap-4">
                    <span
                        class="grid size-12 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary"
                    >
                        <CalendarCheck class="size-6" aria-hidden="true" />
                    </span>
                    <div>
                        <p
                            class="font-display text-xl font-semibold tracking-tight first-letter:uppercase sm:text-2xl"
                        >
                            {{ dayText }}
                        </p>
                        <p
                            class="mt-1 flex items-center gap-1.5 text-muted-foreground tabular-nums"
                        >
                            <Clock class="size-4" aria-hidden="true" />
                            {{ timeText }}
                            <span class="text-sm"
                                >(hora del centro de México)</span
                            >
                        </p>
                    </div>
                </div>
                <slot name="badge" />
            </div>

            <dl class="grid gap-5 p-6 sm:grid-cols-2">
                <div v-if="personName" class="flex gap-3">
                    <UserRound
                        class="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ personLabel }}
                        </dt>
                        <dd class="text-sm font-medium">{{ personName }}</dd>
                    </div>
                </div>
                <div v-if="place" class="flex gap-3">
                    <component
                        :is="placeIcons[place.icon]"
                        class="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div class="min-w-0">
                        <dt class="text-xs text-muted-foreground">
                            {{ place.label }}
                        </dt>
                        <dd class="text-sm font-medium break-words">
                            {{ place.value }}
                        </dd>
                    </div>
                </div>
                <div class="flex gap-3">
                    <CreditCard
                        class="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Pagado<template v-if="cardText">
                                con {{ cardText }}</template
                            >
                        </dt>
                        <dd class="text-sm font-semibold tabular-nums">
                            {{ formatMoney(payment.amount) }} MXN
                        </dd>
                    </div>
                </div>
                <div class="flex gap-3">
                    <ReceiptText
                        class="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Folio de pago
                        </dt>
                        <dd class="font-mono text-sm font-medium">
                            {{ payment.folio }}
                        </dd>
                    </div>
                </div>
            </dl>

            <div
                v-if="meeting || meetingPending"
                :class="
                    cn(
                        'flex flex-wrap items-center gap-4 border-t px-6 py-5',
                        meetingState === 'open'
                            ? 'bg-success-soft'
                            : 'bg-muted/40',
                    )
                "
            >
                <span
                    :class="
                        cn(
                            'grid size-10 shrink-0 place-items-center rounded-full',
                            meetingState === 'open'
                                ? 'join-pulse bg-success text-success-foreground'
                                : 'bg-card text-primary',
                        )
                    "
                    aria-hidden="true"
                >
                    <Video class="size-5" />
                </span>
                <div class="min-w-0 flex-1 text-sm">
                    <template v-if="meeting">
                        <p class="font-semibold">
                            {{
                                meetingState === "open"
                                    ? "Tu videollamada está lista"
                                    : meetingState === "closed"
                                      ? "La videollamada ya terminó"
                                      : "Videollamada con enlace propio"
                            }}
                        </p>
                        <p class="mt-0.5 text-muted-foreground">
                            <template v-if="meetingState === 'early'"
                                >El botón se activa el {{ opensText }} (15
                                minutos antes). Te enviamos el enlace por
                                correo.</template
                            >
                            <template v-else-if="meetingState === 'open'"
                                >Entra desde tu computadora o celular; no
                                necesitas instalar nada.</template
                            >
                            <template v-else
                                >Si se cortó, escríbenos y te
                                ayudamos.</template
                            >
                        </p>
                    </template>
                    <p v-else class="text-muted-foreground">
                        {{ meetingPending }}
                    </p>
                </div>
                <Button
                    v-if="meeting && meetingState === 'open'"
                    as-child
                    class="h-11"
                >
                    <a :href="meeting.url" target="_blank" rel="noopener">
                        Unirse a la videollamada
                        <ExternalLink aria-hidden="true" />
                    </a>
                </Button>
                <Button
                    v-else-if="meeting && meetingState === 'early'"
                    class="h-11"
                    disabled
                    aria-disabled="true"
                >
                    Unirse a la videollamada
                </Button>
            </div>
        </article>

        <div
            class="confirm-rise confirm-delay-2 flex flex-wrap gap-3"
            aria-label="Acciones"
        >
            <template v-if="calendar">
                <Button as-child variant="outline" class="h-11">
                    <a :href="calendar.icsUrl" download>
                        <Download aria-hidden="true" /> Agregar a mi calendario
                    </a>
                </Button>
                <Button as-child variant="outline" class="h-11">
                    <a
                        :href="calendar.googleUrl"
                        target="_blank"
                        rel="noopener"
                    >
                        <CalendarPlus aria-hidden="true" /> Google Calendar
                    </a>
                </Button>
            </template>
            <Button v-if="receiptUrl" as-child variant="ghost" class="h-11">
                <a :href="receiptUrl" target="_blank" rel="noopener">
                    <ReceiptText aria-hidden="true" /> Ver comprobante
                </a>
            </Button>
        </div>

        <section
            class="confirm-rise confirm-delay-3 rounded-2xl border bg-card p-6"
            aria-labelledby="next-steps"
        >
            <h2
                id="next-steps"
                class="font-display text-lg font-semibold tracking-tight"
            >
                ¿Qué sigue?
            </h2>
            <ol class="mt-4 space-y-3">
                <li
                    v-for="(step, i) in nextSteps"
                    :key="i"
                    class="flex gap-3 text-sm"
                >
                    <span
                        class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-soft text-xs font-semibold text-primary tabular-nums"
                        aria-hidden="true"
                        >{{ i + 1 }}</span
                    >
                    <span class="pt-0.5">{{ step }}</span>
                </li>
            </ol>
            <div
                v-if="portalUrl"
                class="mt-5 flex flex-wrap items-center gap-3 border-t pt-5 text-sm"
            >
                <LayoutDashboard
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                <span class="text-muted-foreground"
                    >Consulta tus citas, pagos y avances en tu portal.</span
                >
                <Link
                    :href="portalUrl"
                    class="font-medium text-primary underline-offset-4 hover:underline"
                    >Ir a mi portal</Link
                >
            </div>
        </section>

        <slot />
    </div>
</template>

<style scoped>
.confirm-rise {
    animation: confirm-rise 560ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.confirm-delay-1 {
    animation-delay: 180ms;
}

.confirm-delay-2 {
    animation-delay: 300ms;
}

.confirm-delay-3 {
    animation-delay: 420ms;
}

.join-pulse {
    animation: join-pulse 1.8s ease-in-out infinite;
}

@keyframes confirm-rise {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
}

@keyframes join-pulse {
    50% {
        box-shadow: 0 0 0 8px
            color-mix(in oklab, var(--success) 18%, transparent);
    }
}

@media (prefers-reduced-motion: reduce) {
    .confirm-rise,
    .join-pulse {
        animation: none;
    }
}
</style>
