<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarClock, Home, MessageCircle, Ruler, Video } from '@lucide/vue';
import { computed } from 'vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import Timeline from '@/components/ds/Timeline.vue';
import SuccessMark from '@/components/web/visual/SuccessMark.vue';
import type { TimelineEvent } from '@/components/ds/Timeline.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    lead: {
        folio: string;
        firstName: string;
        email: string | null;
        preferredContact: string | null;
        postalCode: string | null;
        categories: string[];
    };
    consultation: {
        enabled: boolean;
        isFree: boolean;
        price: string;
        durationMinutes: number;
        url: string | null;
    };
    visit: { covered: boolean; acceptsVisits: boolean; url: string | null };
}>();

const contactBy = computed(
    () =>
        (
            ({
                whatsapp: 'por WhatsApp',
                phone: 'por teléfono',
                email: 'por correo',
            }) as Record<string, string>
        )[props.lead.preferredContact ?? ''] ?? 'por el medio que elegiste',
);

const nextSteps = computed<TimelineEvent[]>(() => [
    {
        id: 1,
        title: 'Revisamos tu solicitud',
        description: `Un asesor de ML Habitat la revisa y te contacta ${contactBy.value} en horario laboral.`,
        icon: MessageCircle,
        tone: 'brand',
    },
    {
        id: 2,
        title: props.consultation.isFree
            ? 'Asesoría remota gratuita'
            : 'Asesoría remota',
        description: `Unos ${props.consultation.durationMinutes} minutos por videollamada, llamada o WhatsApp para entender tu espacio, estilo y presupuesto.`,
        icon: Video,
        tone: 'info',
    },
    {
        id: 3,
        title: 'Visita técnica a domicilio',
        description:
            'Tomamos medidas exactas y revisamos instalaciones para que el diseño quede perfecto.',
        icon: Ruler,
        tone: 'info',
    },
    {
        id: 4,
        title: 'Diseño y propuesta',
        description: 'Te presentamos tu diseño y una cotización detallada.',
        icon: Home,
        tone: 'neutral',
    },
]);
</script>

<template>
    <Head title="Recibimos tu solicitud" />

    <section class="motion-calm relative isolate overflow-hidden bg-background">
        <div
            class="tex-blueprint pointer-events-none absolute inset-x-0 top-0 -z-10 h-96 [mask-image:linear-gradient(to_bottom,#000,transparent)]"
            aria-hidden="true"
        />
        <div
            class="container-site grid gap-10 py-12 md:py-20 lg:grid-cols-[minmax(0,1fr)_24rem] lg:gap-16"
        >
            <div data-motion-stagger="110" data-motion="fade-up">
                <SuccessMark />
                <h1
                    class="mt-5 text-display-md font-display tracking-tight text-foreground"
                >
                    ¡Gracias, {{ lead.firstName }}! Recibimos tu solicitud.
                </h1>
                <p class="mt-3 max-w-xl text-lg text-muted-foreground">
                    <template v-if="lead.categories.length"
                        >Anotamos tu interés en
                        {{ lead.categories.join(', ').toLowerCase() }}.
                    </template>
                    <template v-if="lead.email"
                        >Te enviamos una confirmación a
                        <strong class="font-medium text-foreground">{{
                            lead.email
                        }}</strong
                        >.</template
                    >
                </p>

                <div
                    class="mt-8 inline-flex flex-col rounded-xl border bg-card px-6 py-4 shadow-xs"
                >
                    <span class="text-xs text-muted-foreground">Tu folio</span>
                    <span
                        class="font-display text-2xl font-semibold tracking-tight text-foreground tabular-nums"
                        >{{ lead.folio }}</span
                    >
                    <span class="mt-1 text-xs text-muted-foreground"
                        >Tenlo a la mano si nos escribes.</span
                    >
                </div>

                <h2
                    class="mt-12 font-display text-xl font-semibold tracking-tight"
                >
                    ¿Qué sigue?
                </h2>
                <Timeline class="mt-5 max-w-xl" :events="nextSteps" />
            </div>

            <aside class="space-y-4 lg:pt-16" aria-label="Siguientes pasos">
                <div
                    v-if="consultation.enabled && consultation.url"
                    class="rounded-xl border bg-card p-6 shadow-xs"
                >
                    <CalendarClock
                        class="size-5 text-primary"
                        aria-hidden="true"
                    />
                    <h2
                        class="mt-3 font-display text-lg font-semibold tracking-tight"
                    >
                        {{
                            consultation.isFree
                                ? 'Adelántate: agenda tu asesoría gratuita'
                                : 'Adelántate: agenda tu asesoría'
                        }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Elige el día y la hora que te acomoden; tu asesor llega
                        con ideas para tu espacio.
                        <template v-if="!consultation.isFree">
                            Por ahora el pago de la asesoría se coordina con tu
                            asesor.</template
                        >
                    </p>
                    <Button as-child class="mt-4 h-11 w-full">
                        <a :href="consultation.url">{{
                            consultation.isFree
                                ? 'Agenda tu asesoría gratuita'
                                : 'Agenda tu asesoría'
                        }}</a>
                    </Button>
                </div>

                <div class="rounded-xl border bg-card p-6">
                    <Ruler class="size-5 text-primary" aria-hidden="true" />
                    <h2
                        class="mt-3 font-display text-lg font-semibold tracking-tight"
                    >
                        Visita técnica a domicilio
                    </h2>
                    <template v-if="visit.url">
                        <p class="mt-1 text-sm text-muted-foreground">
                            Tenemos cobertura en tu código postal. Si ya sabes
                            lo que quieres, agenda la visita para tomar medidas.
                        </p>
                        <Button
                            as-child
                            variant="outline"
                            class="mt-4 h-11 w-full"
                        >
                            <a :href="visit.url">Agenda tu visita técnica</a>
                        </Button>
                    </template>
                    <p v-else class="mt-1 text-sm text-muted-foreground">
                        <template v-if="visit.covered"
                            >Atendemos tu zona; tu asesor te propondrá fechas
                            para la visita.</template
                        >
                        <template v-else>
                            Estamos revisando la cobertura para el C.P.
                            {{ lead.postalCode ?? 'que indicaste' }}. Tu asesor
                            te contactará para confirmar cómo podemos atenderte.
                        </template>
                    </p>
                </div>

                <FinishSwatches
                    class="mx-auto hidden w-48 opacity-90 lg:block"
                    :labels="false"
                />
            </aside>
        </div>
    </section>
</template>
