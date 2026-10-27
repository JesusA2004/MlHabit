<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import FormStatus from '@/components/ds/FormStatus.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Inicia sesión',
        description:
            'Entra para dar seguimiento a tu proyecto o a tu trabajo del día.',
    },
});

defineProps<{
    status?: string;
    canResetPassword?: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <FormStatus :message="status" class="mb-6" />

    <Form
        action="/login"
        method="post"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <Label for="email">Correo electrónico</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                v-focus
                autocomplete="username"
                inputmode="email"
                placeholder="nombre@correo.com"
                class="h-11 text-base md:text-[0.9375rem]"
                :aria-invalid="errors.email ? true : undefined"
                :aria-describedby="errors.email ? 'email-error' : undefined"
            />
            <InputError id="email-error" :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between gap-3">
                <Label for="password">Contraseña</Label>
                <TextLink
                    v-if="canResetPassword !== false"
                    href="/forgot-password"
                    class="text-sm"
                >
                    ¿La olvidaste?
                </TextLink>
            </div>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Tu contraseña"
                class="h-11 text-base md:text-[0.9375rem]"
                :aria-invalid="errors.password ? true : undefined"
                :aria-describedby="
                    errors.password ? 'password-error' : undefined
                "
            />
            <InputError id="password-error" :message="errors.password" />
        </div>

        <Label
            for="remember"
            class="flex min-h-11 w-fit cursor-pointer items-center gap-3 font-normal text-muted-foreground"
        >
            <Checkbox id="remember" name="remember" value="1" />
            <span>Mantener mi sesión iniciada</span>
        </Label>

        <Button
            type="submit"
            class="h-11 w-full text-base"
            :disabled="processing"
            :aria-busy="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Iniciando sesión…' : 'Iniciar sesión' }}
        </Button>
    </Form>

    <p class="mt-10 text-sm leading-relaxed text-muted-foreground">
        ¿Eres cliente y aún no tienes acceso? Te enviamos una invitación por
        correo cuando confirmas tu proyecto.
    </p>
</template>
