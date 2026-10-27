<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Check, KeyRound } from '@lucide/vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import FormSection from '@/components/admin/FormSection.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit as editProfile } from '@/routes/profile';
import { edit } from '@/routes/security';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} ;

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración',
                href: editProfile(),
            },
            {
                title: 'Seguridad',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Seguridad" />

    <Heading title="Seguridad" description="Protege el acceso al panel. Cambia tu contraseña si sospechas que alguien más la conoce." />

    <div class="rounded-xl border bg-card text-card-foreground shadow-xs">
        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="['password', 'password_confirmation', 'current_password']"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="px-4 sm:px-6">
                <FormSection title="Contraseña" description="Usa una frase larga y única que no utilices en otros sitios.">
                    <template #aside>
                        <div class="mt-4 hidden items-start gap-2 rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground lg:flex">
                            <KeyRound class="mt-0.5 size-4 shrink-0 text-brand" />
                            <span>Ejemplo: «café-negro-a-las-seis-en-punto». Un gestor de contraseñas te ayuda a recordarla.</span>
                        </div>
                    </template>

                    <div class="grid gap-2">
                        <Label for="current_password">Contraseña actual</Label>
                        <PasswordInput
                            id="current_password"
                            name="current_password"
                            class="h-10"
                            autocomplete="current-password"
                            placeholder="Tu contraseña actual"
                            :aria-invalid="errors.current_password ? true : undefined"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="password">Nueva contraseña</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                class="h-10"
                                autocomplete="new-password"
                                placeholder="Nueva contraseña"
                                :passwordrules="props.passwordRules"
                                :aria-invalid="errors.password ? true : undefined"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label for="password_confirmation">Confirmar contraseña</Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                class="h-10"
                                autocomplete="new-password"
                                placeholder="Escríbela de nuevo"
                                :passwordrules="props.passwordRules"
                                :aria-invalid="errors.password_confirmation ? true : undefined"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>
                    </div>
                </FormSection>
            </div>

            <footer class="flex items-center justify-end gap-3 rounded-b-xl border-t bg-surface-raised px-4 py-3 sm:px-6">
                <Transition
                    enter-active-class="transition-opacity duration-200"
                    enter-from-class="opacity-0"
                    leave-active-class="transition-opacity duration-300"
                    leave-to-class="opacity-0"
                >
                    <p v-show="recentlySuccessful" class="flex items-center gap-1.5 text-sm text-success" role="status">
                        <Check class="size-4" /> Contraseña actualizada
                    </p>
                </Transition>
                <Button type="submit" :disabled="processing" :aria-busy="processing" data-test="update-password-button">
                    <Spinner v-if="processing" />
                    Actualizar contraseña
                </Button>
            </footer>
        </Form>
    </div>
</template>
