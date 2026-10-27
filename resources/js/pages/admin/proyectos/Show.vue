<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Mail, Phone, UserRound } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import ContractTab from '@/components/projects/admin/ContractTab.vue';
import DesignTab from '@/components/projects/admin/DesignTab.vue';
import FilesTab from '@/components/projects/admin/FilesTab.vue';
import MeasurementTab from '@/components/projects/admin/MeasurementTab.vue';
import OperationsTab from '@/components/projects/admin/OperationsTab.vue';
import OverviewTab from '@/components/projects/admin/OverviewTab.vue';
import QuotesTab from '@/components/projects/admin/QuotesTab.vue';
import TimelineTab from '@/components/projects/admin/TimelineTab.vue';
import type { ProjectHubProps } from '@/components/projects/admin/types';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import proyectos from '@/routes/admin/proyectos';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<ProjectHubProps>();

defineOptions({
    layout: (p: { project?: { folio?: string; uuid?: string } }) => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Proyectos', href: '/admin/proyectos' },
            {
                title: p.project?.folio ?? 'Proyecto',
                href: `/admin/proyectos/${p.project?.uuid ?? ''}`,
            },
        ] as BreadcrumbItem[],
    }),
});

const TABS = [
    { key: 'resumen', label: 'Resumen' },
    { key: 'cronologia', label: 'Cronología' },
    { key: 'medicion', label: 'Medición' },
    { key: 'diseno', label: 'Diseño' },
    { key: 'cotizaciones', label: 'Cotizaciones' },
    { key: 'contrato', label: 'Contrato y pagos' },
    { key: 'operacion', label: 'Operación' },
    { key: 'archivos', label: 'Archivos' },
] as const;

function initialTab(): string {
    try {
        const tab = new URLSearchParams(window.location.search).get('tab');

        return TABS.some((t) => t.key === tab) ? (tab as string) : 'resumen';
    } catch {
        return 'resumen';
    }
}

const tab = ref(initialTab());

watch(tab, (value) => {
    try {
        const url = new URL(window.location.href);
        if (value === 'resumen') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', value);
        }
        window.history.replaceState(window.history.state, '', url.toString());
    } catch {
        // Sin acceso al historial: la pestaña solo vive en memoria.
    }
});

const badges = computed<Record<string, number>>(() => ({
    diseno: props.summary.pending_technical ? 1 : 0,
    contrato: props.summary.deposit_pending ? 1 : 0,
}));
</script>

<template>
    <Head :title="`${project.folio} · ${project.name}`" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader :title="project.name">
            <template #breadcrumbs>
                <Link
                    :href="proyectos.index().url"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" /> Proyectos
                </Link>
            </template>
            <template #meta>
                <span class="text-xs tabular-nums">{{ project.folio }}</span>
                <StatusBadge
                    :tone="project.status.tone"
                    :label="project.status.label"
                />
                <span v-if="project.category">{{ project.category }}</span>
                <span
                    v-if="project.customer"
                    class="inline-flex items-center gap-1"
                    ><UserRound class="size-3.5" aria-hidden="true" />{{
                        project.customer.name
                    }}</span
                >
            </template>
            <template #actions>
                <a
                    v-if="project.customer.phone"
                    :href="`tel:${project.customer.phone}`"
                    class="inline-flex h-9 items-center gap-2 rounded-md border bg-card px-3 text-sm font-medium hover:bg-muted pointer-coarse:h-11"
                >
                    <Phone class="size-4" aria-hidden="true" /> Llamar
                </a>
                <a
                    v-if="project.customer.email"
                    :href="`mailto:${project.customer.email}`"
                    class="inline-flex h-9 items-center gap-2 rounded-md border bg-card px-3 text-sm font-medium hover:bg-muted pointer-coarse:h-11"
                >
                    <Mail class="size-4" aria-hidden="true" /> Correo
                </a>
            </template>
        </PageHeader>

        <Tabs v-model="tab" class="gap-6">
            <div class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
                <TabsList class="w-max">
                    <TabsTrigger
                        v-for="t in TABS"
                        :key="t.key"
                        :value="t.key"
                        class="gap-1.5 pointer-coarse:h-10"
                    >
                        {{ t.label }}
                        <span
                            v-if="badges[t.key]"
                            class="size-1.5 rounded-full bg-warning"
                            aria-label="Requiere atención"
                        />
                    </TabsTrigger>
                </TabsList>
            </div>

            <TabsContent value="resumen">
                <OverviewTab v-bind="props" @go="tab = $event" />
            </TabsContent>
            <TabsContent value="cronologia">
                <TimelineTab :project="project" :events="events" :can="can" />
            </TabsContent>
            <TabsContent value="medicion">
                <MeasurementTab :measurements="measurements" :visits="visits" />
            </TabsContent>
            <TabsContent value="diseno">
                <DesignTab
                    :project="project"
                    :designs="designs"
                    :measurements="measurements"
                    :can="can"
                />
            </TabsContent>
            <TabsContent value="cotizaciones">
                <QuotesTab :project="project" :quotes="quotes" :can="can" />
            </TabsContent>
            <TabsContent value="contrato">
                <ContractTab
                    :project="project"
                    :contract="contract"
                    :quotes="quotes"
                    :payment-methods="paymentMethods"
                    :can="can"
                />
            </TabsContent>
            <TabsContent value="operacion">
                <OperationsTab :operations="operations" :can="can" />
            </TabsContent>
            <TabsContent value="archivos">
                <FilesTab :project="project" :files="files" :can="can" />
            </TabsContent>
        </Tabs>
    </PageContainer>
</template>
