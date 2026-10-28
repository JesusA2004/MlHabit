<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    CalendarClock,
    CalendarDays,
    CircleSlash,
    ClipboardList,
    FolderKanban,
    Hammer,
    Mail,
    MessagesSquare,
    Phone,
    Plus,
    ReceiptText,
    Ruler,
    Sparkles,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    EmptyState,
    Money,
    PageContainer,
    ProgressSteps,
    SearchSelect,
    SectionCard,
    StatusBadge,
    Timeline,
    UserAvatar,
    toneClasses,
} from '@/components/ds';
import type { SearchSelectOption, TimelineEvent } from '@/components/ds';
import AppointmentCard from '@/components/portal/AppointmentCard.vue';
import ProgressRing from '@/components/portal/ProgressRing.vue';
import RequestCard from '@/components/portal/RequestCard.vue';
import StatTile from '@/components/portal/StatTile.vue';
import type {
    ActivityItem,
    PortalAppointment,
    PortalRequest,
    RequestStage,
} from '@/components/portal/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { seleccionar } from '@/routes/portal/proyectos';
import type {
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
    actions: (PendingAction & { external?: boolean })[];
    nextAppointment: PortalAppointment | null;
    upcomingCount: number;
    advisor: {
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    requests: PortalRequest[];
    requestStages: RequestStage[];
    stats: {
        open_requests: number;
        quotes_pending: number;
        upcoming: number;
        paid: string;
        pending: string;
        count: number;
    };
    activity: ActivityItem[];
}>();

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Buenos días';
    }

    return hour < 19 ? 'Buenas tardes' : 'Buenas noches';
});
const today = new Intl.DateTimeFormat('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
}).format(new Date());

// ── Proyecto ──────────────────────────────────────────────────────────
const step = computed(() => props.project?.status.journey_step ?? 1);
const current = computed(() => String(Math.max(1, step.value)));
const finished = computed(() => step.value === 7 && !!props.project?.completed);
const totalSteps = computed(() => props.journey.length || 7);
const projectProgress = computed(() => {
    if (!props.project || props.project.cancelled) {
        return 0;
    }

    if (finished.value) {
        return 100;
    }

    return (
        ((Math.max(1, step.value) - 1) / totalSteps.value) * 100 +
        100 / totalSteps.value / 2
    );
});
const currentStep = computed(
    () => props.journey.find((s) => s.key === current.value) ?? null,
);

// Sin proyecto: el avance es el de la solicitud más reciente en curso.
const leadRequest = computed(
    () => props.requests.find((r) => !r.closed && !r.project) ?? null,
);
const requestProgress = computed(() =>
    leadRequest.value
        ? Math.round(
              ((leadRequest.value.stage + 0.5) /
                  Math.max(1, props.requestStages.length)) *
                  100,
          )
        : 0,
);

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

const primaryAction = computed(() =>
    props.project?.cancelled ? null : (props.actions[0] ?? null),
);

// ── Actividad ─────────────────────────────────────────────────────────
const activityIcons = {
    project: FolderKanban,
    payment: Wallet,
    request: ClipboardList,
    consultation: MessagesSquare,
    visit: Ruler,
} as const;
const activityTones = {
    project: 'brand',
    payment: 'success',
    request: 'info',
    consultation: 'info',
    visit: 'info',
} as const;
const showAllActivity = ref(false);
const timeline = computed<TimelineEvent[]>(() =>
    (showAllActivity.value ? props.activity : props.activity.slice(0, 6)).map(
        (e) => ({
            id: e.key,
            title: e.title,
            description: e.description,
            date: e.date,
            icon: activityIcons[e.kind],
            tone: activityTones[e.kind],
        }),
    ),
);
</script>

