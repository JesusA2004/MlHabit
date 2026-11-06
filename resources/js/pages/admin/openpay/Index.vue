<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    CircleDashed,
    CircleMinus,
    Copy,
    CreditCard,
    ExternalLink,
    KeyRound,
    ListChecks,
    PlugZap,
    RotateCcw,
    Webhook,
    WandSparkles,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type Option = { value: string; label: string; tone: string };
type Source = 'panel' | 'env' | null;
type Step = {
    key: string;
    title: string;
    description: string;
    status: 'done' | 'pending' | 'warning' | 'optional';
};

const props = defineProps<{
    environment: 'sandbox' | 'production';
    canEdit: boolean;
    configured: boolean;
    webhookConfigured: boolean;
    credentials: {
        merchantId: { value: string; source: Source; panelValue: string };
        publicKey: { value: string; source: Source; panelValue: string };
        privateKey: { masked: string | null; source: Source };
        webhookUser: { value: string; source: Source; panelValue: string };
        webhookPassword: { masked: string | null; source: Source };
        production: { value: boolean; source: Source };
        threeDsMode: { value: string; source: Source };
        updatedAt: string | null;
        updatedBy: string | null;
        hasPanelValues: boolean;
        unreadable: boolean;
    };
    threeDsModes: { value: string; label: string; description: string }[];
    webhookUrl: string;
    webhookEvents: string[];
    webhook: {
        state:
            | 'missing_credentials'
            | 'not_registered'
            | 'awaiting_verification'
            | 'verified';
        code: string | null;
        codeReceivedAt: string | null;
        lastEventAt: string | null;
    };
    dashboardUrl: string;
    checklist: Step[];
    progress: { done: number; required: number };
    testCards: { number: string; brand: string; result: string }[];
    lastWebhook: {
        type: string;
        status: Option;
        receivedAt: string | null;
        error: string | null;
    } | null;
    lastError: { source: string; message: string; at: string | null } | null;
    lastPing: {
        ok: boolean;
        message: string;
        httpStatus: number | null;
        errorCode: string | null;
        latencyMs: number | null;
        at: string;
        current: boolean;
    } | null;
    urls: {
        update: string;
        reset: string;
        test: string;
        settings: string;
        payments: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Openpay', href: '/admin/openpay' },
        ],
    },
});

const tone = (o: { tone?: string } | null | undefined) =>
    (o?.tone ?? 'neutral') as Tone;

const stepIcon: Record<Step['status'], Component> = {
    done: CircleCheck,
    pending: CircleDashed,
    warning: CircleAlert,
    optional: CircleMinus,
};
const stepClass: Record<Step['status'], string> = {
    done: 'text-success',
    pending: 'text-muted-foreground',
    warning: 'text-warning',
    optional: 'text-muted-foreground/70',
};
const stepLabel: Record<Step['status'], string> = {
    done: 'Listo',
    pending: 'Pendiente',
    warning: 'Revisar',
    optional: 'Opcional',
};

const sourceText = (s: Source) =>
    s === 'panel'
        ? 'Guardado en el panel'
        : s === 'env'
          ? 'Tomado del archivo .env'
          : 'Sin configurar';

// ---------------------------------------------------------------- Credenciales

const replacePrivate = ref(!props.credentials.privateKey.masked);
const replacePassword = ref(!props.credentials.webhookPassword.masked);

const form = useForm({
    merchant_id: props.credentials.merchantId.panelValue,
    public_key: props.credentials.publicKey.panelValue,
    private_key: '',
    webhook_user: props.credentials.webhookUser.panelValue,
    webhook_password: '',
    production: props.credentials.production.value,
    three_ds_mode: props.credentials.threeDsMode.value,
});

const switchingToProduction = computed(
    () => form.production && props.environment !== 'production',
);

function save() {
    form.transform((data) => {
        const payload: Record<string, unknown> = {
            merchant_id: data.merchant_id,
            public_key: data.public_key,
            webhook_user: data.webhook_user,
            production: data.production,
            three_ds_mode: data.three_ds_mode,
        };

        if (replacePrivate.value && data.private_key) {
            payload.private_key = data.private_key;
        }

        if (replacePassword.value && data.webhook_password) {
            payload.webhook_password = data.webhook_password;
        }

        return payload;
    }).put(props.urls.update, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('private_key', 'webhook_password');
            replacePrivate.value = !props.credentials.privateKey.masked;
            replacePassword.value = !props.credentials.webhookPassword.masked;
        },
    });
}

