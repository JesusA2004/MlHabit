<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Clock, ReceiptText } from '@lucide/vue';
import { computed } from 'vue';
import {
    DateText,
    EmptyState,
    Money,
    PageContainer,
    PageHeader,
    StatusBadge,
} from '@/components/ds';
import { cn } from '@/lib/utils';
import { show } from '@/routes/portal/cotizaciones';
import type { QuoteSummary } from '@/types/projects';

type Row = QuoteSummary & {
    project: { folio: string; name: string };
    actionable: boolean;
};

const props = defineProps<{ quotes: Row[] }>();

const pending = computed(() => props.quotes.filter((q) => q.actionable));
const others = computed(() => props.quotes.filter((q) => !q.actionable));

/** Días de vigencia restantes (null si no aplica). */
function daysLeft(q: Row): number | null {
    if (!q.valid_until) {
        return null;
    }

    const end = new Date(`${q.valid_until}T23:59:59`).getTime();

    return Math.max(0, Math.ceil((end - Date.now()) / 86_400_000));
}
</script>

<template>
    <Head title="Mis cotizaciones" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Cotizaciones"
            description="Revisa el detalle de cada propuesta, acéptala o cuéntanos qué cambiarías."
        />

        <section
            v-if="pending.length"
            aria-labelledby="pendientes"
            class="flex flex-col gap-3"
        >
            <h2 id="pendientes" class="text-base font-semibold text-foreground">
                Esperan tu respuesta
            </h2>
            <ul
                class="portal-stagger grid gap-4 lg:grid-cols-2"
                style="--stagger-base: 60ms"
            >
                <li v-for="quote in pending" :key="quote.uuid">
                    <Link
                        :href="show(quote.uuid).url"
                        class="group portal-lift relative flex h-full flex-col gap-5 overflow-hidden rounded-2xl border border-primary/25 bg-card p-5 shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:p-6"
                    >
                        <span
                            class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-oak to-primary/40"
                            aria-hidden="true"
                        />
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ quote.folio }} · versión
                                    {{ quote.version }}
                                </p>
                                <h3
                                    class="mt-1 font-display text-lg leading-snug font-semibold tracking-tight text-foreground"
                                >
                                    {{ quote.title }}
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ quote.project.name }}
                                </p>
                            </div>
                            <StatusBadge
                                tone="info"
                                label="Por revisar"
                                size="sm"
                            />
                        </div>
                        <div
                            class="mt-auto flex flex-wrap items-end justify-between gap-3"
                        >
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Total
                                </p>
                                <Money
                                    :value="quote.total"
                                    :currency="quote.currency"
                                    class="font-display text-2xl font-semibold tracking-tight text-foreground"
                                />
                                <p
                                    v-if="daysLeft(quote) !== null"
                                    :class="
                                        cn(
                                            'mt-1 flex items-center gap-1 text-xs',
                                            (daysLeft(quote) ?? 99) <= 5
                                                ? 'text-warning-ink'
                                                : 'text-muted-foreground',
                                        )
                                    "
                                >
                                    <Clock
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Vigente hasta el
                                    <DateText
                                        :value="quote.valid_until"
                                        format="long"
                                    />
                                    ({{ daysLeft(quote) }}
                                    {{
                                        daysLeft(quote) === 1 ? 'día' : 'días'
                                    }})
                                </p>
                            </div>
                            <span
                                class="inline-flex h-10 items-center gap-1.5 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors group-hover:bg-primary-hover"
                            >
                                Revisar
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

        <section
            v-if="others.length"
            aria-labelledby="historial"
            class="flex flex-col gap-3"
        >
            <h2 id="historial" class="text-base font-semibold text-foreground">
                {{ pending.length ? 'Anteriores' : 'Tus cotizaciones' }}
            </h2>
            <ul
                class="portal-stagger flex flex-col gap-2"
                style="--stagger-base: 120ms"
            >
                <li v-for="quote in others" :key="quote.uuid">
                    <Link
                        :href="show(quote.uuid).url"
                        class="group portal-lift flex items-center gap-4 rounded-xl border bg-card p-4 shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span
                            class="hidden size-10 shrink-0 place-items-center rounded-full bg-muted text-muted-foreground sm:grid"
                            aria-hidden="true"
                            ><ReceiptText class="size-[18px]"
                        /></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-foreground">
                                    {{ quote.title }}
                                </p>
                                <StatusBadge
                                    :tone="quote.status.tone"
                                    :label="quote.status.client_label"
                                    size="sm"
                                />
                            </div>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                {{ quote.project.name }} · {{ quote.folio }} ·
                                versión {{ quote.version }}
                            </p>
                        </div>
                        <Money
                            :value="quote.total"
                            :currency="quote.currency"
                            class="shrink-0 font-semibold text-foreground"
                        />
                        <ArrowRight
                            class="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </Link>
                </li>
            </ul>
        </section>

        <EmptyState
            v-if="!quotes.length"
            :icon="ReceiptText"
            title="Todavía no hay cotizaciones"
            description="Cuando tu diseño esté listo te enviaremos la cotización y aparecerá aquí para que la revises y la aceptes."
        />
    </PageContainer>
</template>
