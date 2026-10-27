<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Activa tu cuenta',
        description:
            'Crea una contraseña para entrar a ML Habitat y dar seguimiento a todo desde un solo lugar.',
    },
});

/**
 * Alta de usuarios invitados (equipo, técnicos y clientes). Envía
 * `token`, `email`, `password` y `password_confirmation` a `action`
 * (por defecto `/invitacion/{token}`).
 */
const props = defineProps<{
    email: string;
    token: string;
    name?: string | null;
    action?: string;
    passwordRules?: string;
}>();

const firstName = computed(() => props.name?.trim().split(/\s+/)[0] ?? null);
const action = computed(
    () => props.action ?? `/invitacion/${encodeURIComponent(props.token)}`,
);
</script>

<template>
    <Head title="Activar cuenta" />

    <p v-if="firstName" class="-mt-3 mb-7 text-[0.9375rem] text-foreground">
        Hola, <span class="font-medium">{{ firstName }}</span
        >. Ya casi terminas.
    </p>

    <Form
        :action="action"
        method="post"
        :transform="
            (data) => ({ ...data, token: props.token, email: props.email })
        "
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <Label for="email">Correo electrónico</Label>
            <Input
                id="email"
                type="email"
                name="email"
                autocomplete="username"
                :model-value="props.email"
                readonly
                class="h-11 bg-muted text-base text-muted-foreground md:text-[0.9375rem]"
                :aria-invalid="errors.email ? true : undefined"
                :aria-describedby="errors.email ? 'email-error' : undefined"
            />
            <InputError id="email-error" :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Contraseña</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="new-password"
                autofocus
                class="h-11 text-base md:text-[0.9375rem]"
                :passwordrules="props.passwordRules"
                :aria-invalid="errors.password ? true : undefined"
                :aria-describedby="
                    errors.password
                        ? 'password-error password-hint'
                        : 'password-hint'
                "
            />
            <p id="password-hint" class="text-xs text-muted-foreground">
                Mínimo 8 caracteres. Evita usar datos personales.
            </p>
            <InputError id="password-error" :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirma la contraseña</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                class="h-11 text-base md:text-[0.9375rem]"
                :passwordrules="props.passwordRules"
                :aria-invalid="errors.password_confirmation ? true : undefined"
                :aria-describedby="
                    errors.password_confirmation
                        ? 'password_confirmation-error'
                        : undefined
                "
            />
            <InputError
                id="password_confirmation-error"
                :message="errors.password_confirmation"
            />
        </div>

        <InputError id="token-error" :message="errors.token" />

        <Button
            type="submit"
            class="mt-1 h-11 w-full text-base"
            :disabled="processing"
            :aria-busy="processing"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Activando…' : 'Activar mi cuenta' }}
        </Button>

        <p class="text-xs leading-relaxed text-muted-foreground">
            Al activar tu cuenta aceptas que usemos tus datos para dar
            seguimiento a tu proyecto, conforme a nuestro
            <a
                href="/aviso-de-privacidad"
                class="underline underline-offset-4 hover:text-foreground"
                >aviso de privacidad</a
            >.
        </p>
    </Form>
</template>