function generatePassword() {
    const alphabet =
        'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    const bytes = new Uint32Array(32);
    crypto.getRandomValues(bytes);
    form.webhook_password = Array.from(
        bytes,
        (b) => alphabet[b % alphabet.length],
    ).join('');
    replacePassword.value = true;
}

function generateUser() {
    const bytes = new Uint8Array(4);
    crypto.getRandomValues(bytes);
    form.webhook_user = `mlhabitat-${Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')}`;
}

const resetOpen = ref(false);

function reset(): Promise<void> {
    return new Promise((resolve, reject) => {
        router.delete(props.urls.reset, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo restablecer.')),
        });
    });
}

// ---------------------------------------------------------------- Conexión

const testing = ref(false);

function test() {
    router.post(
        props.urls.test,
        {},
        {
            preserveScroll: true,
            onStart: () => (testing.value = true),
            onFinish: () => (testing.value = false),
        },
    );
}

async function copy(text: string, label: string) {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(`${label} copiado.`);
    } catch {
        toast.error(
            'No se pudo copiar. Selecciona el texto y cópialo manualmente.',
        );
    }
}

const webhookBadge = computed<{ tone: Tone; label: string }>(() => {
    switch (props.webhook.state) {
        case 'verified':
            return { tone: 'success', label: 'Verificado' };
        case 'awaiting_verification':
            return { tone: 'warning', label: 'Falta verificar' };
        case 'missing_credentials':
            return { tone: 'danger', label: 'Sin credenciales' };
        default:
            return { tone: 'neutral', label: 'No registrado' };
    }
});

const progressPercent = computed(() =>
    props.progress.required > 0
        ? Math.round((props.progress.done / props.progress.required) * 100)
        : 0,
);
</script>

