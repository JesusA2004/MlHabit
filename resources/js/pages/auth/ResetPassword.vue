<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Nueva contraseña',
        description: 'Elige una contraseña larga y única. Al guardarla podrás iniciar sesión de inmediato.',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Restablecer contraseña" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
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
                autocomplete="email"
                v-model="inputEmail"
                readonly
                class="h-11 bg-muted px-3.5 text-base text-muted-foreground md:text-base"
                :aria-invalid="errors.email ? true : undefined"
                aria-describedby="email-error"
            />
            <InputError id="email-error" :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Nueva contraseña</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="new-password"
                autofocus
                placeholder="Nueva contraseña"
                class="h-11 px-3.5 text-base md:text-base"
                :passwordrules="passwordRules"
                :aria-invalid="errors.password ? true : undefined"
                aria-describedby="password-error"
            />
            <InputError id="password-error" :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirmar contraseña</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Escríbela de nuevo"
                class="h-11 px-3.5 text-base md:text-base"
                :passwordrules="passwordRules"
                :aria-invalid="errors.password_confirmation ? true : undefined"
                aria-describedby="password_confirmation-error"
            />
            <InputError id="password_confirmation-error" :message="errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            size="lg"
            class="mt-2 h-11 w-full text-base"
            :disabled="processing"
            :aria-busy="processing"
            data-test="reset-password-button"
        >
            <Spinner v-if="processing" />
            Guardar nueva contraseña
        </Button>
    </Form>
</template>
