<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import FormSection from '@/components/admin/FormSection.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import UserInfo from '@/components/UserInfo.vue';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración',
                href: edit(),
            },
            {
                title: 'Perfil',
                href: edit(),
            },
        ],
    },
});

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Perfil" />

    <Heading title="Perfil" description="Tu nombre aparece como autor en las publicaciones y en el registro de actividad." />

    <div class="rounded-xl border bg-card text-card-foreground shadow-xs">
        <div class="flex items-center gap-4 border-b px-4 py-5 sm:px-6">
            <UserInfo :user="user" show-email show-role size="lg" />
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            :options="{ preserveScroll: true }"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="px-4 sm:px-6">
                <FormSection title="Información personal" description="Usa el correo con el que inicias sesión; ahí llegan los enlaces de recuperación.">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="name">Nombre</Label>
                            <Input
                                id="name"
                                name="name"
                                :default-value="user.name"
                                required
                                autocomplete="name"
                                placeholder="Ej. Juan Pérez"
                                class="h-10"
                                :aria-invalid="errors.name ? true : undefined"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label for="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                :default-value="user.email"
                                required
                                autocomplete="username"
                                placeholder="tu@correo.com"
                                class="h-10"
                                :aria-invalid="errors.email ? true : undefined"
                            />
                            <InputError :message="errors.email" />
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
                        <Check class="size-4" /> Guardado
                    </p>
                </Transition>
                <Button type="submit" :disabled="processing" :aria-busy="processing" data-test="update-profile-button">
                    <Spinner v-if="processing" />
                    Guardar cambios
                </Button>
            </footer>
        </Form>
    </div>

</template>
