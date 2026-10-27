<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, RotateCw } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import { Button } from '@/components/ui/button';
import { adminNavigation } from '@/lib/navigation';
import { shellFor } from '@/lib/permissions';

/**
 * Página de error con marca (403, 404, 419, 429, 500, 503). Independiente
 * de cualquier layout: funciona con o sin sesión.
 */

const props = defineProps<{ status: number; message?: string | null }>();

type Copy = { title: string; body: string; action?: 'reload' | 'back' };

const COPY: Record<number, Copy> = {
    403: {
        title: 'No tienes acceso a esta sección',
        body: 'Tu cuenta no tiene permiso para ver esta página. Si lo necesitas para tu trabajo, pide acceso a un administrador.',
        action: 'back',
    },
    404: {
        title: 'No encontramos esta página',
        body: 'Puede que el enlace esté incompleto o que la página se haya movido. Revisa la dirección o vuelve al inicio.',
        action: 'back',
    },
    419: {
        title: 'Tu sesión expiró',
        body: 'Por seguridad, la página caducó después de un tiempo sin actividad. Recárgala y vuelve a intentarlo.',
        action: 'reload',
    },
    429: {
        title: 'Demasiadas solicitudes seguidas',
        body: 'Espera un minuto y vuelve a intentarlo. Esto nos ayuda a mantener el servicio estable para todos.',
        action: 'reload',
    },
    500: {
        title: 'Algo falló de nuestro lado',
        body: 'El error quedó registrado y ya lo estamos revisando. Intenta de nuevo en unos minutos.',
        action: 'reload',
    },
    503: {
        title: 'Estamos haciendo mejoras',
        body: 'El sitio vuelve a estar disponible en unos minutos. Gracias por tu paciencia.',
        action: 'reload',
    },
};

const copy = computed<Copy>(() => COPY[props.status] ?? COPY[500]);

const page = usePage();
const home = computed(() => {
    const shell = shellFor(page.props.auth?.user ?? null, adminNavigation);

    return {
        admin: '/admin',
        technician: '/tecnico',
        portal: '/portal',
        guest: '/',
    }[shell];
});
const homeLabel = computed(() =>
    home.value === '/' ? 'Ir al inicio' : 'Ir a mi inicio',
);

function reload() {
    window.location.reload();
}

function back() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = home.value;
    }
}
</script>

<template>
    <Head :title="copy.title" />

    <div
        class="grid min-h-screen grid-cols-1 bg-background text-foreground lg:grid-cols-2"
    >
        <main class="flex flex-col px-5 py-6 sm:px-10 lg:px-16 xl:px-24">
            <Link
                href="/"
                class="w-fit rounded-md"
                aria-label="ML Habitat, inicio"
            >
                <AppLogo />
            </Link>

            <div class="flex flex-1 flex-col justify-center py-16">
                <p
                    class="font-display text-sm font-semibold text-oak-ink tabular-nums"
                >
                    Error {{ props.status }}
                </p>
                <h1 class="mt-3 max-w-xl text-display-lg">{{ copy.title }}</h1>
                <p class="mt-5 max-w-lg text-lead text-muted-foreground">
                    {{ copy.body }}
                </p>
                <p
                    v-if="props.message && props.status !== 500"
                    class="mt-4 max-w-lg rounded-lg border bg-card px-4 py-3 text-sm text-muted-foreground"
                >
                    {{ props.message }}
                </p>

                <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                    <Button as-child class="h-12 rounded-lg px-6 text-base">
                        <a :href="home">{{ homeLabel }}</a>
                    </Button>
                    <Button
                        v-if="copy.action === 'reload'"
                        variant="outline"
                        class="h-12 rounded-lg bg-card px-6 text-base"
                        @click="reload"
                    >
                        <RotateCw />
                        Recargar página
                    </Button>
                    <Button
                        v-else
                        variant="outline"
                        class="h-12 rounded-lg bg-card px-6 text-base"
                        @click="back"
                    >
                        <ArrowLeft />
                        Volver atrás
                    </Button>
                </div>
            </div>

            <p class="text-sm text-muted-foreground">
                ¿Sigue sin funcionar? Escríbenos y con gusto te ayudamos.
            </p>
        </main>

        <aside
            class="relative hidden overflow-hidden bg-stone lg:block"
            aria-hidden="true"
        >
            <div class="absolute inset-x-[8%] bottom-[-6%]">
                <FinishSwatches />
            </div>
        </aside>
    </div>
</template>
