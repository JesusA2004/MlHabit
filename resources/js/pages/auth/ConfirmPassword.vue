<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Confirma tu contraseña',
        description:
            'Esta sección protege información sensible. Escribe tu contraseña para continuar.',
    },
});
</script>

<template>
    <Head title="Confirmar contraseña" />

    <Form
        action="/user/confirm-password"
        method="post"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <Label for="password">Contraseña</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                autofocus
                class="h-11 text-base md:text-[0.9375rem]"
                :aria-invalid="errors.password ? true : undefined"
                :aria-describedby="
                    errors.password ? 'password-error' : undefined
                "
            />
            <InputError id="password-error" :message="errors.password" />
        </div>

        <Button
            type="submit"
            class="h-11 w-full text-base"
            :disabled="processing"
            :aria-busy="processing"
            data-test="confirm-password-button"
        >
            <Spinner v-if="processing" />
            <ShieldCheck v-else />
            {{ processing ? 'Confirmando…' : 'Confirmar y continuar' }}
        </Button>

        <p class="text-sm text-muted-foreground">
            No te lo volveremos a pedir durante las próximas horas en este
            dispositivo.
        </p>
    </Form>
</template>
