<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Check, MailWarning } from '@lucide/vue';
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
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mi cuenta', href: edit() },
            { title: 'Perfil', href: edit() },
        ],
    },
});

const props = defineProps<{
    mustVerifyEmail?: boolean;
    emailVerified?: boolean;
    phone?: string | null;
    status?: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const linkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Perfil" />

    <Heading
        title="Perfil"
        description="Tus datos de contacto. Tu nombre aparece en la bitácora de actividad y en los seguimientos que registras."
    />

    <div
        v-if="props.mustVerifyEmail && !props.emailVerified"
        role="status"
        class="flex flex-col gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-sm text-warning-ink sm:flex-row sm:items-center"
    >
        <MailWarning class="size-5 shrink-0" />
        <div class="flex-1">
            <p class="font-medium">Tu correo aún no está verificado.</p>
            <p v-if="linkSent">
                Te enviamos un nuevo enlace. Revisa tu bandeja de entrada.
            </p>
            <p v-else>
                Abre el enlace que te enviamos para confirmar que el correo es
                tuyo.
            </p>
        </div>
        <Form
            :action="send()"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
        >
            <Button
                type="submit"
                variant="outline"
                size="sm"
                :disabled="processing"
                class="bg-background"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Enviando…' : 'Reenviar enlace' }}
            </Button>
        </Form>
    </div>

    <div class="rounded-xl border bg-card text-card-foreground shadow-xs">
        <div class="flex items-center gap-4 border-b px-4 py-5 sm:px-6">
            <UserInfo :user="user" show-email show-role size="lg" />
        </div>

        <Form
            :action="ProfileController.update()"
            :options="{ preserveScroll: true }"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="px-4 sm:px-6">
                <FormSection
                    title="Datos de contacto"
                    description="Usa el correo con el que inicias sesión: ahí llegan los avisos y los enlaces de recuperación."
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid content-start gap-2 sm:col-span-2">
                            <Label for="name">Nombre completo</Label>
                            <Input
                                id="name"
                                name="name"
                                :default-value="user.name"
                                required
                                autocomplete="name"
                                placeholder="Ej. Ana López"
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
                            <p class="text-xs text-muted-foreground">
                                Si lo cambias, te pediremos verificarlo de
                                nuevo.
                            </p>
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label for="phone"
                                >Teléfono
                                <span class="font-normal text-muted-foreground"
                                    >(opcional)</span
                                ></Label
                            >
                            <Input
                                id="phone"
                                type="tel"
                                name="phone"
                                inputmode="tel"
                                :default-value="props.phone ?? ''"
                                autocomplete="tel"
                                placeholder="55 1234 5678"
                                class="h-10"
                                :aria-invalid="errors.phone ? true : undefined"
                            />
                            <InputError :message="errors.phone" />
                        </div>
                    </div>
                </FormSection>
            </div>

            <footer
                class="flex items-center justify-end gap-3 rounded-b-xl border-t bg-muted/40 px-4 py-3 sm:px-6"
            >
                <Transition
                    enter-active-class="transition-opacity duration-200"
                    enter-from-class="opacity-0"
                    leave-active-class="transition-opacity duration-300"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-show="recentlySuccessful"
                        class="flex items-center gap-1.5 text-sm text-success-ink"
                        role="status"
                    >
                        <Check class="size-4" /> Guardado
                    </p>
                </Transition>
                <Button
                    type="submit"
                    :disabled="processing"
                    :aria-busy="processing"
                    data-test="update-profile-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Guardando…' : 'Guardar cambios' }}
                </Button>
            </footer>
        </Form>
    </div>
</template>
