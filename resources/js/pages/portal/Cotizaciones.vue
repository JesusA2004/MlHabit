<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, ReceiptText } from '@lucide/vue';
import {
    DateText,
    EmptyState,
    Money,
    PageContainer,
    PageHeader,
    StatusBadge,
} from '@/components/ds';
import { show } from '@/routes/portal/cotizaciones';
import type { QuoteSummary } from '@/types/projects';

type Row = QuoteSummary & {
    project: { folio: string; name: string };
    actionable: boolean;
};

defineProps<{ quotes: Row[] }>();
</script>

<template>
    <Head title="Mis cotizaciones" />

    <PageContainer width="narrow" class="flex flex-col gap-6">
        <PageHeader
            title="Cotizaciones"
            description="Revisa el detalle, acepta o cuéntanos qué cambiarías."
        />

        <ul v-if="quotes.length" class="flex flex-col gap-3">
            <li v-for="quote in quotes" :key="quote.uuid">
                <Link
                    :href="show(quote.uuid).url"
                    class="group flex items-center gap-4 rounded-xl border bg-card p-4 shadow-xs transition hover:border-primary/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-foreground">
                                {{ quote.title }}
                            </p>
                            <StatusBadge
                                :tone="
                                    quote.actionable
                                        ? 'info'
                                        : quote.status.tone
                                "
                                :label="
                                    quote.actionable
                                        ? 'Esperando tu respuesta'
                                        : quote.status.client_label
                                "
                                size="sm"
                            />
                        </div>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ quote.project.name }} · {{ quote.folio }} ·
                            versión {{ quote.version }}
                        </p>
                        <p
                            v-if="quote.actionable && quote.valid_until"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Vigente hasta el
                            <DateText
                                :value="quote.valid_until"
                                format="long"
                            />
                        </p>
                    </div>
                    <Money
                        :value="quote.total"
                        :currency="quote.currency"
                        class="shrink-0 text-base font-semibold text-foreground"
                    />
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </li>
        </ul>
        <EmptyState
            v-else
            :icon="ReceiptText"
            title="Todavía no hay cotizaciones"
            description="Cuando tu diseño esté listo te enviaremos la cotización y aparecerá aquí."
        />
    </PageContainer>
</template>
