<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CircleCheck, CircleX, Copy, ExternalLink, PlugZap } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import DateText from '@/components/ds/DateText.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Option = { value: string; label: string; tone: string };

const props = defineProps<{
    environment: 'sandbox' | 'production';
    variables: { key: string; configured: boolean; hint: string | null }[];
    configured: boolean;
    webhookConfigured: boolean;
    webhookUrl: string;
    webhookEvents: string[];
    dashboardUrl: string;
    lastWebhook: {
        type: string;
        status: Option;
        receivedAt: string | null;
        error: string | null;
    } | null;
    verification: { code: string; receivedAt: string | null } | null;
    lastError: { source: string; message: string; at: string | null } | null;
    lastPing: {
        ok: boolean;
        message: string;
        httpStatus: number | null;
        errorCode: string | null;
        latencyMs: number | null;
        at: string;
    } | null;
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
const testing = ref(false);

function test() {
    router.post(
        '/admin/openpay/probar',
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
</script>

<template>
    <Head title="Openpay" />

    <PageContainer width="narrow">
        <PageHeader
            title="Openpay"
            description="Estado de la integración de cobros con tarjeta. Aquí nunca se muestran llaves ni contraseñas."
        >
            <template #meta>
                <StatusBadge
                    :tone="
                        props.environment === 'production'
                            ? 'success'
                            : 'warning'
                    "
                    :label="
                        props.environment === 'production'
                            ? 'Producción'
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
            v-if="!configured"
            tone="danger"
            message="Faltan variables en el servidor: los pagos con tarjeta están desactivados. Configúralas en el archivo .env y vuelve a probar."
        />
        <FormStatus
            v-else-if="!webhookConfigured"
            tone="info"
            message="Los cobros funcionan, pero el webhook no tiene credenciales: Openpay no podrá avisarnos de cambios (reembolsos, contracargos). Configura OPENPAY_WEBHOOK_USER y OPENPAY_WEBHOOK_PASSWORD."
        />

        <SectionCard
            title="Variables de entorno"
            description="Solo se indica si están configuradas."
        >
            <ul class="divide-y">
                <li
                    v-for="v in variables"
                    :key="v.key"
                    class="flex items-center justify-between gap-3 py-2.5"
                >
                    <span class="font-mono text-sm">{{ v.key }}</span>
                    <span class="flex items-center gap-2 text-sm">
                        <span
                            v-if="v.hint"
                            class="font-mono text-xs text-muted-foreground"
                            >{{ v.hint }}</span
                        >
                        <CircleCheck
                            v-if="v.configured"
                            class="size-4 text-success"
                            aria-hidden="true"
                        />
                        <CircleX
                            v-else
                            class="size-4 text-danger"
                            aria-hidden="true"
                        />
                        <span
                            :class="
                                v.configured
                                    ? 'text-success-ink'
                                    : 'text-danger-ink'
                            "
                            >{{ v.configured ? 'Configurada' : 'Falta' }}</span
                        >
                    </span>
                </li>
            </ul>
        </SectionCard>

        <SectionCard
            title="Prueba de conexión"
            description="Consulta segura de solo lectura (un cargo) con la llave privada."
        >
            <div v-if="lastPing" class="flex items-start gap-3">
                <CircleCheck
                    v-if="lastPing.ok"
                    class="mt-0.5 size-5 shrink-0 text-success"
                    aria-hidden="true"
                />
                <CircleX
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
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Aún no se ha probado la conexión.
            </p>
        </SectionCard>

        <SectionCard
            title="Webhook"
            description="Openpay nos avisa de cargos, reembolsos y contracargos en esta URL."
        >
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
                </div>

                <div>
                    <p class="text-sm font-medium">Código de verificación</p>
                    <div
                        v-if="verification && verification.code"
                        class="mt-1.5 flex items-center gap-2"
                    >
                        <code
                            class="rounded-md border bg-muted/50 px-3 py-2 font-mono text-base tracking-wider"
                            >{{ verification.code }}</code
                        >
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Copiar código de verificación"
                            @click="copy(verification.code, 'Código')"
                            ><Copy class="size-4"
                        /></Button>
                        <span class="text-xs text-muted-foreground"
                            >Recibido
                            <DateText :value="verification.receivedAt" relative
                        /></span>
                    </div>
                    <p v-else class="mt-1 text-sm text-muted-foreground">
                        Aparecerá aquí cuando registres el webhook en Openpay.
                    </p>
                </div>

                <ol class="list-decimal space-y-2 pl-5 text-sm">
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
                        Pega la URL de arriba. En producción debe ser HTTPS con
                        un certificado válido.
                    </li>
                    <li>
                        Activa la autenticación básica y captura el usuario y la
                        contraseña configurados en
                        <code>OPENPAY_WEBHOOK_USER</code> y
                        <code>OPENPAY_WEBHOOK_PASSWORD</code>.
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
                        Guarda. Openpay enviará un código de verificación que
                        aparecerá arriba; cópialo y pulsa
                        <strong>Verificar</strong> en el dashboard.
                    </li>
                </ol>
            </div>
        </SectionCard>

        <div class="grid gap-6 md:grid-cols-2">
            <SectionCard title="Último webhook">
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
                    Todavía no se recibe ningún webhook.
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
    </PageContainer>
</template>
