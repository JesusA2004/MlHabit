<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarClock,
    CircleSlash,
    Hammer,
    Mail,
    MapPin,
    Phone,
    Sparkles,
    Video,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    DateText,
    EmptyState,
    Money,
    PageContainer,
    ProgressSteps,
    SearchSelect,
    SectionCard,
    StatusBadge,
    Timeline,
} from '@/components/ds';
import type { SearchSelectOption, TimelineEvent } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { toneClasses } from '@/components/ds/tones';
import { cn } from '@/lib/utils';
import { seleccionar } from '@/routes/portal/proyectos';
import type {
    Appointment,
    JourneyStep,
    PendingAction,
    ProjectEventItem,
    ProjectOption,
    ProjectSummary,
} from '@/types/projects';

type PortalProject = ProjectSummary & {
    address: string | null;
    cancelled: boolean;
    completed: boolean;
};

const props = defineProps<{
    firstName: string;
    projects: ProjectOption[];
    project: PortalProject | null;
    journey: JourneyStep[];
    events: ProjectEventItem[];
    actions: PendingAction[];
    nextAppointment: Appointment | null;
    advisor: {
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
}>();

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Buenos días';
    }

    return hour < 19 ? 'Buenas tardes' : 'Buenas noches';
});

const step = computed(() => props.project?.status.journey_step ?? 1);
const current = computed(() => String(Math.max(1, step.value)));
const finished = computed(() => step.value === 7 && !!props.project?.completed);

const projectOptions = computed<SearchSelectOption<string>[]>(() =>
    props.projects.map((p) => ({
        value: p.uuid,
        label: p.name,
        description: `${p.folio} · ${p.status}`,
    })),
);
const selected = ref<string | null>(props.project?.uuid ?? null);
const switching = ref(false);

function switchProject(uuid: string | null) {
    if (!uuid || uuid === props.project?.uuid) {
        return;
    }

    switching.value = true;
    router.post(
        seleccionar(uuid).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (switching.value = false),
        },
    );
}

const timeline = computed<TimelineEvent[]>(() =>
    props.events.map((e) => ({
        id: e.id,
        title: e.title,
        description: e.body,
        date: e.occurred_at,
        tone: e.to_status ? 'brand' : 'neutral',
    })),
);
</script>

