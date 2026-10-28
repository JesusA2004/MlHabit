<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    CalendarClock,
    Check,
    MapPin,
    Ruler,
} from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { DateText, StatusBadge } from '@/components/ds';
import type { PortalRequest, RequestStage } from '@/components/portal/types';
import { cn } from '@/lib/utils';

/**
 * Tarjeta de una solicitud: etapa actual en un riel de 5 pasos, lo que
 * pidió, sus citas y el siguiente paso. `compact` para el inicio.
 */
const props = withDefaults(
    defineProps<{
        request: PortalRequest;
        stages: RequestStage[];
        compact?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { compact: false },
);

const stage = computed(() => props.request.stage);
const progress = computed(() =>
    props.request.closed
        ? 0
        : Math.round((stage.value / (props.stages.length - 1)) * 100),
);
</script>

<template>
    <article
        :class="
            cn(
                'portal-lift flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs sm:p-5',
                request.closed && 'bg-card/70',
                props.class,
            )
        "
    >
        <header
            class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2"
        >
            <div class="min-w-0">
                <p class="text-xs text-muted-foreground tabular-nums">
                    Solicitud {{ request.folio }} ·
                    <DateText :value="request.created_at" format="medium" />
                </p>
                <h3
                    class="mt-1 font-display text-lg leading-snug font-semibold tracking-tight text-foreground"
                >
                    {{
                        request.categories.length
                            ? request.categories.join(', ')
                            : 'Mobiliario a la medida'
                    }}
                </h3>
            </div>
            <StatusBadge
                :tone="request.status.tone"
                :label="request.status.label"
                size="sm"
            />
        </header>

        <!-- Riel de etapas -->
        <div v-if="!request.closed">
            <div
                class="relative h-1.5 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                :aria-valuenow="progress"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="`Avance de la solicitud ${request.folio}`"
            >
                <div
                    class="portal-bar absolute inset-y-0 left-0 rounded-full bg-gradient-to-r from-primary to-oak"
                    :style="{ width: `${Math.max(progress, 6)}%` }"
                />
            </div>
            <ol
                v-if="!compact"
                class="mt-3 grid grid-cols-5 gap-1 text-[11px] leading-tight sm:text-xs"
            >
                <li
                    v-for="(s, i) in stages"
                    :key="s.key"
                    :class="
                        cn(
                            'flex flex-col gap-1',
                            i <= stage
                                ? 'text-foreground'
                                : 'text-muted-foreground',
                        )
                    "
                    :aria-current="i === stage ? 'step' : undefined"
                >
                    <span
                        :class="
                            cn(
                                'grid size-5 place-items-center rounded-full border text-[10px] font-semibold',
                                i < stage &&
                                    'border-primary bg-primary text-primary-foreground',
                                i === stage &&
                                    'border-primary bg-primary-soft text-primary',
                                i > stage && 'border-border bg-card',
                            )
                        "
                        aria-hidden="true"
                    >
                        <Check
                            v-if="i < stage"
                            class="size-3"
                            :stroke-width="3"
                        />
                        <template v-else>{{ i + 1 }}</template>
                    </span>
                    <span class="hidden sm:block">{{ s.title }}</span>
                    <span v-if="i === stage" class="sm:hidden">{{
                        s.title
                    }}</span>
                </li>
            </ol>
            <p v-else class="mt-2 text-xs text-muted-foreground">
                Etapa {{ stage + 1 }} de {{ stages.length }} ·
                {{ stages[stage]?.title }}
            </p>
        </div>

        <div
            v-if="
                !compact &&
                (request.place || request.timeframe || request.comments)
            "
            class="flex flex-col gap-2 text-sm"
        >
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-muted-foreground">
                <span
                    v-if="request.place"
                    class="inline-flex items-center gap-1.5"
                    ><MapPin class="size-3.5" aria-hidden="true" />{{
                        request.place
                    }}</span
                >
                <span v-if="request.timeframe">{{ request.timeframe }}</span>
                <span v-if="request.budget">{{ request.budget }}</span>
            </div>
            <p
                v-if="request.comments"
                class="line-clamp-3 rounded-lg bg-muted/50 px-3 py-2 text-foreground/90"
            >
                “{{ request.comments }}”
            </p>
        </div>

        <ul
            v-if="!compact && (request.consultations.length || request.visit)"
            class="flex flex-col gap-1.5 text-sm"
        >
            <li
                v-for="c in request.consultations"
                :key="c.uuid"
                class="flex flex-wrap items-center gap-x-2 gap-y-1"
            >
                <CalendarClock
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span class="text-foreground">Asesoría · {{ c.channel }}</span>
                <DateText
                    :value="c.starts_at"
                    format="datetime"
                    class="text-muted-foreground"
                />
                <StatusBadge
                    v-if="c.status"
                    :tone="c.status.tone"
                    :label="c.status.label"
                    size="sm"
                    :dot="false"
                />
            </li>
            <li
                v-if="request.visit"
                class="flex flex-wrap items-center gap-x-2 gap-y-1"
            >
                <Ruler
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span class="text-foreground">Visita técnica</span>
                <DateText
                    :value="request.visit.starts_at"
                    format="datetime"
                    class="text-muted-foreground"
                />
                <StatusBadge
                    v-if="request.visit.status"
                    :tone="request.visit.status.tone"
                    :label="request.visit.status.label"
                    size="sm"
                    :dot="false"
                />
            </li>
        </ul>

        <footer
            v-if="request.next || request.project"
            class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t pt-3"
        >
            <p v-if="request.project" class="text-sm text-muted-foreground">
                Proyecto {{ request.project.folio }} ·
                {{ request.project.status }}
            </p>
            <span v-else />
            <template v-if="request.next">
                <a
                    v-if="request.next.external"
                    :href="request.next.href"
                    class="group inline-flex min-h-10 items-center gap-1.5 rounded-md bg-primary px-3.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary-hover focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    {{ request.next.label }}
                    <ArrowUpRight
                        class="size-4 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                        aria-hidden="true"
                    />
                </a>
                <Link
                    v-else
                    :href="request.next.href"
                    class="group inline-flex min-h-10 items-center gap-1.5 rounded-md border bg-card px-3.5 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {{ request.next.label }}
                    <ArrowRight
                        class="size-4 transition group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </template>
        </footer>
    </article>
</template>
