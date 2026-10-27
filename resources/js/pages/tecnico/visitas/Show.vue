<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronRight,
    Clock,
    MapPin,
    Navigation,
    Phone,
    Play,
    Ruler,
    ShieldCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { TechnicianVisit } from '@/components/projects/technician/VisitCard.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import mediciones from '@/routes/tecnico/mediciones';
import visitas from '@/routes/tecnico/visitas';
import type { EnumValue } from '@/types/projects';

type MeasurementRow = {
    id: number;
    version: number;
    status: EnumValue;
    items_count: number;
    areas: string[];
    submitted_at: string | null;
    updated_at: string | null;
};

const props = defineProps<{
    visit: TechnicianVisit & {
        phone: string | null;
        access_notes: string | null;
        customer_notes: string | null;
        started_at: string | null;
        completed_at: string | null;
    };
    measurements: MeasurementRow[];
    can: {
        start: boolean;
        measure: boolean;
        complete: boolean;
        has_submitted: boolean;
    };
}>();

defineOptions({ layout: { title: 'Visita', backHref: '/tecnico/visitas' } });

const busy = ref<'start' | 'measure' | null>(null);
const confirmComplete = ref(false);

const hasDraft = computed(() =>
    props.measurements.some((m) => m.status.value === 'draft'),
);
const timeRange = computed(() => {
    const start = formatDate(props.visit.starts_at, 'time');
    const end = formatDate(props.visit.ends_at, 'time');

    return start && end ? `${start} – ${end}` : (start ?? '');
});
const telHref = computed(() =>
    props.visit.phone
        ? `tel:${props.visit.phone.replace(/[^\d+]/g, '')}`
        : null,
);

function start() {
    router.post(
        visitas.iniciar(props.visit.uuid).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = 'start'),
            onFinish: () => (busy.value = null),
        },
    );
}

function measure() {
    router.post(
        visitas.medicion(props.visit.uuid).url,
        {},
        {
            onStart: () => (busy.value = 'measure'),
            onFinish: () => (busy.value = null),
        },
    );
}

function complete(): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(
            visitas.completar(props.visit.uuid).url,
            {},
            {
                onSuccess: () => resolve(),
                onError: () =>
                    reject(
                        new Error(
                            'No se pudo completar la visita. Inténtalo de nuevo.',
                        ),
                    ),
            },
        );
    });
}
</script>

