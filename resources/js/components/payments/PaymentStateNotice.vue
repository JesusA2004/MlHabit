<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import {
    CircleAlert,
    Clock,
    Loader2,
    ShieldCheck,
    TimerOff,
} from "@lucide/vue";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

/**
 * Estados del pago que NO son éxito (asesorías y visitas):
 *  - processing: el banco confirma (3D Secure o red); la página sondea.
 *  - stalled: tarda más de lo normal; no volver a pagar.
 *  - failed: rechazo con mensaje seguro (OpenpayErrorMap) y reintento
 *    mientras el apartado siga vigente.
 *  - expired: venció el apartado; no hubo cargo.
 */
const props = withDefaults(
    defineProps<{
        state: "processing" | "stalled" | "failed" | "expired";
        eyebrow?: string | null;
        message?: string | null;
        holdExpiresAt?: string | null;
        retryUrl?: string | null;
        restartUrl?: string | null;
        restartLabel?: string;
    }>(),
    {
        eyebrow: null,
        message: null,
        holdExpiresAt: null,
        retryUrl: null,
        restartUrl: null,
        restartLabel: "Elegir otro horario",
    },
);

const now = ref(Date.now());
let clock: ReturnType<typeof setInterval> | undefined;
onMounted(() => (clock = setInterval(() => (now.value = Date.now()), 1000)));
onBeforeUnmount(() => clearInterval(clock));

const countdown = computed(() => {
    if (!props.holdExpiresAt) {
        return null;
    }

    const total = Math.max(
        0,
        Math.floor(
            (new Date(props.holdExpiresAt).getTime() - now.value) / 1000,
        ),
    );

    if (total === 0) {
        return null;
    }

    const h = Math.floor(total / 3600);
    const mmss = `${String(Math.floor((total % 3600) / 60)).padStart(2, "0")}:${String(total % 60).padStart(2, "0")}`;

    return h > 0 ? `${h} h ${mmss}` : mmss;
});

const copy = computed(() => {
    switch (props.state) {
        case "processing":
            return {
                title: "Estamos confirmando tu pago",
                body: "Tu banco está respondiendo. No cierres esta página ni vuelvas a pagar: se actualiza sola en unos segundos.",
            };
        case "stalled":
            return {
                title: "La confirmación está tardando",
                body: "No vuelvas a pagar. En cuanto el banco confirme te enviaremos un correo; si ya se aprobó, no se hará un segundo cargo.",
            };
        case "failed":
            return {
                title: "No se completó el pago",
                body:
                    props.message ??
                    "El banco no autorizó el cargo. No se realizó ningún cobro.",
            };
        default:
            return {
                title: "Tu apartado venció",
                body: "El horario se liberó y no se realizó ningún cargo. Es posible que siga disponible: elígelo de nuevo.",
            };
    }
});
</script>

<template>
    <div
        class="notice-rise max-w-2xl space-y-6"
        :role="state === 'failed' ? 'alert' : 'status'"
        aria-live="polite"
    >
        <span
            :class="
                cn(
                    'grid size-14 place-items-center rounded-full',
                    state === 'failed'
                        ? 'bg-danger-soft text-danger'
                        : state === 'expired'
                          ? 'bg-muted text-muted-foreground'
                          : 'bg-primary-soft text-primary',
                )
            "
            aria-hidden="true"
        >
            <Loader2
                v-if="state === 'processing'"
                class="size-7 animate-spin"
            />
            <Clock v-else-if="state === 'stalled'" class="size-7" />
            <CircleAlert v-else-if="state === 'failed'" class="size-7" />
            <TimerOff v-else class="size-7" />
        </span>

        <div>
            <p v-if="eyebrow" class="text-sm font-medium text-oak-ink">
                {{ eyebrow }}
            </p>
            <h1 class="mt-2 text-display-md font-display tracking-tight">
                {{ copy.title }}
            </h1>
            <p class="mt-3 text-lg text-muted-foreground">{{ copy.body }}</p>
        </div>

        <div
            v-if="state === 'failed'"
            class="space-y-4 rounded-2xl border bg-card p-5 text-sm"
        >
            <p v-if="retryUrl && countdown">
                Tu horario sigue apartado
                <strong class="tabular-nums">{{ countdown }}</strong> más.
                Puedes intentar con la misma u otra tarjeta.
            </p>
            <p v-else-if="!retryUrl" class="text-muted-foreground">
                El apartado ya no está vigente: elige de nuevo tu horario.
            </p>
            <ul class="list-disc space-y-1 pl-5 text-muted-foreground">
                <li>Revisa número, vencimiento y código de seguridad.</li>
                <li>
                    Algunas tarjetas necesitan habilitar compras en línea desde
                    la app del banco.
                </li>
                <li>Si el problema sigue, prueba con otra tarjeta.</li>
            </ul>
            <div class="flex flex-wrap gap-3">
                <Button v-if="retryUrl" as-child class="h-11">
                    <Link :href="retryUrl">Intentar de nuevo</Link>
                </Button>
                <Button v-else-if="restartUrl" as-child class="h-11">
                    <Link :href="restartUrl">{{ restartLabel }}</Link>
                </Button>
            </div>
        </div>

        <Button
            v-else-if="state === 'expired' && restartUrl"
            as-child
            class="h-11"
        >
            <Link :href="restartUrl">{{ restartLabel }}</Link>
        </Button>

        <p
            v-if="state === 'processing' || state === 'stalled'"
            class="flex items-center gap-2 text-sm text-muted-foreground"
        >
            <ShieldCheck class="size-4 text-success" aria-hidden="true" />
            Nunca mostramos un pago como aprobado sin la confirmación del banco.
        </p>

        <slot />
    </div>
</template>

<style scoped>
.notice-rise {
    animation: notice-rise 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes notice-rise {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .notice-rise {
        animation: none;
    }
}
</style>
