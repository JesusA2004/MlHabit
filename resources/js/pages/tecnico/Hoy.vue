<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarCheck, ChevronRight, MapPin, Wrench } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import VisitCard from '@/components/projects/technician/VisitCard.vue';
import TimeBlock from '@/components/projects/technician/TimeBlock.vue';
import type { TechnicianVisit } from '@/components/projects/technician/VisitCard.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import visitas from '@/routes/tecnico/visitas';
import type { AddressInfo, EnumValue } from '@/types/projects';

type InstallationCard = {
    id: number;
    folio: string;
    status: EnumValue | null;
    scheduled_start: string | null;
    scheduled_end: string | null;
    project: string | null;
    customer_first_name: string | null;
    address: AddressInfo | null;
    url: string;
};

const props = defineProps<{
    today: string;
    firstName: string;
    visits: TechnicianVisit[];
    installations: InstallationCard[];
}>();

defineOptions({ layout: { title: 'Hoy' } });

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Buenos días';
    }

    return hour < 19 ? 'Buenas tardes' : 'Buenas noches';
});

const dateLabel = computed(
    () => formatDate(`${props.today}T12:00:00`, 'full') ?? '',
);
const total = computed(() => props.visits.length + props.installations.length);
</script>

<template>
    <Head title="Hoy" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-5 md:py-8">
        <header>
            <p class="text-sm text-muted-foreground first-letter:uppercase">
                {{ dateLabel }}
            </p>
            <h1 class="mt-1 font-display text-2xl font-semibold tracking-tight">
                {{ greeting }}, {{ props.firstName }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                <template v-if="total === 0"
                    >No tienes trabajos agendados para hoy.</template
                >
                <template v-else-if="total === 1"
                    >Tienes 1 trabajo agendado hoy.</template
                >
                <template v-else
                    >Tienes {{ total }} trabajos agendados hoy.</template
                >
            </p>
        </header>

        <section aria-labelledby="hoy-visitas" class="flex flex-col gap-3">
            <h2
                id="hoy-visitas"
                class="text-sm font-semibold text-muted-foreground"
            >
                Visitas de medición
            </h2>
            <VisitCard
                v-for="visit in props.visits"
                :key="visit.uuid"
                :visit="visit"
            />
            <EmptyState
                v-if="props.visits.length === 0"
                compact
                :icon="CalendarCheck"
                title="Sin visitas hoy"
                description="Cuando te asignen una visita aparecerá aquí con la dirección y los datos del cliente."
            >
                <Button variant="outline" as-child class="h-11">
                    <Link :href="visitas.index().url"
                        >Ver próximas visitas</Link
                    >
                </Button>
            </EmptyState>
        </section>

        <section
            v-if="props.installations.length"
            aria-labelledby="hoy-instalaciones"
            class="flex flex-col gap-3"
        >
            <h2
                id="hoy-instalaciones"
                class="text-sm font-semibold text-muted-foreground"
            >
                Instalaciones
            </h2>
            <Link
                v-for="item in props.installations"
                :key="item.id"
                :href="item.url"
                class="group flex items-stretch gap-4 rounded-xl border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <TimeBlock
                    :at="item.scheduled_start"
                    tone="oak"
                    :icon="Wrench"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <p class="line-clamp-2 text-base font-semibold">
                            {{ item.project ?? item.folio }}
                        </p>
                        <StatusBadge
                            v-if="item.status"
                            :label="item.status.label"
                            :tone="item.status.tone"
                            size="sm"
                        />
                    </div>
                    <p
                        v-if="item.customer_first_name"
                        class="text-sm text-muted-foreground"
                    >
                        {{ item.customer_first_name }}
                    </p>
                    <p
                        v-if="item.address"
                        class="mt-1 flex items-start gap-1.5 text-sm text-muted-foreground"
                    >
                        <MapPin
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span class="line-clamp-2">{{
                            item.address.one_line
                        }}</span>
                    </p>
                </div>
                <ChevronRight
                    class="size-5 shrink-0 self-center text-muted-foreground"
                    aria-hidden="true"
                />
            </Link>
        </section>
    </div>
</template>
