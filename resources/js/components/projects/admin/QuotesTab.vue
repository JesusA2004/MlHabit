<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, Printer, ReceiptText } from '@lucide/vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import Money from '@/components/ds/Money.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import cotizaciones from '@/routes/admin/cotizaciones';
import type { QuoteSummary } from '@/types/projects';
import type { HubCan, HubProject } from './types';

defineProps<{ project: HubProject; quotes: QuoteSummary[]; can: HubCan }>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <EmptyState
            v-if="!quotes.length"
            :icon="ReceiptText"
            title="Aún no hay cotizaciones"
            description="Arma la cotización con partidas; los totales e IVA se calculan en el servidor con la configuración comercial vigente."
        >
            <Button v-if="can.quotes_manage" as-child>
                <Link :href="cotizaciones.create(project.uuid).url"
                    ><Plus class="size-4" aria-hidden="true" /> Nueva
                    cotización</Link
                >
            </Button>
        </EmptyState>

        <SectionCard
            v-else
            title="Cotizaciones"
            description="Cada edición de una cotización enviada crea una nueva versión; las anteriores se conservan."
            flush
        >
            <template #actions>
                <Button v-if="can.quotes_manage" size="sm" as-child>
                    <Link :href="cotizaciones.create(project.uuid).url"
                        ><Plus class="size-4" aria-hidden="true" /> Nueva
                        cotización</Link
                    >
                </Button>
            </template>
            <ul class="flex flex-col divide-y">
                <li
                    v-for="q in quotes"
                    :key="q.uuid"
                    class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <Link
                            :href="cotizaciones.edit(q.uuid).url"
                            class="font-medium hover:underline"
                        >
                            v{{ q.version }} · {{ q.title }}
                        </Link>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            <span class="tabular-nums">{{ q.folio }}</span>
                            <template v-if="q.design_number">
                                · Diseño v{{ q.design_number }}</template
                            >
                            <template v-if="q.sent_at">
                                · enviada <DateText :value="q.sent_at"
                            /></template>
                            <template
                                v-if="
                                    q.valid_until && q.status.value === 'sent'
                                "
                            >
                                · vigente hasta
                                <DateText :value="q.valid_until"
                            /></template>
                        </p>
                        <p
                            v-if="q.rejection_reason"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Motivo: {{ q.rejection_reason }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Money
                            :value="q.total"
                            class="font-semibold tabular-nums"
                        />
                        <StatusBadge
                            :tone="q.status.tone"
                            :label="q.status.label"
                            size="sm"
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            as-child
                            :aria-label="`Imprimir ${q.folio}`"
                        >
                            <a
                                :href="`/cotizaciones/${q.uuid}/imprimir`"
                                target="_blank"
                                rel="noopener"
                                ><Printer class="size-4" aria-hidden="true"
                            /></a>
                        </Button>
                    </div>
                </li>
            </ul>
        </SectionCard>
    </div>
</template>
