<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Bienvenido de vuelta',
        description: 'Inicia sesión para entrar a la sala de redacción de MR. INSIGHT.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        role="status"
        class="mb-6 flex items-start gap-2.5 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success"
    >
        <CircleCheck class="mt-0.5 size-4 shrink-0" />
        <span>{{ status }}</span>
    </div>

    <Form
        v-bind="store.form()"
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
                autocomplete="email"
                inputmode="email"
                placeholder="tu@correo.com"
                class="h-11 px-3.5 text-base md:text-base"
                :aria-invalid="errors.email ? true : undefined"
                aria-describedby="email-error"
            />
            <InputError id="email-error" :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Contraseña</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Tu contraseña"
                class="h-11 px-3.5 text-base md:text-base"
                :aria-invalid="errors.password ? true : undefined"
                aria-describedby="password-error"
            />
            <InputError id="password-error" :message="errors.password" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <Label for="remember" class="flex min-h-11 cursor-pointer items-center gap-3 font-normal">
                <Checkbox id="remember" name="remember" />
                <span>Mantener sesión iniciada</span>
            </Label>
            <TextLink v-if="canResetPassword" :href="request()" class="text-sm">¿Olvidaste tu contraseña?</TextLink>
        </div>

        <Button
            type="submit"
            size="lg"
            class="mt-2 h-11 w-full text-base"
            :disabled="processing"
            :aria-busy="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Entrando…' : 'Entrar al panel' }}
        </Button>

        <p class="text-center text-xs text-muted-foreground">El acceso es solo para el equipo editorial de MR. INSIGHT.</p>
    </Form>
</template>
