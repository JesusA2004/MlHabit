<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Check,
    CircleAlert,
    Loader2,
    Lock,
    RefreshCw,
    ShieldCheck,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/format';
import { apiFetch } from '@/lib/http';
import { cn } from '@/lib/utils';
import CardBrandMarks from './CardBrandMarks.vue';
import {
    BRAND_LABEL,
    createToken,
    detectBrand,
    formatCardNumber,
    loadOpenpay,
    luhnValid,
    setupOpenpay,
    tokenErrorMessage,
    uuid,
} from './openpay';
import type { OpenpayClientConfig } from './openpay';

/**
 * Formulario de pago con tarjeta (Openpay.js). Reutilizable: visitas,
 * asesorías y anticipos del portal.
 *
 *  - Los datos de la tarjeta NUNCA se envían a nuestro servidor ni se
 *    guardan: se tokenizan en el navegador (token de un solo uso) y a
 *    `chargeUrl` solo se envían { token_id, device_session_id, attempt_key }.
 *    No se crean clientes ni tarjetas guardadas en Openpay: es un cargo único.
 *  - `attempt_key` se conserva mientras no haya un resultado definitivo:
 *    reenviar tras un corte de red no genera otro cargo.
 *  - Nunca muestra éxito sin que el backend lo confirme. `chargeUrl` debe
 *    responder con App\Payments\ChargeResponse:
 *    { status: 'paid'|'processing'|'redirect'|'failed', message, redirectUrl, nextUrl }.
 *  - Configuración de Openpay: prop `openpay` o, si no se pasa, la prop de
 *    página `openpay` (OpenpayConfig::jsConfig(): merchant, llave pública, sandbox).
 */
type ChargeResult = {
    status: 'paid' | 'processing' | 'redirect' | 'failed';
    message: string;
    redirectUrl: string | null;
    nextUrl: string | null;
};

const props = withDefaults(
    defineProps<{
        paymentUuid: string;
        /** Monto decimal en cadena ("1500.00"); solo se muestra. */
        amount: string;
        chargeUrl: string;
        description?: string;
        openpay?: OpenpayClientConfig | null;
        /** Navegar a `nextUrl` automáticamente cuando el backend responde. */
        followNext?: boolean;
        disabled?: boolean;
        /** Vista previa animada de la tarjeta sobre los campos. */
        showPreview?: boolean;
    }>(),
    {
        followNext: true,
        disabled: false,
        openpay: null,
        description: undefined,
        showPreview: true,
    },
);

const emit = defineEmits<{
    paid: [result: ChargeResult];
    processing: [result: ChargeResult];
    failed: [result: ChargeResult];
}>();

const page = usePage();
const config = computed<OpenpayClientConfig | null>(
    () =>
        props.openpay ??
        (page.props.openpay as OpenpayClientConfig | undefined) ??
        null,
);

// ---------------------------------------------------------------- Openpay.js

const ready = ref(false);
const loadError = ref<string | null>(null);
let deviceSessionId = '';

async function init() {
    loadError.value = null;
    ready.value = false;

    if (!config.value?.merchantId || !config.value.publicKey) {
        loadError.value =
            'Los pagos con tarjeta no están disponibles en este momento. Escríbenos y te ayudamos a completar tu pago.';

        return;
    }

    try {
        const openpay = await loadOpenpay();
        deviceSessionId = setupOpenpay(openpay, config.value);
        ready.value = true;
    } catch {
        loadError.value =
            'No pudimos cargar el formulario seguro de pago. Revisa tu conexión (o desactiva bloqueadores de contenido) e inténtalo de nuevo.';
    }
}

onMounted(init);

// ---------------------------------------------------------------- Campos

const holder = ref('');
const numberText = ref('');
const expiry = ref('');
const cvv = ref('');
const touched = ref<Record<string, boolean>>({});
const focused = ref<string | null>(null);

const digits = computed(() => numberText.value.replace(/\D/g, ''));
const brand = computed(() => detectBrand(digits.value));
const cvvLength = computed(() => (brand.value === 'amex' ? 4 : 3));
const maxDigits = computed(() => (brand.value === 'amex' ? 15 : 19));

function onNumber(value: string | number) {
    const clean = String(value).replace(/\D/g, '').slice(0, 19);
    const limited = clean.slice(0, detectBrand(clean) === 'amex' ? 15 : 19);
    numberText.value = formatCardNumber(limited, detectBrand(limited));
}