<template>
    <Head title="Openpay" />

    <PageContainer width="narrow">
        <PageHeader
            title="Cobros con tarjeta (Openpay)"
            description="Configura tu cuenta de Openpay, verifica el webhook y prueba la conexión. Las llaves secretas se guardan cifradas y nunca se vuelven a mostrar."
        >
            <template #meta>
                <StatusBadge
                    :tone="environment === 'production' ? 'success' : 'warning'"
                    :label="
                        environment === 'production'
                            ? 'Producción (cobros reales)'
                            : 'Sandbox (pruebas)'
                    "
                />
            </template>
            <template #actions>
                <Button :disabled="testing || !configured" @click="test">
                    <Spinner v-if="testing" /><PlugZap v-else class="size-4" />
                    Probar conexión
                </Button>
            </template>
        </PageHeader>

        <FormStatus
            v-if="credentials.unreadable"
            tone="danger"
            message="Hay credenciales guardadas que ya no se pueden leer (cambió la APP_KEY del servidor). Se están usando las del .env; vuelve a capturarlas abajo."
        />
        <FormStatus
            v-else-if="!configured"
            tone="danger"
            message="Faltan llaves: los pagos con tarjeta están desactivados. Captúralas abajo y pulsa Probar conexión."
        />
        <FormStatus
            v-else-if="!webhookConfigured"
            tone="info"
            message="Los cobros funcionan, pero el webhook no tiene usuario y contraseña: Openpay no podrá avisarnos de reembolsos ni contracargos."
        />

        <!-- Pasos para activar cobros -->
        <SectionCard
            title="Pasos para activar cobros"
            description="Se marcan solos conforme avanzas. La guía completa está en docs/pagos/GUIA-ACTIVACION-OPENPAY.md."
            :icon="ListChecks"
        >
            <div class="mb-5">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium"
                        >{{ progress.done }} de {{ progress.required }} pasos
                        listos</span
                    >
                    <span class="text-muted-foreground tabular-nums"
                        >{{ progressPercent }}%</span
                    >
                </div>
                <div
                    class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    :aria-valuenow="progressPercent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div
                        class="h-full rounded-full bg-success transition-[width] duration-700"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
            </div>
            <ol class="divide-y">
                <li
                    v-for="(step, i) in checklist"
                    :key="step.key"
                    class="flex gap-3 py-3"
                >
                    <component
                        :is="stepIcon[step.status]"
                        :class="
                            cn('mt-0.5 size-5 shrink-0', stepClass[step.status])
                        "
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium"
                                >{{ i + 1 }}. {{ step.title }}</span
                            >
                            <span
                                :class="
                                    cn(
                                        'text-xs',
                                        step.status === 'done'
                                            ? 'text-success-ink'
                                            : step.status === 'warning'
                                              ? 'text-warning-ink'
                                              : 'text-muted-foreground',
                                    )
                                "
                                >{{ stepLabel[step.status] }}</span
                            >
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ step.description }}
                        </p>
                    </div>
                </li>
            </ol>
        </SectionCard>

        <!-- Credenciales -->
        <SectionCard
            title="Credenciales de Openpay"
            description="Las encuentras en el dashboard de Openpay, en la sección de credenciales o llaves de API (ID, llave privada y llave pública). Lo que guardes aquí tiene prioridad sobre el archivo .env."
            :icon="KeyRound"
        >
            <form
                v-if="canEdit"
                id="form-openpay"
                class="grid gap-6"
                @submit.prevent="save"
            >
                <div class="grid gap-3 rounded-xl border bg-muted/40 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div class="grid gap-1">
                            <Label for="production"
                                >Ambiente de producción</Label
                            >
                            <p class="text-sm text-muted-foreground">
                                Apagado = sandbox (pruebas, sin dinero real).
                                Enciéndelo solo con llaves de producción.
                            </p>
                        </div>
                        <Switch id="production" v-model="form.production" />
                    </div>
                    <p
                        v-if="switchingToProduction"
                        class="rounded-lg border border-warning/30 bg-warning-soft p-3 text-sm text-warning-ink"
                    >
                        Vas a cobrar con dinero real. Reemplaza también las tres
                        llaves por las de producción (son distintas a las de
                        sandbox) y registra de nuevo el webhook en el dashboard
                        de producción.
                    </p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="merchant_id"
                            >ID de comercio (Merchant ID)</Label
                        >
                        <Input
                            id="merchant_id"
                            v-model="form.merchant_id"
                            autocomplete="off"
                            spellcheck="false"
                            class="font-mono"
                            :placeholder="
                                credentials.merchantId.source === 'env'
                                    ? `Del .env: ${credentials.merchantId.value}`
                                    : 'mxxxxxxxxxxxxxxxxxxx'
                            "
                            :aria-invalid="!!form.errors.merchant_id"
                        />
                        <InputError :message="form.errors.merchant_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="public_key">Llave pública</Label>
                        <Input
                            id="public_key"
                            v-model="form.public_key"
                            autocomplete="off"
                            spellcheck="false"
                            class="font-mono"
                            :placeholder="
                                credentials.publicKey.source === 'env'
                                    ? 'Tomada del .env'
                                    : 'pk_…'
                            "
                            :aria-invalid="!!form.errors.public_key"
                        />
                        <InputError :message="form.errors.public_key" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="private_key">Llave privada</Label>
                    <div
                        v-if="!replacePrivate && credentials.privateKey.masked"
                        class="flex flex-wrap items-center gap-3"
                    >
                        <code
                            class="rounded-md border bg-muted/50 px-3 py-2 font-mono text-sm"
                            >sk_{{ credentials.privateKey.masked }}</code
                        >
                        <span class="text-xs text-muted-foreground">{{
                            sourceText(credentials.privateKey.source)
                        }}</span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="replacePrivate = true"
                            >Reemplazar</Button
                        >
                    </div>
                    <template v-else>
                        <Input
                            id="private_key"
                            v-model="form.private_key"
                            type="password"
                            autocomplete="new-password"
                            spellcheck="false"
                            class="font-mono"
                            placeholder="sk_…"
                            :aria-invalid="!!form.errors.private_key"
                        />
                        <p class="text-xs text-muted-foreground">
                            Se guarda cifrada. Después solo verás los últimos 4
                            caracteres. Nunca la compartas por correo o chat.
                        </p>
                        <Button
                            v-if="credentials.privateKey.masked"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="justify-self-start"
                            @click="
                                replacePrivate = false;
                                form.private_key = '';
                            "
                            >Conservar la actual</Button
                        >
                    </template>
                    <InputError :message="form.errors.private_key" />
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        3D Secure (autenticación del banco)
                    </legend>
                    <RadioGroup
                        v-model="form.three_ds_mode"
                        class="grid gap-2 sm:grid-cols-3"
                    >
                        <label
                            v-for="m in threeDsModes"
                            :key="m.value"
                            :class="
                                cn(
                                    'flex cursor-pointer gap-3 rounded-xl border p-3 transition-colors',
                                    form.three_ds_mode === m.value
                                        ? 'border-primary/40 bg-primary-soft/40'
                                        : 'hover:bg-muted/40',
                                )
                            "
                        >
                            <RadioGroupItem :value="m.value" class="mt-0.5" />
                            <span class="grid gap-1">
                                <span class="text-sm font-medium">{{
                                    m.label
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    m.description
                                }}</span>
                            </span>
                        </label>
                    </RadioGroup>
                    <InputError :message="form.errors.three_ds_mode" />
                </fieldset>

                <div class="grid gap-5 border-t pt-5 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="webhook_user">Usuario del webhook</Label>
                        <div class="flex gap-2">
                            <Input
                                id="webhook_user"
                                v-model="form.webhook_user"
                                autocomplete="off"
                                spellcheck="false"
                                class="font-mono"
                                :placeholder="
                                    credentials.webhookUser.source === 'env'
                                        ? `Del .env: ${credentials.webhookUser.value}`
                                        : 'mlhabitat-…'
                                "
                                :aria-invalid="!!form.errors.webhook_user"
                            />
                            <Button
                                v-if="form.webhook_user"
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Copiar usuario del webhook"
                                @click="copy(form.webhook_user, 'Usuario')"
                                ><Copy class="size-4"
                            /></Button>
                            <Button
                                v-else
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Generar usuario"
                                @click="generateUser"
                                ><WandSparkles class="size-4"
                            /></Button>
                        </div>
                        <InputError :message="form.errors.webhook_user" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="webhook_password"
                            >Contraseña del webhook</Label
                        >
                        <div
                            v-if="
                                !replacePassword &&
                                credentials.webhookPassword.masked
                            "
                            class="flex flex-wrap items-center gap-3"
                        >
                            <code
                                class="rounded-md border bg-muted/50 px-3 py-2 font-mono text-sm"
                                >{{ credentials.webhookPassword.masked }}</code
                            >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="replacePassword = true"
                                >Reemplazar</Button
                            >
                        </div>
                        <template v-else>
                            <div class="flex gap-2">
                                <Input
                                    id="webhook_password"
                                    v-model="form.webhook_password"
                                    autocomplete="new-password"
                                    spellcheck="false"
                                    class="font-mono"
                                    placeholder="Mínimo 16 caracteres"
                                    :aria-invalid="
                                        !!form.errors.webhook_password
                                    "
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Generar contraseña segura"
                                    @click="generatePassword"
                                    ><WandSparkles class="size-4"
                                /></Button>
                                <Button
                                    v-if="form.webhook_password"
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Copiar contraseña"
                                    @click="
                                        copy(
                                            form.webhook_password,
                                            'Contraseña',
                                        )
                                    "
                                    ><Copy class="size-4"
                                /></Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Cópiala ahora: la necesitas al registrar el
                                webhook en Openpay y después ya no se muestra.
                            </p>
                        </template>
                        <InputError :message="form.errors.webhook_password" />
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t pt-5"
                >
                    <p class="text-xs text-muted-foreground">
                        <template v-if="credentials.updatedAt"
                            >Última edición
                            <DateText :value="credentials.updatedAt" relative />
                            <template v-if="credentials.updatedBy">
                                por {{ credentials.updatedBy }}</template
                            >.</template
                        >
                        <template v-else
                            >Aún no se guarda nada en el panel.</template
                        >
                    </p>
                    <div class="flex gap-2">
                        <Button
                            v-if="
                                credentials.hasPanelValues ||
                                credentials.unreadable
                            "
                            type="button"
                            variant="ghost"
                            @click="resetOpen = true"
                        >
                            <RotateCcw class="size-4" /> Usar solo el .env
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            <Spinner v-if="form.processing" />
                            {{
                                form.processing
                                    ? 'Guardando…'
                                    : 'Guardar credenciales'
                            }}
                        </Button>
                    </div>
                </div>
            </form>

            <ul v-else class="divide-y text-sm">
                <li
                    v-for="row in [
                        {
                            label: 'ID de comercio',
                            value: credentials.merchantId.value,
                            source: credentials.merchantId.source,
                        },
                        {
                            label: 'Llave pública',
                            value: credentials.publicKey.value,
                            source: credentials.publicKey.source,
                        },
                        {
                            label: 'Llave privada',
                            value: credentials.privateKey.masked,
                            source: credentials.privateKey.source,
                        },
                        {
                            label: 'Usuario del webhook',
                            value: credentials.webhookUser.value,
                            source: credentials.webhookUser.source,
                        },
                        {
                            label: 'Contraseña del webhook',
                            value: credentials.webhookPassword.masked,
                            source: credentials.webhookPassword.source,
                        },
                    ]"
                    :key="row.label"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <span>{{ row.label }}</span>
                    <span class="flex items-center gap-2">
                        <code
                            v-if="row.value"
                            class="font-mono text-xs text-muted-foreground"
                            >{{ row.value }}</code
                        >
                        <span
                            :class="
                                row.source
                                    ? 'text-success-ink'
                                    : 'text-danger-ink'
                            "
                            >{{ sourceText(row.source) }}</span
                        >
                    </span>
                </li>
                <li class="py-2.5 text-muted-foreground">
                    Solo quien tiene el permiso de configuración puede cambiar
                    las credenciales.
                </li>
            </ul>
        </SectionCard>

        <!-- Conexión -->
        <SectionCard
            title="Prueba de conexión"
            description="Consulta de solo lectura con la llave privada: no crea cargos ni mueve dinero."
            :icon="PlugZap"
        >
            <div v-if="lastPing" class="flex items-start gap-3">
                <CircleCheck
                    v-if="lastPing.ok"
                    class="mt-0.5 size-5 shrink-0 text-success"
                    aria-hidden="true"
                />
                <CircleAlert
                    v-else
                    class="mt-0.5 size-5 shrink-0 text-danger"
                    aria-hidden="true"
                />
                <div class="text-sm">
                    <p class="font-medium">{{ lastPing.message }}</p>
                    <p class="text-muted-foreground">
                        <DateText :value="lastPing.at" relative />
                        <template v-if="lastPing.httpStatus">
                            · HTTP {{ lastPing.httpStatus }}</template
                        >
                        <template v-if="lastPing.errorCode">
                            · código {{ lastPing.errorCode }}</template
                        >
                        <template v-if="lastPing.latencyMs !== null">
                            · {{ lastPing.latencyMs }} ms</template
                        >
                    </p>
                    <p v-if="!lastPing.current" class="mt-1 text-warning-ink">
                        Las credenciales cambiaron desde esta prueba: vuelve a
                        probar.
                    </p>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Aún no se ha probado la conexión.
            </p>
            <Button
                class="mt-4"
                variant="outline"
                :disabled="testing || !configured"
                @click="test"
            >
                <Spinner v-if="testing" /><PlugZap v-else class="size-4" />
                Probar conexión
            </Button>
        </SectionCard>

        <!-- Webhook -->
        <SectionCard
            title="Webhook"
            description="Openpay nos avisa en esta URL de pagos, reembolsos y contracargos."
            :icon="Webhook"
        >
            <template #actions>
                <StatusBadge
                    :tone="webhookBadge.tone"
                    :label="webhookBadge.label"
                />
            </template>
            <div class="space-y-5">
                <div>
                    <p class="text-sm font-medium">URL para registrar</p>
                    <div class="mt-1.5 flex items-center gap-2">
                        <code
                            class="min-w-0 flex-1 truncate rounded-md border bg-muted/50 px-3 py-2 text-sm"
                            >{{ webhookUrl }}</code
                        >
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Copiar URL del webhook"
                            @click="copy(webhookUrl, 'URL')"
                            ><Copy class="size-4"
                        /></Button>
                    </div>
                    <p
                        v-if="!webhookUrl.startsWith('https://')"
                        class="mt-1.5 text-xs text-warning-ink"
                    >
                        Esta dirección no es HTTPS. Openpay producción solo
                        acepta HTTPS con certificado válido; usa la URL de tu
                        dominio publicado.
                    </p>
                </div>

                <div>
                    <p class="text-sm font-medium">Código de verificación</p>
                    <div
                        v-if="webhook.code"
                        class="mt-1.5 flex flex-wrap items-center gap-2"
                    >
                        <code
                            class="rounded-md border bg-muted/50 px-3 py-2 font-mono text-base tracking-wider"
                            >{{ webhook.code }}</code
                        >
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Copiar código de verificación"
                            @click="copy(webhook.code, 'Código')"
                            ><Copy class="size-4"
                        /></Button>
                        <span class="text-xs text-muted-foreground"
                            >Recibido
                            <DateText :value="webhook.codeReceivedAt" relative
                        /></span>
                    </div>
                    <p v-else class="mt-1 text-sm text-muted-foreground">
                        Aparecerá aquí cuando registres el webhook en Openpay
                        (recarga la página tras guardarlo allá).
                    </p>
                    <p
                        v-if="webhook.lastEventAt"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Último aviso recibido
                        <DateText :value="webhook.lastEventAt" relative />.
                    </p>
                </div>

                <ol class="list-decimal space-y-2 pl-5 text-sm">
                    <li>
                        Guarda arriba el usuario y la contraseña del webhook.
                    </li>
                    <li>
                        Entra al dashboard de Openpay
                        <a
                            :href="dashboardUrl"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 underline underline-offset-4"
                            >({{
                                environment === 'production'
                                    ? 'producción'
                                    : 'sandbox'
                            }}) <ExternalLink class="size-3" aria-hidden="true"
                        /></a>
                        y ve a
                        <strong>Perfil → Webhooks → Agregar webhook</strong>.
                    </li>
                    <li>
                        Pega la URL de arriba, activa la autenticación básica y
                        escribe el mismo usuario y contraseña.
                    </li>
                    <li>
                        Selecciona estos eventos:
                        <span class="mt-1 flex flex-wrap gap-1.5">
                            <code
                                v-for="e in webhookEvents"
                                :key="e"
                                class="rounded bg-muted px-1.5 py-0.5 text-xs"
                                >{{ e }}</code
                            >
                        </span>
                    </li>
                    <li>
                        Guarda. Openpay envía un código que aparecerá arriba;
                        cópialo y pulsa <strong>Verificar</strong> en el
                        dashboard.
                    </li>
                </ol>
            </div>
        </SectionCard>

        <!-- Tarjetas de prueba -->
        <SectionCard
            v-if="environment === 'sandbox'"
            title="Tarjetas de prueba (solo sandbox)"
            description="Cualquier fecha de vencimiento futura y cualquier CVV de 3 dígitos (4 en American Express). No funcionan en producción."
            :icon="CreditCard"
        >
            <ul class="divide-y text-sm">
                <li
                    v-for="card in testCards"
                    :key="card.number"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <span class="flex items-center gap-2">
                        <code class="font-mono tabular-nums">{{
                            card.number
                        }}</code>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :aria-label="`Copiar ${card.number}`"
                            @click="
                                copy(card.number.replace(/\s/g, ''), 'Número')
                            "
                            ><Copy class="size-3.5"
                        /></Button>
                    </span>
                    <span class="text-muted-foreground"
                        >{{ card.brand }} · {{ card.result }}</span
                    >
                </li>
            </ul>
        </SectionCard>

        <div class="grid gap-6 md:grid-cols-2">
            <SectionCard title="Último aviso del webhook">
                <div v-if="lastWebhook" class="space-y-1.5 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono">{{ lastWebhook.type }}</span>
                        <StatusBadge
                            :tone="tone(lastWebhook.status)"
                            :label="lastWebhook.status.label"
                            size="sm"
                        />
                    </div>
                    <p class="text-muted-foreground">
                        <DateText
                            :value="lastWebhook.receivedAt"
                            format="datetime"
                        />
                    </p>
                    <p v-if="lastWebhook.error" class="text-muted-foreground">
                        {{ lastWebhook.error }}
                    </p>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Todavía no se recibe ningún aviso.
                </p>
            </SectionCard>

            <SectionCard title="Último error">
                <div v-if="lastError" class="space-y-1.5 text-sm">
                    <p class="font-medium">{{ lastError.source }}</p>
                    <p>{{ lastError.message }}</p>
                    <p class="text-muted-foreground">
                        <DateText :value="lastError.at" format="datetime" />
                    </p>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Sin errores registrados.
                </p>
            </SectionCard>
        </div>

        <ConfirmDialog
            v-model:open="resetOpen"
            title="¿Usar solo el archivo .env?"
            description="Se borran las credenciales guardadas en el panel. Si el .env no tiene llaves, los cobros con tarjeta se desactivan."
            confirm-label="Sí, borrar del panel"
            loading-label="Borrando…"
            tone="destructive"
            :confirm="reset"
        />
    </PageContainer>
</template>
