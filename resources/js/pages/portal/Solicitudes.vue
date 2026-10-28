<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ClipboardList, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { EmptyState, PageContainer, PageHeader } from '@/components/ds';
import RequestCard from '@/components/portal/RequestCard.vue';
import type { PortalRequest, RequestStage } from '@/components/portal/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = defineProps<{
    requests: PortalRequest[];
    stages: RequestStage[];
    newRequestUrl: string;
}>();

type Filter = 'active' | 'all' | 'closed';

const counts = computed(() => ({
    active: props.requests.filter((r) => !r.closed).length,
    closed: props.requests.filter((r) => r.closed).length,
    all: props.requests.length,
}));
const filter = ref<Filter>(counts.value.active > 0 ? 'active' : 'all');
const filters: { key: Filter; label: string }[] = [
    { key: 'active', label: 'En curso' },
    { key: 'closed', label: 'Cerradas' },
    { key: 'all', label: 'Todas' },
];
const visible = computed(() =>
    props.requests.filter((r) =>
        filter.value === 'all'
            ? true
            : filter.value === 'active'
              ? !r.closed
              : r.closed,
    ),
);
</script>

<template>
    <Head title="Mis solicitudes" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Mis solicitudes"
            description="Lo que nos pediste y en qué va cada solicitud. Te avisamos de cada avance."
        >
            <template #actions>
                <Button as-child>
                    <a :href="newRequestUrl"
                        ><Plus class="size-4" aria-hidden="true" /> Nueva
                        solicitud</a
                    >
                </Button>
            </template>
        </PageHeader>

        <template v-if="requests.length">
            <div
                role="group"
                aria-label="Filtrar solicitudes"
                class="inline-flex w-full max-w-sm self-start rounded-lg border bg-card p-1 shadow-xs"
            >
                <button
                    v-for="f in filters"
                    :key="f.key"
                    type="button"
                    :aria-pressed="filter === f.key"
                    :class="
                        cn(
                            'flex min-h-9 flex-1 items-center justify-center gap-1.5 rounded-md px-3 text-sm font-medium transition-colors',
                            filter === f.key
                                ? 'bg-primary-soft text-primary'
                                : 'text-muted-foreground hover:text-foreground',
                        )
                    "
                    @click="filter = f.key"
                >
                    {{ f.label }}
                    <span class="text-xs tabular-nums opacity-70">{{
                        counts[f.key]
                    }}</span>
                </button>
            </div>

            <Transition name="portal-fade" mode="out-in">
                <div
                    :key="filter"
                    class="portal-stagger grid gap-4 xl:grid-cols-2"
                    style="--stagger-base: 40ms"
                >
                    <RequestCard
                        v-for="r in visible"
                        :key="r.uuid"
                        :request="r"
                        :stages="stages"
                    />
                    <p
                        v-if="!visible.length"
                        class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground xl:col-span-2"
                    >
                        No hay solicitudes en este filtro.
                    </p>
                </div>
            </Transition>
        </template>

        <EmptyState
            v-else
            :icon="ClipboardList"
            title="Aún no tienes solicitudes"
            description="Cuéntanos qué espacio quieres transformar: cocina, clóset, centro de entretenimiento… Te asesoramos sin compromiso."
        >
            <Button as-child>
                <a :href="newRequestUrl"
                    ><Plus class="size-4" aria-hidden="true" /> Hacer una
                    solicitud</a
                >
            </Button>
        </EmptyState>
    </PageContainer>
</template>