function onExpiry(value: string | number) {
    let clean = String(value).replace(/\D/g, '').slice(0, 4);

    if (clean.length === 1 && Number(clean) > 1) {
        clean = `0${clean}`;
    }

    expiry.value =
        clean.length > 2 ? `${clean.slice(0, 2)}/${clean.slice(2)}` : clean;
}

function onCvv(value: string | number) {
    cvv.value = String(value).replace(/\D/g, '').slice(0, 4);
}

const expiryParts = computed(() => {
    const [mm = '', yy = ''] = expiry.value.split('/');

    return { mm, yy };
});

const errors = computed(() => {
    const out: Record<string, string | null> = {
        holder: null,
        number: null,
        expiry: null,
        cvv: null,
    };

    if (holder.value.trim().length < 3) {
        out.holder = 'Escribe el nombre como aparece en la tarjeta.';
    }

    if (!luhnValid(digits.value) || digits.value.length > maxDigits.value) {
        out.number = 'Revisa el número de la tarjeta.';
    }

    const { mm, yy } = expiryParts.value;
    const month = Number(mm);
    const year = 2000 + Number(yy);
    const now = new Date();

    if (mm.length !== 2 || yy.length !== 2 || month < 1 || month > 12) {
        out.expiry = 'Usa el formato MM/AA.';
    } else if (
        year < now.getFullYear() ||
        (year === now.getFullYear() && month < now.getMonth() + 1)
    ) {
        out.expiry = 'La tarjeta está vencida.';
    }

    if (cvv.value.length !== cvvLength.value) {
        out.cvv = `El código tiene ${cvvLength.value} dígitos.`;
    }

    return out;
});

const isValid = computed(() =>
    Object.values(errors.value).every((e) => e === null),
);

function shown(field: string): string | null {
    return touched.value[field] || submitted.value ? errors.value[field] : null;
}

function valid(field: string): boolean {
    return (touched.value[field] ?? false) && errors.value[field] === null;
}

function blur(field: string) {
    touched.value[field] = true;
    focused.value = null;
}

// Vista previa (solo visual, aria-hidden): el número se agrupa y se rellena con •.
const previewNumber = computed(() => {
    const template =
        brand.value === 'amex' ? '•••• •••••• •••••' : '•••• •••• •••• ••••';
    const typed = numberText.value;

    return typed + template.slice(typed.length);
});
const previewHolder = computed(
    () => holder.value.trim().toUpperCase() || 'NOMBRE DEL TITULAR',
);
const previewExpiry = computed(() => expiry.value || 'MM/AA');
const flipped = computed(() => focused.value === 'cvv');

// ---------------------------------------------------------------- Cobro

type Phase =
    | 'idle'
    | 'tokenizing'
    | 'charging'
    | 'redirecting'
    | 'waiting'
    | 'paid';
const phase = ref<Phase>('idle');
const submitted = ref(false);
const message = ref<{ tone: 'danger' | 'info'; text: string } | null>(null);
const shake = ref(0);
let attemptKey = uuid();

const busy = computed(() => phase.value !== 'idle');
const amountText = computed(() => formatMoney(props.amount) ?? '');

const buttonLabel = computed(() => {
    switch (phase.value) {
        case 'tokenizing':
            return 'Cifrando tu tarjeta…';
        case 'charging':
            return 'Procesando pago…';
        case 'redirecting':
            return 'Conectando con tu banco…';
        case 'waiting':
            return 'Confirmando pago…';
        case 'paid':
            return 'Pago confirmado';
        default:
            return `Pagar ${amountText.value}`.trim();
    }
});

function clearSensitive() {
    cvv.value = '';
}

function fail(text: string, tone: 'danger' | 'info' = 'danger') {
    phase.value = 'idle';
    message.value = { tone, text };
    shake.value++;
}

