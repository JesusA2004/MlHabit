<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Crea una contraseña nueva',
        description:
            'Usa al menos 8 caracteres. Al guardarla podrás iniciar sesión de inmediato.',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules?: string;
}>();
</script>

<template>
    <Head title="Restablecer contraseña" />

    <Form
        action="/reset-password"
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
            />
            <InputError id="email-error" :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Contraseña nueva</Label>
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
                    errors.password ? 'password-error' : undefined
                "
            />
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

        <Button
            type="submit"
            class="mt-1 h-11 w-full text-base"
            :disabled="processing"
            :aria-busy="processing"
            data-test="reset-password-button"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Guardando…' : 'Guardar contraseña' }}
        </Button>
    </Form>
</template>
