<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import FormSection from '@/components/admin/FormSection.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <section aria-labelledby="danger-zone-title" class="rounded-xl border border-destructive/30 bg-card text-card-foreground shadow-xs">
        <div class="flex items-center gap-2 border-b border-destructive/20 px-4 py-3 sm:px-6">
            <TriangleAlert class="size-4 text-destructive" />
            <h2 id="danger-zone-title" class="text-sm font-semibold text-destructive">Zona de peligro</h2>
        </div>
        <div class="px-4 sm:px-6">
            <FormSection
                title="Eliminar cuenta"
                description="Borra tu usuario de forma permanente y pierdes el acceso al panel."
            >
                <div class="flex flex-col gap-3 rounded-lg border border-dashed border-destructive/30 bg-destructive/5 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">Esta acción no se puede deshacer. Te pediremos tu contraseña para confirmar.</p>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="destructive" class="shrink-0" data-test="delete-user-button">Eliminar cuenta</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="ProfileController.destroy.form()"
                                reset-on-success
                                @error="() => passwordInput?.focus()"
                                :options="{
                                    preserveScroll: true,
                                }"
                                class="space-y-6"
                                v-slot="{ errors, processing, reset, clearErrors }"
                            >
                                <DialogHeader class="space-y-3">
                                    <DialogTitle>¿Eliminar tu cuenta definitivamente?</DialogTitle>
                                    <DialogDescription>
                                        Cerraremos tu sesión y borraremos tu usuario. No podrás volver a entrar con este correo. Escribe tu
                                        contraseña para confirmar.
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="grid gap-2">
                                    <Label for="delete-password">Contraseña</Label>
                                    <PasswordInput
                                        id="delete-password"
                                        name="password"
                                        ref="passwordInput"
                                        autocomplete="current-password"
                                        placeholder="Tu contraseña"
                                        :aria-invalid="errors.password ? true : undefined"
                                    />
                                    <InputError :message="errors.password" />
                                </div>

                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="
                                                () => {
                                                    clearErrors();
                                                    reset();
                                                }
                                            "
                                        >
                                            Cancelar
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                        :aria-busy="processing"
                                        data-test="confirm-delete-user-button"
                                    >
                                        <Spinner v-if="processing" />
                                        Eliminar cuenta
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </FormSection>
        </div>
    </section>
</template>
