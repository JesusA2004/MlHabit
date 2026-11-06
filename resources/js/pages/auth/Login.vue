<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArrowRight, LockKeyhole, Mail } from '@lucide/vue';
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
        description: 'Escribe tu correo y tu contraseña para entrar.',
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
            <div class="relative">
                <Mail
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    autocomplete="username"
                    inputmode="email"
                    autocapitalize="off"
                    spellcheck="false"
                    placeholder="nombre@correo.com"
                    class="h-12 rounded-lg pl-10 text-base md:text-[0.9375rem]"
                    :aria-invalid="errors.email ? true : undefined"
                    :aria-describedby="errors.email ? 'email-error' : undefined"
                />
            </div>
            <InputError id="email-error" :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Contraseña</Label>
            <div class="relative">
                <LockKeyhole
                    class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Tu contraseña"
                    class="h-12 rounded-lg pl-10 text-base md:text-[0.9375rem]"
                    :aria-invalid="errors.password ? true : undefined"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                />
            </div>
            <InputError id="password-error" :message="errors.password" />
        </div>

        <div
            class="-mt-1 flex flex-wrap items-center justify-between gap-x-4 gap-y-1"
        >
            <Label
                for="remember"
                class="flex min-h-11 cursor-pointer items-center gap-2.5 font-normal text-muted-foreground"
            >
                <Checkbox id="remember" name="remember" value="1" />
                <span>Recordarme</span>
            </Label>
            <TextLink
                v-if="canResetPassword !== false"
                href="/forgot-password"
                class="inline-flex min-h-11 items-center text-sm"
            >
                ¿Olvidaste tu contraseña?
            </TextLink>
        </div>

        <Button
            type="submit"
            class="group h-12 w-full rounded-lg text-base shadow-[0_10px_24px_-12px_rgb(47_90_76/0.7)]"
            :disabled="processing"
            :aria-busy="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Entrando…' : 'Entrar' }}
            <ArrowRight
                v-if="!processing"
                class="size-4 transition-transform duration-200 group-hover:translate-x-0.5"
                aria-hidden="true"
            />
        </Button>
    </Form>
</template>
