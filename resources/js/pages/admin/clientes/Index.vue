<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
import { ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import type { DataColumn } from '@/components/ds/types';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { LengthAwarePaginator } from '@/types/ui';

type CustomerRow = {
    uuid: string;
    name: string;
    email: string | null;
    phone: string | null;
    hasPortal: boolean;
    leadsCount: number;
    projectsCount: number;
    lastLead: {
        folio: string;
        status: { value: string; label: string; tone?: string };
    } | null;
    createdAt: string | null;
};

const props = defineProps<{
    customers: LengthAwarePaginator<CustomerRow>;
    filters: { tipo: string; q: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Clientes', href: '/admin/clientes' },
        ],
    },
});

const search = ref(props.filters.q ?? '');
const tipo = ref(props.filters.tipo ?? 'todos');
const loading = ref(false);

watch([search, tipo], () => {
    router.get(
        '/admin/clientes',
        {
            q: search.value || undefined,
            tipo: tipo.value === 'todos' ? undefined : tipo.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
});

const tabs = [
    { value: 'todos', label: 'Todos' },
    { value: 'con_proyecto', label: 'Con proyecto' },
    { value: 'portal', label: 'Con acceso al portal' },
];

const columns: DataColumn<CustomerRow>[] = [
    { key: 'name', label: 'Cliente', primary: true },
    { key: 'phone', label: 'Teléfono' },
    { key: 'lastLead', label: 'Última solicitud' },
    { key: 'counts', label: 'Solicitudes / proyectos', hideOnMobile: true },
    { key: 'createdAt', label: 'Alta', hideOnMobile: true },
];

const showUrl = (uuid: string) => `/admin/clientes/${uuid}`;
</script>

<template>
    <Head title="Clientes" />

    <PageContainer>
        <PageHeader
            title="Clientes"
            description="Personas que han pedido información o ya tienen un proyecto con ML Habitat."
        />

        <nav
            aria-label="Tipo de cliente"
            class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
        >
            <ul class="flex w-max gap-1.5">
                <li v-for="tab in tabs" :key="tab.value">
                    <button
                        type="button"
                        :aria-pressed="tipo === tab.value"
                        :class="
                            cn(
                                'flex h-9 items-center rounded-full border px-3.5 text-sm transition-colors',
                                tipo === tab.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'bg-card hover:bg-muted',
                            )
                        "
                        @click="tipo = tab.value"
                    >
                        {{ tab.label }}
                    </button>
                </li>
            </ul>
        </nav>

        <FilterBar
            v-model:search="search"
            placeholder="Nombre, correo o teléfono"
        />

        <DataView
            :columns="columns"
            :paginator="customers"
            :loading="loading"
            row-key="uuid"
            :row-href="(row) => showUrl(row.uuid)"
            item-label="clientes"
            caption="Clientes"
        >
            <template #cell-name="{ row }">
                <div class="min-w-0">
                    <Link
                        :href="showUrl(row.uuid)"
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.name }}</Link
                    >
                    <p class="truncate text-xs text-muted-foreground">
                        {{ row.email ?? 'Sin correo' }}
                    </p>
                </div>
                <Badge v-if="row.hasPortal" variant="secondary" class="mt-1"
                    >Portal</Badge
                >
            </template>
            <template #cell-phone="{ row }">
                <span class="tabular-nums">{{ row.phone ?? '—' }}</span>
            </template>
            <template #cell-lastLead="{ row }">
                <span
                    v-if="row.lastLead"
                    class="inline-flex items-center gap-2"
                >
                    <span class="text-xs text-muted-foreground tabular-nums">{{
                        row.lastLead.folio
                    }}</span>
                    <StatusBadge
                        size="sm"
                        :tone="(row.lastLead.status.tone ?? 'neutral') as Tone"
                        :label="row.lastLead.status.label"
                    />
                </span>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-counts="{ row }">
                <span class="tabular-nums"
                    >{{ row.leadsCount }} / {{ row.projectsCount }}</span
                >
            </template>
            <template #cell-createdAt="{ row }">
                <DateText :value="row.createdAt" />
            </template>
            <template #empty>
                <EmptyState
                    v-if="search || tipo !== 'todos'"
                    :icon="Users"
                    title="Ningún cliente coincide"
                    description="Revisa la búsqueda o cambia de pestaña."
                />
                <EmptyState
                    v-else
                    :icon="Users"
                    title="Aún no hay clientes"
                    description="Cada persona que envíe una solicitud o registres como prospecto aparecerá aquí."
                />
            </template>
        </DataView>
    </PageContainer>
</template>
