<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recuperar contraseña',
        description: 'Escribe el correo de tu cuenta y te enviaremos un enlace para crear una nueva.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Recuperar contraseña" />

    <div
        v-if="status"
        role="status"
        class="mb-6 flex items-start gap-2.5 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success"
    >
        <CircleCheck class="mt-0.5 size-4 shrink-0" />
        <span>{{ status }}</span>
    </div>

    <Form v-bind="email.form()" v-slot="{ errors, processing }" class="flex flex-col gap-5">
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
                placeholder="tu@correo.com"
                class="h-11 px-3.5 text-base md:text-base"
                :aria-invalid="errors.email ? true : undefined"
                aria-describedby="email-error"
            />
            <InputError id="email-error" :message="errors.email" />
            <p class="text-xs text-muted-foreground">El enlace caduca en 60 minutos. Revisa también tu carpeta de spam.</p>
        </div>

        <Button
            type="submit"
            size="lg"
            class="h-11 w-full text-base"
            :disabled="processing"
            :aria-busy="processing"
            data-test="email-password-reset-link-button"
        >
            <Spinner v-if="processing" />
            Enviar enlace de recuperación
        </Button>
    </Form>

    <div class="mt-8 border-t pt-6">
        <Link
            :href="login()"
            class="inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Volver a iniciar sesión
        </Link>
    </div>
</template>
