<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardCheck, Hourglass, ShieldAlert, ThumbsUp } from '@lucide/vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import calidad from '@/routes/admin/calidad';
import instalaciones from '@/routes/admin/instalaciones';
import type { EnumOption, InstallationRow } from '@/types/operations';

type QueueRow = InstallationRow & {
    openIncidents: number;
    clientSignedAt: string | null;
    waitingSince: string | null;
};

defineProps<{
    queue: QueueRow[];
    awaitingClient: InstallationRow[];
    recent: {
        id: number;
        orderId: number;
        folio: string;
        project: string;
        result: EnumOption;
        inspector: string | null;
        inspectedAt: string | null;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Calidad', href: '/admin/calidad' },
        ],
    },
});
</script>

<template>
    <Head title="Calidad" />

    <PageContainer>
        <PageHeader
            title="Calidad"
            description="Revisa la evidencia de cada módulo contra el checklist y dictamina. La más antigua va primero."
        />

        <section aria-labelledby="queue-title" class="flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-3">
                <h2
                    id="queue-title"
                    class="font-display text-lg font-semibold tracking-tight"
                >
                    Pendientes de revisión de calidad
                </h2>
                <span class="text-sm text-muted-foreground tabular-nums">{{
                    queue.length
                }}</span>
            </div>

            <EmptyState
                v-if="queue.length === 0"
                :icon="ThumbsUp"
                title="No hay instalaciones esperando revisión"
                description="Cuando el cliente responda a la evidencia de una instalación, aparecerá aquí."
            />

            <ol
                v-else
                class="admin-stagger grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            >
                <li v-for="row in queue" :key="row.id">
                    <Link
                        :href="calidad.show(row.id).url"
                        class="group flex h-full hover-lift flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs hover:border-primary/40 focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="truncate font-medium group-hover:text-primary"
                                >
                                    {{ row.project.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ row.folio }} · {{ row.project.folio }}
                                </p>
                            </div>
                            <StatusBadge
                                v-if="row.clientConformity"
                                size="sm"
                                :tone="row.clientConformity.tone"
                                :label="row.clientConformity.label"
                            />
                            <StatusBadge
                                v-else
                                size="sm"
                                tone="neutral"
                                label="Sin respuesta del cliente"
                            />
                        </div>
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Esperando desde
                                </dt>
                                <dd class="flex items-center gap-1.5">
                                    <Hourglass
                                        class="size-3.5 text-muted-foreground"
                                        aria-hidden="true"
                                    /><DateText
                                        :value="row.waitingSince"
                                        relative
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Instalador
                                </dt>
                                <dd class="truncate">
                                    {{ row.leadInstaller?.name ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Partidas
                                </dt>
                                <dd class="tabular-nums">
                                    {{ row.itemsCount ?? 0 }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Incidencias abiertas
                                </dt>
                                <dd
                                    :class="
                                        row.openIncidents > 0
                                            ? 'flex items-center gap-1 font-medium text-danger-ink'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    <ShieldAlert
                                        v-if="row.openIncidents > 0"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />{{ row.openIncidents }}
                                </dd>
                            </div>
                        </dl>
                        <span
                            class="mt-auto inline-flex items-center gap-1.5 text-sm font-medium text-primary"
                        >
                            <ClipboardCheck class="size-4" aria-hidden="true" />
                            Revisar
                        </span>
                    </Link>
                </li>
            </ol>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <SectionCard
                title="Esperando al cliente"
                description="La evidencia ya está en su portal; aún no responde."
                flush
            >
                <p
                    v-if="awaitingClient.length === 0"
                    class="px-4 py-5 text-sm text-muted-foreground sm:px-6"
                >
                    Ningún cliente tiene evidencia pendiente de revisar.
                </p>
                <ul v-else class="divide-y">
                    <li v-for="row in awaitingClient" :key="row.id">
                        <Link
                            :href="instalaciones.show(row.id).url"
                            class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-muted/50 sm:px-6"
                        >
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-sm font-medium"
                                    >{{ row.project.name }}</span
                                >
                                <span class="text-xs text-muted-foreground"
                                    >{{ row.folio
                                    }}<template v-if="row.project.customer">
                                        · {{ row.project.customer }}</template
                                    ></span
                                >
                            </span>
                            <span class="shrink-0 text-xs text-muted-foreground"
                                >Enviada
                                <DateText
                                    :value="row.evidenceSubmittedAt"
                                    relative
                            /></span>
                        </Link>
                    </li>
                </ul>
            </SectionCard>

            <SectionCard title="Decisiones recientes" flush>
                <p
                    v-if="recent.length === 0"
                    class="px-4 py-5 text-sm text-muted-foreground sm:px-6"
                >
                    Aún no hay dictámenes de calidad.
                </p>
                <ul v-else class="divide-y">
                    <li v-for="row in recent" :key="row.id">
                        <Link
                            :href="instalaciones.show(row.orderId).url"
                            class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-muted/50 sm:px-6"
                        >
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-sm font-medium"
                                    >{{ row.project }}</span
                                >
                                <span class="text-xs text-muted-foreground"
                                    >{{ row.folio }} ·
                                    {{ row.inspector ?? '—' }} ·
                                    <DateText :value="row.inspectedAt" relative
                                /></span>
                            </span>
                            <StatusBadge
                                size="sm"
                                :tone="row.result.tone"
                                :label="row.result.label"
                            />
                        </Link>
                    </li>
                </ul>
            </SectionCard>
        </div>
    </PageContainer>
</template>