<template>
    <Head :title="`Visita ${props.visit.folio}`" />

    <div
        class="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-5 pb-10 md:py-8"
    >
        <header class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <StatusBadge
                    v-if="props.visit.status"
                    :label="props.visit.status.label"
                    :tone="props.visit.status.tone"
                />
                <span class="text-xs text-muted-foreground">{{
                    props.visit.folio
                }}</span>
            </div>
            <h1 class="font-display text-2xl font-semibold tracking-tight">
                {{ props.visit.customer_first_name ?? 'Cliente' }}
            </h1>
            <p class="flex items-start gap-1.5 text-sm text-muted-foreground">
                <Clock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>
                    <span class="block first-letter:uppercase">{{
                        formatDate(props.visit.starts_at, 'full')
                    }}</span>
                    <span
                        class="block font-medium text-foreground tabular-nums"
                        >{{ timeRange }}</span
                    >
                </span>
            </p>
        </header>

        <div class="grid grid-cols-2 gap-3">
            <Button
                v-if="telHref"
                as-child
                size="lg"
                variant="outline"
                class="h-14 text-base"
            >
                <a :href="telHref"><Phone class="size-5" /> Llamar</a>
            </Button>
            <Button
                v-else
                size="lg"
                variant="outline"
                class="h-14 text-base"
                disabled
            >
                <Phone class="size-5" /> Sin teléfono
            </Button>
            <Button
                v-if="props.visit.address"
                as-child
                size="lg"
                variant="outline"
                class="h-14 text-base"
            >
                <a
                    :href="props.visit.address.maps_url"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <Navigation class="size-5" /> Abrir en Mapas
                </a>
            </Button>
        </div>

        <section
            class="rounded-xl border bg-card p-4"
            aria-labelledby="visita-lugar"
        >
            <h2
                id="visita-lugar"
                class="flex items-center gap-2 text-sm font-semibold"
            >
                <MapPin class="size-4 text-primary" aria-hidden="true" />
                Dirección
            </h2>
            <p class="mt-2 text-base">
                {{
                    props.visit.address?.one_line ?? 'Sin dirección registrada'
                }}
            </p>
            <p
                v-if="props.visit.address?.references"
                class="mt-1 text-sm text-muted-foreground"
            >
                Referencias: {{ props.visit.address.references }}
            </p>
            <dl class="mt-4 grid gap-3 text-sm">
                <div v-if="props.visit.access_notes">
                    <dt class="font-medium">Acceso</dt>
                    <dd
                        class="mt-0.5 whitespace-pre-line text-muted-foreground"
                    >
                        {{ props.visit.access_notes }}
                    </dd>
                </div>
                <div v-if="props.visit.customer_notes">
                    <dt class="font-medium">Notas del cliente</dt>
                    <dd
                        class="mt-0.5 whitespace-pre-line text-muted-foreground"
                    >
                        {{ props.visit.customer_notes }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="flex flex-col gap-3" aria-label="Acciones de la visita">
            <Button
                v-if="props.can.start"
                size="lg"
                class="h-14 text-base"
                :disabled="busy !== null"
                @click="start"
            >
                <Spinner v-if="busy === 'start'" />
                <Play v-else class="size-5" />
                Iniciar visita
            </Button>
            <Button
                v-if="props.can.measure"
                size="lg"
                :variant="
                    props.can.has_submitted && !hasDraft ? 'outline' : 'default'
                "
                class="h-14 text-base"
                :disabled="busy !== null"
                @click="measure"
            >
                <Spinner v-if="busy === 'measure'" />
                <Ruler v-else class="size-5" />
                {{
                    hasDraft
                        ? 'Continuar medición'
                        : props.can.has_submitted
                          ? 'Abrir medición'
                          : 'Registrar medición'
                }}
            </Button>
            <template v-if="props.can.complete">
                <Button
                    size="lg"
                    variant="secondary"
                    class="h-14 text-base"
                    :disabled="!props.can.has_submitted"
                    @click="confirmComplete = true"
                >
                    <CheckCircle2 class="size-5" /> Completar visita
                </Button>
                <p
                    v-if="!props.can.has_submitted"
                    class="text-center text-sm text-muted-foreground"
                >
                    Envía la medición para poder completar la visita.
                </p>
            </template>
            <p
                v-if="!props.can.start && !props.can.measure"
                class="text-center text-sm text-muted-foreground"
            >
                No hay acciones pendientes para esta visita.
            </p>
        </section>

        <section
            v-if="props.measurements.length"
            class="flex flex-col gap-2"
            aria-labelledby="visita-mediciones"
        >
            <h2
                id="visita-mediciones"
                class="text-sm font-semibold text-muted-foreground"
            >
                Mediciones
            </h2>
            <Link
                v-for="m in props.measurements"
                :key="m.id"
                :href="mediciones.edit(m.id).url"
                class="flex min-h-14 items-center gap-3 rounded-xl border bg-card px-4 py-3 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <div class="min-w-0 flex-1">
                    <p class="font-medium">Versión {{ m.version }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ m.items_count }}
                        {{ m.items_count === 1 ? 'medida' : 'medidas' }}
                        <template v-if="m.areas.length">
                            · {{ m.areas.join(', ') }}</template
                        >
                    </p>
                </div>
                <StatusBadge
                    :label="m.status.label"
                    :tone="m.status.tone"
                    size="sm"
                />
                <ChevronRight
                    class="size-5 text-muted-foreground"
                    aria-hidden="true"
                />
            </Link>
        </section>

        <p class="flex items-start gap-2 text-xs text-muted-foreground">
            <ShieldCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Por privacidad no registramos tu ubicación: solo usamos la dirección
            del cliente para orientarte.
        </p>
    </div>

    <ConfirmDialog
        v-model:open="confirmComplete"
        title="¿Completar la visita?"
        description="Confirma que terminaste de medir y que la medición ya está enviada. El equipo de diseño continuará con el proyecto."
        confirm-label="Completar visita"
        loading-label="Completando…"
        :confirm="complete"
    />
</template>