<template>
    <Head title="Mi proyecto" />

    <PageContainer class="flex flex-col gap-6 md:gap-8">
        <!-- Saludo y selector -->
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ greeting }}, {{ firstName }}
                </p>
                <h1
                    class="mt-1 font-display text-2xl font-semibold tracking-tight text-foreground md:text-3xl"
                >
                    {{ project ? project.name : 'Tu espacio en ML Habitat' }}
                </h1>
                <p v-if="project" class="mt-1 text-sm text-muted-foreground">
                    Proyecto {{ project.folio
                    }}<template v-if="project.address">
                        · {{ project.address }}</template
                    >
                </p>
            </div>
            <div v-if="projects.length > 1" class="w-full sm:w-72">
                <label
                    for="portal-project"
                    class="mb-1.5 block text-xs font-medium text-muted-foreground"
                >
                    Ver otro proyecto
                </label>
                <SearchSelect
                    id="portal-project"
                    v-model="selected"
                    :options="projectOptions"
                    :disabled="switching"
                    placeholder="Elige un proyecto"
                    search-placeholder="Buscar proyecto…"
                    @update:model-value="switchProject"
                />
            </div>
        </header>

        <!-- Sin proyecto -->
        <EmptyState
            v-if="!project"
            :icon="Sparkles"
            title="Aún estamos preparando tu proyecto"
            description="En cuanto tu asesor lo registre, aquí verás cada paso: diseño, cotización, fabricación e instalación. Si ya tienes una cita, la encontrarás en Citas."
        >
            <Button as-child variant="outline"
                ><Link href="/portal/citas">Ver mis citas</Link></Button
            >
        </EmptyState>

        <template v-else>
            <!-- Recorrido -->
            <section
                aria-labelledby="journey-title"
                :class="
                    cn(
                        'relative overflow-hidden rounded-2xl border bg-card p-5 shadow-xs sm:p-7',
                        project.cancelled && 'bg-muted/40',
                    )
                "
            >
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary/70 via-oak/70 to-primary/30"
                    aria-hidden="true"
                />
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="max-w-xl">
                        <h2
                            id="journey-title"
                            class="text-xs font-medium text-muted-foreground"
                        >
                            ¿En qué va tu proyecto?
                        </h2>
                        <p
                            class="mt-2 font-display text-xl leading-snug font-semibold text-foreground md:text-2xl"
                        >
                            {{ project.status.client_description }}
                        </p>
                    </div>
                    <StatusBadge
                        :tone="project.status.tone"
                        :label="project.status.label"
                        class="self-start"
                    />
                </div>

                <!-- En móvil la acción principal no queda bajo el recorrido. -->
                <Button
                    v-if="actions.length && !project.cancelled"
                    as-child
                    size="lg"
                    class="mt-5 h-12 w-full text-base md:hidden"
                >
                    <Link :href="actions[0].href">
                        {{ actions[0].cta }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>

                <div
                    v-if="project.cancelled"
                    class="mt-6 flex items-start gap-3 rounded-xl bg-background p-4 text-sm text-muted-foreground"
                >
                    <CircleSlash
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <p>
                        Este proyecto ya no está activo. Si quieres retomarlo,
                        escríbele a tu asesor: con gusto te ayudamos.
                    </p>
                </div>
                <ProgressSteps
                    v-else
                    class="mt-7"
                    :steps="journey"
                    :current="current"
                    :completed="finished"
                    label="Recorrido de tu proyecto"
                />
            </section>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="flex min-w-0 flex-col gap-6">
                    <!-- Pendientes -->
                    <section
                        v-if="actions.length"
                        aria-labelledby="actions-title"
                        class="flex flex-col gap-3"
                    >
                        <h2
                            id="actions-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Lo que necesitamos de ti
                        </h2>
                        <ul class="grid gap-3 sm:grid-cols-2">
                            <li
                                v-for="(action, i) in actions"
                                :key="action.key"
                                :class="
                                    i === 0 && actions.length % 2 === 1
                                        ? 'sm:col-span-2'
                                        : ''
                                "
                            >
                                <Link
                                    :href="action.href"
                                    :class="
                                        cn(
                                            'group flex h-full flex-col gap-4 rounded-xl border p-5 shadow-xs transition hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            i === 0
                                                ? 'border-primary/20 bg-primary-soft'
                                                : 'bg-card hover:border-primary/40',
                                        )
                                    "
                                >
                                    <div class="flex items-start gap-3">
                                        <span
                                            :class="
                                                cn(
                                                    'mt-1 size-2 shrink-0 rounded-full',
                                                    toneClasses[action.tone]
                                                        .dot,
                                                )
                                            "
                                            aria-hidden="true"
                                        />
                                        <div class="min-w-0">
                                            <p
                                                class="font-medium text-foreground"
                                            >
                                                {{ action.title }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-sm text-muted-foreground"
                                            >
                                                {{ action.description }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="mt-auto flex items-center justify-between gap-2 pl-5"
                                    >
                                        <Money
                                            v-if="action.amount"
                                            :value="action.amount"
                                            class="font-display text-xl font-semibold tracking-tight text-foreground"
                                        />
                                        <span v-else />
                                        <span
                                            :class="
                                                cn(
                                                    'inline-flex h-10 items-center gap-1.5 rounded-md px-4 text-sm font-medium transition-colors',
                                                    i === 0
                                                        ? 'bg-primary text-primary-foreground group-hover:bg-primary-hover'
                                                        : 'border bg-card text-foreground group-hover:bg-muted',
                                                )
                                            "
                                        >
                                            {{ action.cta }}
                                            <ArrowRight
                                                class="size-4 transition group-hover:translate-x-0.5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                    </div>
                                </Link>
                            </li>
                        </ul>
                    </section>
                    <div
                        v-else-if="!project.cancelled"
                        class="flex items-start gap-3 rounded-xl border border-dashed bg-card/60 p-4 text-sm text-muted-foreground"
                    >
                        <Hammer
                            class="mt-0.5 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <p>
                            Por ahora no tienes nada pendiente. Nosotros
                            seguimos trabajando y te avisaremos cuando
                            necesitemos algo.
                        </p>
                    </div>

                    <!-- Cronología -->
                    <SectionCard
                        title="Novedades de tu proyecto"
                        description="Cada avance importante queda registrado aquí."
                    >
                        <Timeline
                            v-if="timeline.length"
                            :events="timeline"
                            relative-dates
                        />
                        <p v-else class="text-sm text-muted-foreground">
                            Aún no hay novedades. Te contaremos aquí cada paso.
                        </p>
                    </SectionCard>
                </div>

                <aside class="flex flex-col gap-6">
                    <SectionCard title="Tu próxima cita" :icon="CalendarClock">
                        <div v-if="nextAppointment" class="flex flex-col gap-2">
                            <p class="font-medium text-foreground">
                                {{ nextAppointment.title }}
                            </p>
                            <DateText
                                :value="nextAppointment.starts_at"
                                format="datetime"
                                class="text-sm text-foreground"
                            />
                            <p
                                v-if="nextAppointment.with"
                                class="text-sm text-muted-foreground"
                            >
                                Con {{ nextAppointment.with }}
                            </p>
                            <p
                                v-if="nextAppointment.location"
                                class="flex items-start gap-1.5 text-sm text-muted-foreground"
                            >
                                <MapPin
                                    class="mt-0.5 size-3.5 shrink-0"
                                    aria-hidden="true"
                                />{{ nextAppointment.location }}
                            </p>
                            <Button
                                v-if="nextAppointment.meeting_url"
                                as-child
                                size="sm"
                                class="mt-1 self-start"
                            >
                                <a
                                    :href="nextAppointment.meeting_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Video class="size-4" aria-hidden="true" />
                                    Entrar a la videollamada
                                </a>
                            </Button>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No tienes citas próximas.
                        </p>
                        <template #footer>
                            <Button as-child variant="ghost" size="sm"
                                ><Link href="/portal/citas"
                                    >Ver todas mis citas</Link
                                ></Button
                            >
                        </template>
                    </SectionCard>

                    <SectionCard v-if="advisor" title="Tu asesor">
                        <p class="font-medium text-foreground">
                            {{ advisor.name }}
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            Te acompaña en todo tu proyecto.
                        </p>
                        <div class="mt-3 flex flex-col gap-2">
                            <Button
                                v-if="advisor.phone"
                                as-child
                                variant="outline"
                                size="sm"
                                class="justify-start"
                            >
                                <a :href="`tel:${advisor.phone}`"
                                    ><Phone class="size-4" aria-hidden="true" />
                                    {{ advisor.phone }}</a
                                >
                            </Button>
                            <Button
                                v-if="advisor.email"
                                as-child
                                variant="outline"
                                size="sm"
                                class="justify-start"
                            >
                                <a :href="`mailto:${advisor.email}`"
                                    ><Mail class="size-4" aria-hidden="true" />
                                    Escribir un correo</a
                                >
                            </Button>
                        </div>
                    </SectionCard>
                </aside>
            </div>
        </template>
    </PageContainer>
</template>
