<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    CircleAlert,
    CreditCard,
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
import { apiFetch } from '@/lib/http';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
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
 * Formulario de pago con tarjeta (Openpay.js). Reutilizable: visitas y
 * anticipos del portal.
 *
 *  - Los datos de la tarjeta NUNCA se envían a nuestro servidor: se
 *    tokenizan en el navegador y a `chargeUrl` solo se envían
 *    { token_id, device_session_id, attempt_key }.
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
    }>(),
    {
        followNext: true,
        disabled: false,
        openpay: null,
        description: undefined,
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
let attemptKey = uuid();

const busy = computed(() => phase.value !== 'idle');

const buttonLabel = computed(() => {
    switch (phase.value) {
        case 'tokenizing':
            return 'Validando tarjeta…';
        case 'charging':
            return 'Procesando pago…';
        case 'redirecting':
            return 'Conectando con tu banco…';
        case 'waiting':
            return 'Confirmando pago…';
        case 'paid':
            return 'Pago confirmado';
        default:
            return `Pagar ${formatMoney(props.amount) ?? ''}`.trim();
    }
});

function clearSensitive() {
    cvv.value = '';
}

async function submit() {
    if (busy.value || props.disabled || !ready.value) {
        return;
    }

    submitted.value = true;
    message.value = null;

    if (!isValid.value) {
        message.value = {
            tone: 'danger',
            text: 'Revisa los datos marcados de tu tarjeta.',
        };

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
        phase.value = 'idle';
        message.value = { tone: 'danger', text: tokenErrorMessage(error) };

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
            phase.value = 'idle';
            message.value = {
                tone: 'danger',
                text: 'Tu sesión expiró. Recarga la página para continuar; no se realizó ningún cargo.',
            };

            return;
        }

        if (response.status === 403) {
            phase.value = 'idle';
            message.value = {
                tone: 'danger',
                text: 'Este enlace de pago ya no es válido. Vuelve a abrirlo desde tu correo o agenda de nuevo.',
            };

            return;
        }

        if (response.status === 429) {
            phase.value = 'idle';
            message.value = {
                tone: 'danger',
                text: 'Demasiados intentos seguidos. Espera un minuto antes de volver a intentarlo.',
            };

            return;
        }

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        result = (await response.json()) as ChargeResult;
    } catch {
        // Resultado desconocido (red): conservamos la clave del intento para no cobrar dos veces.
        phase.value = 'idle';
        message.value = {
            tone: 'info',
            text: 'No recibimos respuesta del servidor. Antes de volver a intentar, revisa el estado de tu pago: si se cobró, lo verás confirmado en unos segundos.',
        };

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
                router.visit(result.nextUrl);
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
            phase.value = 'idle';
            attemptKey = uuid();
            message.value = { tone: 'danger', text: result.message };
            emit('failed', result);
            if (props.followNext && result.nextUrl) {
                router.visit(result.nextUrl);
            }
    }
}

const fieldClass = 'h-12 text-base md:text-sm';
</script>

<template>
    <div class="space-y-5" :data-payment="paymentUuid">
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

        <form
            v-else
            class="space-y-4"
            novalidate
            autocomplete="on"
            @submit.prevent="submit"
        >
            <div class="space-y-2">
                <Label for="cc-name">Nombre del titular</Label>
                <Input
                    id="cc-name"
                    v-model="holder"
                    autocomplete="cc-name"
                    autocapitalize="characters"
                    spellcheck="false"
                    maxlength="80"
                    placeholder="Como aparece en la tarjeta"
                    :class="fieldClass"
                    :disabled="busy"
                    :aria-invalid="!!shown('holder')"
                    aria-describedby="cc-name-error"
                    @blur="touched.holder = true"
                />
                <InputError
                    id="cc-name-error"
                    :message="shown('holder') ?? undefined"
                />
            </div>

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
                            cn(fieldClass, 'pr-28 font-mono tracking-wider')
                        "
                        :disabled="busy"
                        :aria-invalid="!!shown('number')"
                        aria-describedby="cc-number-error cc-number-brand"
                        @update:model-value="onNumber"
                        @blur="touched.number = true"
                    />
                    <span
                        id="cc-number-brand"
                        class="pointer-events-none absolute inset-y-0 right-3 flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                        aria-live="polite"
                    >
                        <CreditCard class="size-4" aria-hidden="true" />
                        {{ brand ? BRAND_LABEL[brand] : '' }}
                    </span>
                </div>
                <InputError
                    id="cc-number-error"
                    :message="shown('number') ?? undefined"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-2">
                    <Label for="cc-exp">Vencimiento</Label>
                    <Input
                        id="cc-exp"
                        :model-value="expiry"
                        inputmode="numeric"
                        autocomplete="cc-exp"
                        placeholder="MM/AA"
                        maxlength="5"
                        :class="cn(fieldClass, 'font-mono')"
                        :disabled="busy"
                        :aria-invalid="!!shown('expiry')"
                        aria-describedby="cc-exp-error"
                        @update:model-value="onExpiry"
                        @blur="touched.expiry = true"
                    />
                    <InputError
                        id="cc-exp-error"
                        :message="shown('expiry') ?? undefined"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="cc-cvv">Código de seguridad</Label>
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
                        :class="cn(fieldClass, 'font-mono')"
                        :disabled="busy"
                        :aria-invalid="!!shown('cvv')"
                        aria-describedby="cc-cvv-error cc-cvv-help"
                        @update:model-value="onCvv"
                        @blur="touched.cvv = true"
                    />
                    <p id="cc-cvv-help" class="text-xs text-muted-foreground">
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

            <Button
                type="submit"
                class="h-12 w-full text-base"
                :disabled="busy || disabled || !ready"
                :aria-busy="busy"
            >
                <Loader2
                    v-if="busy && phase !== 'paid'"
                    class="animate-spin"
                    aria-hidden="true"
                />
                <ShieldCheck v-else-if="phase === 'paid'" aria-hidden="true" />
                <Lock v-else aria-hidden="true" />
                {{ ready || loadError ? buttonLabel : 'Cargando pago seguro…' }}
            </Button>

            <p class="sr-only" aria-live="polite">
                {{ busy ? buttonLabel : '' }}
            </p>

            <p
                class="flex items-start gap-2 text-xs leading-relaxed text-muted-foreground"
            >
                <Lock class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                <span>
                    Pago procesado por Openpay. Los datos de tu tarjeta se
                    cifran y van directo a Openpay; ML Habitat nunca los ve ni
                    los guarda.
                    <template v-if="description"> {{ description }}.</template>
                    Tu banco puede pedirte autorizar la compra (3D Secure).
                </span>
            </p>
        </form>
    </div>
</template>