async function submit() {
    if (busy.value || props.disabled || !ready.value) {
        return;
    }

    submitted.value = true;
    message.value = null;

    if (!isValid.value) {
        fail('Revisa los datos marcados de tu tarjeta.');

        return;
    }

    phase.value = 'tokenizing';
    let tokenId: string;

    try {
        const openpay = await loadOpenpay();
        tokenId = await createToken(openpay, {
            holder_name: holder.value.trim(),
            card_number: digits.value,
            expiration_month: expiryParts.value.mm,
            expiration_year: expiryParts.value.yy,
            cvv2: cvv.value,
        });
    } catch (error) {
        fail(tokenErrorMessage(error));

        return;
    }

    // El token es de un solo uso: borramos el CVV de memoria de inmediato.
    clearSensitive();
    phase.value = 'charging';

    let result: ChargeResult;

    try {
        const response = await apiFetch(props.chargeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token_id: tokenId,
                device_session_id: deviceSessionId,
                attempt_key: attemptKey,
            }),
        });

        if (response.status === 419) {
            fail(
                'Tu sesión expiró. Recarga la página para continuar; no se realizó ningún cargo.',
            );

            return;
        }

        if (response.status === 403) {
            fail(
                'Este enlace de pago ya no es válido. Vuelve a abrirlo desde tu correo o agenda de nuevo.',
            );

            return;
        }

        if (response.status === 429) {
            fail(
                'Demasiados intentos seguidos. Espera un minuto antes de volver a intentarlo.',
            );

            return;
        }

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        result = (await response.json()) as ChargeResult;
    } catch {
        // Resultado desconocido (red): conservamos la clave del intento para no cobrar dos veces.
        fail(
            'No recibimos respuesta del servidor. Antes de volver a intentar, revisa el estado de tu pago: si se cobró, lo verás confirmado en unos segundos.',
            'info',
        );

        return;
    }

    handle(result);
}

function handle(result: ChargeResult) {
    switch (result.status) {
        case 'paid':
            phase.value = 'paid';
            emit('paid', result);
            if (props.followNext && result.nextUrl) {
                const next = result.nextUrl;
                // Deja ver la confirmación antes de continuar.
                setTimeout(() => router.visit(next), 1400);
            }
            break;

        case 'redirect':
            if (result.redirectUrl) {
                phase.value = 'redirecting';
                window.location.assign(result.redirectUrl);
            } else {
                phase.value = 'waiting';
            }
            break;

        case 'processing':
            phase.value = 'waiting';
            message.value = { tone: 'info', text: result.message };
            emit('processing', result);
            if (props.followNext && result.nextUrl) {
                router.visit(result.nextUrl);
            }
            break;

        default:
            attemptKey = uuid();
            fail(result.message);
            emit('failed', result);
            if (props.followNext && result.nextUrl) {
                router.visit(result.nextUrl);
            }
    }
}

const fieldClass = 'h-12 text-base md:text-sm';
</script>

