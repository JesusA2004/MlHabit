<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleCheck, CircleAlert, MapPin } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { apiFetch } from '@/lib/http';
import { verificar } from '@/routes/public/cobertura';

/**
 * Verificador de cobertura por código postal. Consulta el endpoint del
 * módulo comercial (`GET /cobertura/verificar?cp=`), que responde
 * `{ covered, zone?, visitsEnabled, message }`.
 */
const props = withDefaults(
    defineProps<{
        ctaLabel: string;
        fallback?: string | null;
        tone?: 'light' | 'dark';
    }>(),
    { tone: 'light' },
);

type Result = {
    covered: boolean;
    zone?: string | null;
    visitsEnabled?: boolean;
    message?: string;
};

const cp = ref('');
const loading = ref(false);
const result = ref<Result | null>(null);
const error = ref<string | null>(null);

async function check() {
    error.value = null;
    result.value = null;
    const value = cp.value.replace(/\D/g, '');
    if (value.length !== 5) {
        error.value = 'Escribe un código postal de 5 dígitos.';

        return;
    }

    loading.value = true;
    try {
        const response = await apiFetch(
            verificar({ query: { cp: value } }).url,
        );
        if (response.status === 429) {
            error.value =
                'Hiciste muchas consultas seguidas. Intenta de nuevo en un momento.';

            return;
        }
        if (!response.ok) {
            error.value =
                'No pudimos revisar tu código postal. Intenta de nuevo.';

            return;
        }
        result.value = (await response.json()) as Result;
    } catch {
        error.value =
            'No pudimos revisar tu código postal. Revisa tu conexión e intenta de nuevo.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div>
        <form
            class="flex flex-col gap-3 sm:flex-row"
            novalidate
            @submit.prevent="check"
        >
            <label for="coverage-cp" class="sr-only">Código postal</label>
            <div class="relative flex-1">
                <MapPin
                    class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="coverage-cp"
                    v-model="cp"
                    inputmode="numeric"
                    autocomplete="postal-code"
                    maxlength="5"
                    placeholder="Tu código postal"
                    class="h-12 rounded-full bg-card pl-11 text-base"
                    :aria-invalid="error ? 'true' : undefined"
                    aria-describedby="coverage-result"
                />
            </div>
            <Button
                type="submit"
                size="lg"
                class="h-12 rounded-full px-6"
                :disabled="loading"
            >
                <Spinner v-if="loading" />
                {{ props.ctaLabel }}
            </Button>
        </form>

        <div id="coverage-result" aria-live="polite" class="mt-4 min-h-6">
            <p
                v-if="error"
                class="flex items-start gap-2 text-sm text-danger-ink"
            >
                <CircleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                {{ error }}
            </p>
            <div
                v-else-if="result && result.covered"
                class="flex flex-col gap-3 rounded-xl bg-success-soft p-4 text-success-ink sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="flex items-start gap-2 text-sm font-medium">
                    <CircleCheck
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        {{ result.message || '¡Sí llegamos a tu zona!' }}
                        <span v-if="result.zone" class="font-normal">
                            · {{ result.zone }}</span
                        >
                    </span>
                </p>
                <Button as-child size="sm" class="rounded-full">
                    <Link href="/solicitar">Solicita una asesoría</Link>
                </Button>
            </div>
            <div
                v-else-if="result"
                class="rounded-xl bg-warning-soft p-4 text-sm text-warning-ink"
            >
                <p class="flex items-start gap-2 font-medium">
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{
                        result.message ||
                        props.fallback ||
                        'Por ahora no llegamos a esa zona.'
                    }}
                </p>
                <Link
                    href="/solicitar"
                    class="mt-2 inline-block font-medium underline underline-offset-4"
                    >Déjanos tus datos</Link
                >
            </div>
        </div>
    </div>
</template>