<template>
    <Head title="Mi portal" />

    <PageContainer class="flex flex-col gap-6 md:gap-8">
        <!-- Bienvenida -->
        <section
            aria-labelledby="hero-title"
            class="portal-hero relative isolate overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-8"
        >
            <div
                class="portal-grid-lines pointer-events-none absolute inset-0 -z-10"
                aria-hidden="true"
            />
            <div
                class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between"
            >
                <div class="max-w-2xl min-w-0">
                    <p class="text-sm text-white/70 first-letter:uppercase">
                        {{ today }}
                    </p>
                    <h1
                        id="hero-title"
                        class="mt-1 font-display text-3xl leading-tight font-semibold tracking-tight sm:text-4xl"
                    >
                        {{ greeting }}, {{ firstName }}
                    </h1>

                    <template v-if="project">
                        <p class="mt-3 text-base text-white/85">
                            <span class="font-medium text-white">{{
                                project.name
                            }}</span>
                            <span class="text-white/60">
                                · {{ project.folio }}</span
                            >
                        </p>
                        <p
                            class="mt-1 max-w-xl text-sm leading-relaxed text-white/75"
                        >
                            {{ project.status.client_description }}
                        </p>
                    </template>
                    <p
                        v-else-if="leadRequest"
                        class="mt-3 max-w-xl text-sm leading-relaxed text-white/80"
                    >
                        Tu solicitud {{ leadRequest.folio }} va en
                        <span class="font-medium text-white">{{
                            requestStages[leadRequest.stage]?.title
                        }}</span
                        >. Aquí verás cada paso hasta que tu proyecto esté
                        listo.
                    </p>
                    <p
                        v-else
                        class="mt-3 max-w-xl text-sm leading-relaxed text-white/80"
                    >
                        Este es tu espacio en ML Habitat: solicitudes, citas,
                        cotizaciones, pagos y el avance de tu proyecto, todo en
                        un solo lugar.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <template v-if="primaryAction">
                            <a
                                v-if="primaryAction.external"
                                :href="primaryAction.href"
                                class="group inline-flex h-11 items-center gap-2 rounded-md bg-white px-4 text-sm font-semibold text-espresso transition hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                            >
                                {{ primaryAction.cta }}
                                <ArrowUpRight
                                    class="size-4 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                    aria-hidden="true"
                                />
                            </a>
                            <Link
                                v-else
                                :href="primaryAction.href"
                                class="group inline-flex h-11 items-center gap-2 rounded-md bg-white px-4 text-sm font-semibold text-espresso transition hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                            >
                                {{ primaryAction.cta }}
                                <ArrowRight
                                    class="size-4 transition group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </template>
                        <a
                            v-else-if="!project && !requests.length"
                            href="/solicitar"
                            class="inline-flex h-11 items-center gap-2 rounded-md bg-white px-4 text-sm font-semibold text-espresso transition hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            Hacer una solicitud
                        </a>
                        <Link
                            v-if="nextAppointment"
                            href="/portal/citas"
                            class="inline-flex h-11 items-center gap-2 rounded-md border border-white/25 px-4 text-sm font-medium text-white transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                        >
                            <CalendarClock class="size-4" aria-hidden="true" />
                            Mi próxima cita
                        </Link>
                    </div>
                </div>

                <div
                    v-if="project || leadRequest"
                    class="flex items-center gap-4 md:flex-col md:items-center md:text-center"
                >
                    <ProgressRing
                        :value="project ? projectProgress : requestProgress"
                        :label="
                            project
                                ? 'Avance de tu proyecto'
                                : 'Avance de tu solicitud'
                        "
                        inverted
                        :size="128"
                    />
                    <div class="min-w-0">
                        <p class="text-xs text-white/60">
                            {{
                                project
                                    ? 'Etapa actual'
                                    : 'Etapa de tu solicitud'
                            }}
                        </p>
                        <p class="text-sm font-medium text-white">
                            <template v-if="project">
                                <template v-if="project.cancelled"
                                    >Proyecto inactivo</template
                                >
                                <template v-else-if="finished"
                                    >¡Proyecto entregado!</template
                                >
                                <template v-else
                                    >{{ currentStep?.title }} · {{ current }}/{{
                                        totalSteps
                                    }}</template
                                >
                            </template>
                            <template v-else-if="leadRequest">{{
                                requestStages[leadRequest.stage]?.title
                            }}</template>
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="projects.length > 1"
                class="mt-6 w-full border-t border-white/10 pt-4 sm:w-80"
            >
                <label
                    for="portal-project"
                    class="mb-1.5 block text-xs font-medium text-white/70"
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
                    class="text-foreground"
                    @update:model-value="switchProject"
                />
            </div>
        </section>

        <!-- Indicadores -->
        <div
            class="portal-stagger grid grid-cols-2 gap-3 lg:grid-cols-4"
            style="--stagger-base: 180ms"
        >
            <StatTile
                label="Solicitudes en curso"
                href="/portal/solicitudes"
                :icon="ClipboardList"
                tone="info"
                >{{ stats.open_requests }}</StatTile
            >
            <StatTile
                label="Cotizaciones por revisar"
                href="/portal/cotizaciones"
                :icon="ReceiptText"
                :tone="stats.quotes_pending ? 'warning' : 'brand'"
                >{{ stats.quotes_pending }}</StatTile
            >
            <StatTile
                label="Citas próximas"
                href="/portal/citas"
                :icon="CalendarDays"
                tone="brand"
                >{{ stats.upcoming }}</StatTile
            >
            <StatTile
                :label="
                    Number(stats.pending) > 0 ? 'Por pagar' : 'Total pagado'
                "
                href="/portal/pagos"
                :icon="Wallet"
                :tone="Number(stats.pending) > 0 ? 'warning' : 'success'"
            >
                <Money
                    :value="
                        Number(stats.pending) > 0 ? stats.pending : stats.paid
                    "
                    :decimals="false"
                />
            </StatTile>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="flex min-w-0 flex-col gap-6">
                <!-- Pendientes -->
                <section
                    v-if="actions.length && !project?.cancelled"
                    aria-labelledby="actions-title"
                    class="flex flex-col gap-3"
                >
                    <h2
                        id="actions-title"
                        class="flex items-center gap-2 text-base font-semibold text-foreground"
                    >
                        Lo que necesitamos de ti
                        <span
                            class="grid size-5 place-items-center rounded-full bg-warning text-[11px] font-semibold text-warning-foreground tabular-nums"
                            >{{ actions.length }}</span
                        >
                    </h2>
                    <ul
                        class="portal-stagger grid gap-3 sm:grid-cols-2"
                        style="--stagger-base: 260ms"
                    >
                        <li
                            v-for="(action, i) in actions"
                            :key="action.key"
                            :class="
                                i === 0 && actions.length % 2 === 1
                                    ? 'sm:col-span-2'
                                    : ''
                            "
                        >
                            <component
                                :is="action.external ? 'a' : Link"
                                :href="action.href"
                                :class="
                                    cn(
                                        'group portal-lift flex h-full flex-col gap-4 rounded-xl border p-5 shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
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
                                                'relative mt-1.5 size-2 shrink-0 rounded-full',
                                                toneClasses[action.tone].dot,
                                            )
                                        "
                                        aria-hidden="true"
                                    />
                                    <div class="min-w-0">
                                        <p class="font-medium text-foreground">
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
                                    class="mt-auto flex flex-wrap items-center justify-between gap-2 pl-5"
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
                            </component>
                        </li>
                    </ul>
                </section>
                <div
                    v-else-if="project && !project.cancelled"
                    class="flex items-start gap-3 rounded-xl border border-dashed bg-card/60 p-4 text-sm text-muted-foreground"
                >
                    <Hammer
                        class="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <p>
                        Por ahora no tienes nada pendiente. Nosotros seguimos
                        trabajando y te avisaremos cuando necesitemos algo.
                    </p>
                </div>

                <!-- Recorrido del proyecto -->
                <SectionCard
                    v-if="project"
                    title="Recorrido de tu proyecto"
                    description="Siete pasos desde la medición hasta la entrega."
                    :icon="FolderKanban"
                >
                    <template #actions>
                        <StatusBadge
                            :tone="project.status.tone"
                            :label="project.status.label"
                            size="sm"
                        />
                    </template>
                    <div
                        v-if="project.cancelled"
                        class="flex items-start gap-3 rounded-xl bg-muted/50 p-4 text-sm text-muted-foreground"
                    >
                        <CircleSlash
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <p>
                            Este proyecto ya no está activo. Si quieres
                            retomarlo, escríbele a tu asesor: con gusto te
                            ayudamos.
                        </p>
                    </div>
                    <ProgressSteps
                        v-else
                        :steps="journey"
                        :current="current"
                        :completed="finished"
                        label="Recorrido de tu proyecto"
                    />
                </SectionCard>

                <!-- Solicitudes (sin proyecto) -->
                <section
                    v-else-if="requests.length"
                    aria-labelledby="requests-title"
                    class="flex flex-col gap-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2
                            id="requests-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Tus solicitudes
                        </h2>
                        <Button as-child variant="ghost" size="sm">
                            <Link href="/portal/solicitudes"
                                >Ver todas
                                <ArrowRight class="size-4" aria-hidden="true"
                            /></Link>
                        </Button>
                    </div>
                    <div
                        class="portal-stagger flex flex-col gap-3"
                        style="--stagger-base: 300ms"
                    >
                        <RequestCard
                            v-for="r in requests.slice(0, 2)"
                            :key="r.uuid"
                            :request="r"
                            :stages="requestStages"
                        />
                    </div>
                </section>

                <EmptyState
                    v-else
                    :icon="Sparkles"
                    title="Empecemos tu proyecto"
                    description="Cuéntanos qué espacio quieres transformar. Te asesoramos, medimos y diseñamos tu mueble a la medida."
                >
                    <Button as-child>
                        <a href="/solicitar"
                            ><Plus class="size-4" aria-hidden="true" /> Hacer
                            una solicitud</a
                        >
                    </Button>
                </EmptyState>

                <!-- Actividad reciente -->
                <SectionCard
                    title="Actividad reciente"
                    description="Cada avance, pago y cita queda registrado aquí."
                >
                    <Timeline
                        v-if="timeline.length"
                        :events="timeline"
                        relative-dates
                    />
                    <p v-else class="text-sm text-muted-foreground">
                        Aún no hay novedades. Te contaremos aquí cada paso.
                    </p>
                    <template v-if="activity.length > 6" #footer>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="showAllActivity = !showAllActivity"
                        >
                            {{
                                showAllActivity
                                    ? 'Ver menos'
                                    : 'Ver toda la actividad'
                            }}
                        </Button>
                    </template>
                </SectionCard>
            </div>

            <aside class="flex flex-col gap-6">
                <section
                    aria-labelledby="next-title"
                    class="flex flex-col gap-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2
                            id="next-title"
                            class="text-base font-semibold text-foreground"
                        >
                            Tu próxima cita
                        </h2>
                        <Link
                            v-if="upcomingCount > 1"
                            href="/portal/citas"
                            class="rounded-md text-sm text-primary underline-offset-4 hover:underline"
                            >+{{ upcomingCount - 1 }} más</Link
                        >
                    </div>
                    <AppointmentCard
                        v-if="nextAppointment"
                        class="portal-pop"
                        style="--pop-delay: 320ms"
                        :appointment="nextAppointment"
                        featured
                    />
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 rounded-xl border border-dashed bg-card/60 p-6 text-center"
                    >
                        <span
                            class="grid size-11 place-items-center rounded-full bg-muted text-muted-foreground"
                            aria-hidden="true"
                            ><CalendarDays class="size-5"
                        /></span>
                        <p class="text-sm font-medium text-foreground">
                            Sin citas próximas
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Tus asesorías y visitas aparecerán aquí.
                        </p>
                    </div>
                </section>

                <SectionCard
                    v-if="project && requests.length"
                    title="Tus solicitudes"
                    :icon="ClipboardList"
                    flush
                >
                    <ul class="divide-y">
                        <li v-for="r in requests" :key="r.uuid">
                            <Link
                                href="/portal/solicitudes"
                                class="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-muted/40 sm:px-5"
                            >
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-sm font-medium text-foreground"
                                        >{{
                                            r.categories.join(', ') ||
                                            'Mobiliario a la medida'
                                        }}</span
                                    >
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                        >{{ r.folio }}</span
                                    >
                                </span>
                                <StatusBadge
                                    :tone="r.status.tone"
                                    :label="r.status.label"
                                    size="sm"
                                    class="shrink-0"
                                />
                            </Link>
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard v-if="advisor" title="Tu asesor">
                    <div class="flex items-center gap-3">
                        <UserAvatar :name="advisor.name" size="lg" />
                        <div class="min-w-0">
                            <p class="truncate font-medium text-foreground">
                                {{ advisor.name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Te acompaña en todo tu proyecto.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                        <Button
                            v-if="advisor.phone"
                            as-child
                            variant="outline"
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
    </PageContainer>
</template>
