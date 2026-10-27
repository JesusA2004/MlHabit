<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { KeyRound, Smartphone } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Verificación en dos pasos',
        description: 'Confirma que eres tú para terminar de iniciar sesión.',
    },
});

const mode = ref<'code' | 'recovery'>('code');

async function toggle(clearErrors: () => void) {
    mode.value = mode.value === 'code' ? 'recovery' : 'code';
    clearErrors();
    await nextTick();
    (
        document.getElementById(
            mode.value === 'code' ? 'code' : 'recovery_code',
        ) as HTMLInputElement | null
    )?.focus();
}
</script>

<template>
    <Head title="Verificación en dos pasos" />

    <Form
        action="/two-factor-challenge"
        method="post"
        :reset-on-error="['code', 'recovery_code']"
        v-slot="{ errors, processing, clearErrors }"
        class="flex flex-col gap-5"
    >
        <div v-if="mode === 'code'" class="grid gap-2">
            <Label for="code">Código de tu app de autenticación</Label>
            <p id="code-hint" class="text-sm text-muted-foreground">
                Abre tu app (Google Authenticator, 1Password, Authy…) y escribe
                el código de 6 dígitos.
            </p>
            <Input
                id="code"
                name="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]*"
                maxlength="6"
                required
                v-focus
                placeholder="000000"
                class="h-12 text-center font-display text-2xl tracking-[0.4em] tabular-nums"
                :aria-invalid="errors.code ? true : undefined"
                :aria-describedby="
                    errors.code ? 'code-error code-hint' : 'code-hint'
                "
            />
            <InputError id="code-error" :message="errors.code" />
        </div>

        <div v-else class="grid gap-2">
            <Label for="recovery_code">Código de recuperación</Label>
            <p id="recovery-hint" class="text-sm text-muted-foreground">
                Usa uno de los códigos que guardaste al activar la verificación.
                Cada código sirve una sola vez.
            </p>
            <Input
                id="recovery_code"
                name="recovery_code"
                autocomplete="one-time-code"
                autocapitalize="off"
                spellcheck="false"
                required
                placeholder="xxxxxxxxxx-xxxxxxxxxx"
                class="h-11 font-mono text-base"
                :aria-invalid="errors.recovery_code ? true : undefined"
                :aria-describedby="
                    errors.recovery_code
                        ? 'recovery_code-error recovery-hint'
                        : 'recovery-hint'
                "
            />
            <InputError
                id="recovery_code-error"
                :message="errors.recovery_code"
            />
        </div>

        <Button
            type="submit"
            class="h-11 w-full text-base"
            :disabled="processing"
            :aria-busy="processing"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Verificando…' : 'Verificar y entrar' }}
        </Button>

        <button
            type="button"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            @click="toggle(clearErrors)"
        >
            <template v-if="mode === 'code'">
                <KeyRound class="size-4" />
                Usar un código de recuperación
            </template>
            <template v-else>
                <Smartphone class="size-4" />
                Usar el código de mi app
            </template>
        </button>
    </Form>
</template>