<template>
    <div class="relative space-y-5" :data-payment="paymentUuid">
        <div
            v-if="config?.sandbox"
            class="rounded-lg border border-warning/30 bg-warning-soft px-3 py-2 text-xs text-warning-ink"
        >
            Modo de prueba (sandbox): usa una tarjeta de prueba de Openpay, por
            ejemplo 4111 1111 1111 1111. No se realizan cargos reales.
        </div>

        <div
            v-if="loadError"
            class="space-y-3 rounded-lg border border-danger/25 bg-danger-soft p-4 text-sm text-danger-ink"
            role="alert"
        >
            <p class="flex gap-2">
                <CircleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />{{ loadError }}
            </p>
            <Button type="button" variant="outline" size="sm" @click="init">
                <RefreshCw aria-hidden="true" />
                Reintentar
            </Button>
        </div>

        <template v-else>
            <!-- Vista previa de la tarjeta (decorativa; los datos no salen del navegador salvo a Openpay). -->
            <div
                v-if="showPreview"
                class="card-scene mx-auto w-full max-w-[22rem]"
                aria-hidden="true"
            >
                <div :class="cn('card-inner', flipped && 'is-flipped')">
                    <div
                        :class="
                            cn(
                                'card-face card-front',
                                brand && `brand-${brand}`,
                            )
                        "
                    >
                        <div class="flex items-start justify-between">
                            <span class="card-chip" />
                            <span
                                class="text-xs font-semibold tracking-wide text-white/90 uppercase"
                            >
                                {{ brand ? BRAND_LABEL[brand] : 'Tarjeta' }}
                            </span>
                        </div>
                        <p
                            class="mt-auto font-mono text-[1.05rem] tracking-[0.12em] text-white tabular-nums sm:text-lg"
                        >
                            {{ previewNumber }}
                        </p>
                        <div
                            class="mt-3 flex items-end justify-between gap-3 text-white/90"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-[0.6rem] tracking-wider text-white/60 uppercase"
                                >
                                    Titular
                                </p>
                                <p
                                    class="truncate text-xs font-medium tracking-wide"
                                >
                                    {{ previewHolder }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p
                                    class="text-[0.6rem] tracking-wider text-white/60 uppercase"
                                >
                                    Vence
                                </p>
                                <p class="font-mono text-xs">
                                    {{ previewExpiry }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        :class="
                            cn('card-face card-back', brand && `brand-${brand}`)
                        "
                    >
                        <span class="card-stripe" />
                        <div class="mt-5 flex items-center gap-3 px-1">
                            <span class="card-signature" />
                            <span
                                class="rounded bg-white px-2 py-1 font-mono text-sm text-neutral-800"
                                >{{ '•'.repeat(cvv.length || cvvLength) }}</span
                            >
                        </div>
                        <p class="mt-auto text-[0.65rem] text-white/70">
                            El código de seguridad nunca se guarda.
                        </p>
                    </div>
                </div>
            </div>

            <form
                :key="`form-${shake}`"
                :class="cn('space-y-4', shake > 0 && 'form-shake')"
                novalidate
                autocomplete="on"
                @submit.prevent="submit"
            >
                <div class="space-y-2">
                    <Label for="cc-number">Número de tarjeta</Label>
                    <div class="relative">
                        <Input
                            id="cc-number"
                            :model-value="numberText"
                            inputmode="numeric"
                            autocomplete="cc-number"
                            placeholder="0000 0000 0000 0000"
                            :class="
                                cn(
                                    fieldClass,
                                    'pr-12 font-mono tracking-wider',
                                    valid('number') &&
                                        'border-success/60 focus-visible:ring-success/30',
                                )
                            "
                            :disabled="busy"
                            :aria-invalid="!!shown('number')"
                            aria-describedby="cc-number-error cc-number-brand"
                            @update:model-value="onNumber"
                            @focus="focused = 'number'"
                            @blur="blur('number')"
                        />
                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center"
                        >
                            <Check
                                v-if="valid('number')"
                                class="field-ok size-4 text-success"
                                aria-hidden="true"
                            />
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <InputError
                            id="cc-number-error"
                            :message="shown('number') ?? undefined"
                        />
                        <span
                            id="cc-number-brand"
                            class="sr-only"
                            aria-live="polite"
                            >{{ brand ? BRAND_LABEL[brand] : '' }}</span
                        >
                        <CardBrandMarks :active="brand" class="ml-auto" />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="cc-name">Nombre del titular</Label>
                    <div class="relative">
                        <Input
                            id="cc-name"
                            v-model="holder"
                            autocomplete="cc-name"
                            autocapitalize="characters"
                            spellcheck="false"
                            maxlength="80"
                            placeholder="Como aparece en la tarjeta"
                            :class="
                                cn(
                                    fieldClass,
                                    'pr-10',
                                    valid('holder') && 'border-success/60',
                                )
                            "
                            :disabled="busy"
                            :aria-invalid="!!shown('holder')"
                            aria-describedby="cc-name-error"
                            @focus="focused = 'holder'"
                            @blur="blur('holder')"
                        />
                        <Check
                            v-if="valid('holder')"
                            class="field-ok pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-success"
                            aria-hidden="true"
                        />
                    </div>
                    <InputError
                        id="cc-name-error"
                        :message="shown('holder') ?? undefined"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <Label for="cc-exp">Vencimiento</Label>
                        <div class="relative">
                            <Input
                                id="cc-exp"
                                :model-value="expiry"
                                inputmode="numeric"
                                autocomplete="cc-exp"
                                placeholder="MM/AA"
                                maxlength="5"
                                :class="
                                    cn(
                                        fieldClass,
                                        'pr-10 font-mono',
                                        valid('expiry') && 'border-success/60',
                                    )
                                "
                                :disabled="busy"
                                :aria-invalid="!!shown('expiry')"
                                aria-describedby="cc-exp-error"
                                @update:model-value="onExpiry"
                                @focus="focused = 'expiry'"
                                @blur="blur('expiry')"
                            />
                            <Check
                                v-if="valid('expiry')"
                                class="field-ok pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-success"
                                aria-hidden="true"
                            />
                        </div>
                        <InputError
                            id="cc-exp-error"
                            :message="shown('expiry') ?? undefined"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="cc-cvv">Código de seguridad</Label>
                        <div class="relative">
                            <Input
                                id="cc-cvv"
                                :model-value="cvv"
                                type="password"
                                inputmode="numeric"
                                autocomplete="cc-csc"
                                :placeholder="
                                    cvvLength === 4 ? '4 dígitos' : '3 dígitos'
                                "
                                :maxlength="cvvLength"
                                :class="
                                    cn(
                                        fieldClass,
                                        'pr-10 font-mono',
                                        valid('cvv') && 'border-success/60',
                                    )
                                "
                                :disabled="busy"
                                :aria-invalid="!!shown('cvv')"
                                aria-describedby="cc-cvv-error cc-cvv-help"
                                @update:model-value="onCvv"
                                @focus="focused = 'cvv'"
                                @blur="blur('cvv')"
                            />
                            <Check
                                v-if="valid('cvv')"
                                class="field-ok pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-success"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            id="cc-cvv-help"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                brand === 'amex'
                                    ? 'Al frente de la tarjeta.'
                                    : 'Al reverso de la tarjeta.'
                            }}
                        </p>
                        <InputError
                            id="cc-cvv-error"
                            :message="shown('cvv') ?? undefined"
                        />
                    </div>
                </div>

                <Transition name="msg">
                    <div
                        v-if="message"
                        :class="
                            cn(
                                'flex gap-2 rounded-lg border p-3 text-sm',
                                message.tone === 'danger'
                                    ? 'border-danger/25 bg-danger-soft text-danger-ink'
                                    : 'border-info/25 bg-info-soft text-info-ink',
                            )
                        "
                        role="alert"
                        aria-live="assertive"
                    >
                        <CircleAlert
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{{ message.text }}</span>
                    </div>
                </Transition>

                <Button
                    type="submit"
                    :class="
                        cn(
                            'pay-button relative h-12 w-full overflow-hidden text-base',
                            busy && phase !== 'paid' && 'is-busy',
                            phase === 'paid' &&
                                'bg-success text-success-foreground hover:bg-success',
                        )
                    "
                    :disabled="busy || disabled || !ready"
                    :aria-busy="busy"
                >
                    <Loader2
                        v-if="busy && phase !== 'paid'"
                        class="animate-spin"
                        aria-hidden="true"
                    />
                    <ShieldCheck
                        v-else-if="phase === 'paid'"
                        aria-hidden="true"
                    />
                    <Lock v-else aria-hidden="true" />
                    {{
                        ready || loadError
                            ? buttonLabel
                            : 'Cargando pago seguro…'
                    }}
                </Button>

                <p class="sr-only" aria-live="polite">
                    {{ busy ? buttonLabel : '' }}
                </p>

                <div
                    class="flex items-start gap-2.5 rounded-lg bg-muted/60 p-3 text-xs leading-relaxed text-muted-foreground"
                >
                    <ShieldCheck
                        class="mt-0.5 size-4 shrink-0 text-success"
                        aria-hidden="true"
                    />
                    <p>
                        <strong class="font-semibold text-foreground"
                            >No guardamos los datos de tu tarjeta.</strong
                        >
                        Se cifran en tu navegador y van directo a Openpay para
                        un solo cargo; ML Habitat nunca los ve.
                        <template v-if="description">
                            {{ description }}.</template
                        >
                        Tu banco puede pedirte autorizar la compra (3D Secure).
                    </p>
                </div>
            </form>
        </template>

        <!-- Confirmación animada (solo cuando el backend confirmó el pago). -->
        <Transition name="success">
            <div
                v-if="phase === 'paid'"
                class="absolute inset-0 z-10 grid place-items-center rounded-xl bg-card/95 backdrop-blur-sm"
                role="status"
                aria-live="polite"
            >
                <div class="text-center">
                    <svg
                        class="success-check mx-auto size-20"
                        viewBox="0 0 52 52"
                        aria-hidden="true"
                    >
                        <circle
                            class="success-circle"
                            cx="26"
                            cy="26"
                            r="24"
                            fill="none"
                        />
                        <path
                            class="success-tick"
                            fill="none"
                            d="M15 27l7 7 15-15"
                        />
                    </svg>
                    <p
                        class="mt-4 font-display text-xl font-semibold tracking-tight"
                    >
                        ¡Pago confirmado!
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ amountText }} · Te enviamos el comprobante por
                        correo.
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* ---------------------------------------------------------------- Tarjeta */
.card-scene {
    perspective: 1200px;
}

