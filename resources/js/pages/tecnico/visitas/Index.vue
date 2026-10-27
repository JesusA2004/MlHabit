<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarClock, History } from '@lucide/vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import VisitCard from '@/components/projects/technician/VisitCard.vue';
import type { TechnicianVisit } from '@/components/projects/technician/VisitCard.vue';

const props = defineProps<{
    upcoming: TechnicianVisit[];
    past: TechnicianVisit[];
}>();

defineOptions({ layout: { title: 'Visitas' } });
</script>

<template>
    <Head title="Mis visitas" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-5 md:py-8">
        <header>
            <h1 class="font-display text-2xl font-semibold tracking-tight">
                Mis visitas
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Solo ves las visitas que tienes asignadas.
            </p>
        </header>

        <section aria-labelledby="visitas-proximas" class="flex flex-col gap-3">
            <h2
                id="visitas-proximas"
                class="text-sm font-semibold text-muted-foreground"
            >
                Próximas
            </h2>
            <VisitCard
                v-for="visit in props.upcoming"
                :key="visit.uuid"
                :visit="visit"
                show-date
            />
            <EmptyState
                v-if="props.upcoming.length === 0"
                compact
                :icon="CalendarClock"
                title="No tienes visitas próximas"
                description="Operaciones te avisará cuando te asigne una nueva visita."
            />
        </section>

        <section aria-labelledby="visitas-pasadas" class="flex flex-col gap-3">
            <h2
                id="visitas-pasadas"
                class="text-sm font-semibold text-muted-foreground"
            >
                Últimos 30 días
            </h2>
            <VisitCard
                v-for="visit in props.past"
                :key="visit.uuid"
                :visit="visit"
                show-date
            />
            <EmptyState
                v-if="props.past.length === 0"
                compact
                :icon="History"
                title="Sin visitas recientes"
                description="Aquí verás las visitas que realizaste en el último mes."
            />
        </section>
    </div>
</template>
