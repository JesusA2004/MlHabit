<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import { computed } from 'vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Confirma tu correo',
        description:
            'Te enviamos un enlace de verificación. Ábrelo desde tu bandeja de entrada para activar tu cuenta.',
    },
});

const props = defineProps<{ status?: string }>();

const page = usePage();
const email = computed(() => page.props.auth?.user?.email ?? null);
const linkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Verificar correo" />

    <div class="flex flex-col gap-6">
        <div class="flex items-start gap-4 rounded-xl border bg-card p-4">
            <span
                class="grid size-10 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary"
                aria-hidden="true"
            >
                <MailCheck class="size-5" />
            </span>
            <p class="text-sm leading-relaxed text-muted-foreground">
                <template v-if="email">
                    Revisa
                    <span class="font-medium break-all text-foreground">{{
                        email
                    }}</span
                    >. Si no lo ves en unos minutos, busca en correo no deseado
                    o pide un enlace nuevo.
                </template>
                <template v-else
                    >Si no lo ves en unos minutos, busca en correo no deseado o
                    pide un enlace nuevo.</template
                >
            </p>
        </div>

        <FormStatus
            v-if="linkSent"
            message="Listo. Enviamos un enlace nuevo a tu correo."
        />

        <Form
            action="/email/verification-notification"
            method="post"
            v-slot="{ processing }"
        >
            <Button
                type="submit"
                variant="outline"
                class="h-11 w-full text-base"
                :disabled="processing"
                :aria-busy="processing"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Enviando…' : 'Reenviar enlace' }}
            </Button>
        </Form>

        <Link
            href="/logout"
            method="post"
            as="button"
            class="inline-flex min-h-11 items-center justify-center rounded-md text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
        >
            Cerrar sesión
        </Link>
    </div>
</template>