.card-inner {
    position: relative;
    aspect-ratio: 1.586;
    transform-style: preserve-3d;
    transition: transform 650ms cubic-bezier(0.22, 1, 0.36, 1);
}

.card-inner.is-flipped {
    transform: rotateY(180deg);
}

.card-face {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    padding: 1.1rem 1.25rem;
    border-radius: 1rem;
    backface-visibility: hidden;
    overflow: hidden;
    color: #fff;
    background:
        radial-gradient(
            120% 90% at 100% 0%,
            rgb(255 255 255 / 0.18),
            transparent 55%
        ),
        linear-gradient(135deg, #3b3a36 0%, #1f1e1b 100%);
    box-shadow:
        0 18px 40px -18px rgb(0 0 0 / 0.55),
        inset 0 1px 0 rgb(255 255 255 / 0.12);
    transition: background 500ms ease;
}

.card-face.brand-visa {
    background:
        radial-gradient(
            120% 90% at 100% 0%,
            rgb(255 255 255 / 0.2),
            transparent 55%
        ),
        linear-gradient(135deg, #2a3b8f 0%, #141b4d 100%);
}

.card-face.brand-mastercard {
    background:
        radial-gradient(
            120% 90% at 100% 0%,
            rgb(255 255 255 / 0.16),
            transparent 55%
        ),
        linear-gradient(135deg, #3d3d3d 0%, #8a3b12 120%);
}

.card-face.brand-amex {
    background:
        radial-gradient(
            120% 90% at 100% 0%,
            rgb(255 255 255 / 0.2),
            transparent 55%
        ),
        linear-gradient(135deg, #2e77bc 0%, #1b4a7a 100%);
}

.card-face.brand-carnet {
    background:
        radial-gradient(
            120% 90% at 100% 0%,
            rgb(255 255 255 / 0.18),
            transparent 55%
        ),
        linear-gradient(135deg, #0b7a43 0%, #064026 100%);
}

.card-back {
    transform: rotateY(180deg);
    padding-inline: 0;
}

.card-back > :not(.card-stripe) {
    margin-inline: 1.25rem;
}

.card-chip {
    width: 2.4rem;
    height: 1.8rem;
    border-radius: 0.4rem;
    background: linear-gradient(135deg, #e9d18b, #b8913f);
    box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.15);
}

.card-stripe {
    display: block;
    height: 2.4rem;
    margin-top: 0.6rem;
    background: rgb(0 0 0 / 0.75);
}

.card-signature {
    flex: 1;
    height: 2rem;
    border-radius: 0.25rem;
    background: repeating-linear-gradient(
        -45deg,
        rgb(255 255 255 / 0.85) 0 6px,
        rgb(235 235 235 / 0.85) 6px 12px
    );
}

/* ---------------------------------------------------------------- Campos */
.field-ok {
    animation: pop-in 260ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.form-shake {
    animation: shake 380ms ease;
}

.pay-button.is-busy::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        100deg,
        transparent 20%,
        rgb(255 255 255 / 0.22) 50%,
        transparent 80%
    );
    transform: translateX(-100%);
    animation: shimmer 1.3s ease-in-out infinite;
}

.msg-enter-active,
.msg-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}

.msg-enter-from,
.msg-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* ---------------------------------------------------------------- Éxito */
.success-enter-active {
    transition: opacity 260ms ease;
}

.success-enter-from {
    opacity: 0;
}

.success-circle {
    stroke: var(--color-success);
    stroke-width: 3;
    stroke-dasharray: 151;
    stroke-dashoffset: 151;
    animation: draw 600ms cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.success-tick {
    stroke: var(--color-success);
    stroke-width: 4;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 36;
    stroke-dashoffset: 36;
    animation: draw 380ms cubic-bezier(0.65, 0, 0.45, 1) 480ms forwards;
}

.success-check {
    animation: pop-in 500ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@keyframes draw {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes pop-in {
    from {
        opacity: 0;
        transform: scale(0.6);
    }
}

@keyframes shake {
    0%,
    100% {
        transform: translateX(0);
    }
    20%,
    60% {
        transform: translateX(-6px);
    }
    40%,
    80% {
        transform: translateX(6px);
    }
}

@keyframes shimmer {
    to {
        transform: translateX(100%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .card-inner,
    .card-face {
        transition: none;
    }

    .field-ok,
    .form-shake,
    .success-check,
    .pay-button.is-busy::after {
        animation: none;
    }

    .success-circle,
    .success-tick {
        animation: none;
        stroke-dashoffset: 0;
    }
}
</style>
