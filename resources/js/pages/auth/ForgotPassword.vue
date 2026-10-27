<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Recupera tu acceso',
        description:
            'Escribe el correo de tu cuenta y te enviaremos un enlace para crear una contraseña nueva.',
    },
});

defineProps<{ status?: string }>();
</script>

<template>
    <Head title="Recuperar contraseña" />

    <FormStatus :message="status" class="mb-6" />

    <Form
        action="/forgot-password"
        method="post"
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
                autocomplete="email"
                inputmode="email"
                v-focus
                placeholder="nombre@correo.com"
                class="h-11 text-base md:text-[0.9375rem]"
                :aria-invalid="errors.email ? true : undefined"
                :aria-describedby="
                    errors.email ? 'email-error email-hint' : 'email-hint'
                "
            />
            <InputError id="email-error" :message="errors.email" />
            <p id="email-hint" class="text-xs text-muted-foreground">
                El enlace vence en 60 minutos. Si no llega, revisa tu carpeta de
                correo no deseado.
            </p>
        </div>

        <Button
            type="submit"
            class="h-11 w-full text-base"
            :disabled="processing"
            :aria-busy="processing"
            data-test="email-password-reset-link-button"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'Enviando enlace…' : 'Enviar enlace' }}
        </Button>
    </Form>

    <Link
        href="/login"
        class="mt-8 inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
    >
        <ArrowLeft class="size-4" />
        Volver a iniciar sesión
    </Link>
</template>
