<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays, History, Ruler, Video } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    DateText,
    EmptyState,
    PageContainer,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import AppointmentCard from '@/components/portal/AppointmentCard.vue';
import type { PortalAppointment } from '@/components/portal/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = defineProps<{
    upcoming: PortalAppointment[];
    past: PortalAppointment[];
}>();

type Kind = 'all' | 'consultation' | 'visit';
const kind = ref<Kind>('all');
const kinds: { key: Kind; label: string }[] = [
    { key: 'all', label: 'Todas' },
    { key: 'consultation', label: 'Asesorías' },
    { key: 'visit', label: 'Visitas' },
];
const byKind = (list: PortalAppointment[]) =>
    kind.value === 'all' ? list : list.filter((a) => a.kind === kind.value);
const upcomingList = computed(() => byKind(props.upcoming));
const pastList = computed(() => byKind(props.past));
const showAllPast = ref(false);
const pastVisible = computed(() =>
    showAllPast.value ? pastList.value : pastList.value.slice(0, 8),
);
</script>

<template>
    <Head title="Mis citas" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Mis citas"
            description="Tus asesorías (videollamada, llamada o WhatsApp) y visitas técnicas a domicilio."
        />

        <div
            v-if="upcoming.length || past.length"
            role="group"
            aria-label="Filtrar citas"
            class="inline-flex w-full max-w-sm self-start rounded-lg border bg-card p-1 shadow-xs"
        >
            <button
                v-for="k in kinds"
                :key="k.key"
                type="button"
                :aria-pressed="kind === k.key"
                :class="
                    cn(
                        'flex min-h-9 flex-1 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors',
                        kind === k.key
                            ? 'bg-primary-soft text-primary'
                            : 'text-muted-foreground hover:text-foreground',
                    )
                "
                @click="kind = k.key"
            >
                {{ k.label }}
            </button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <section
                aria-labelledby="proximas"
                class="flex min-w-0 flex-col gap-3"
            >
                <h2
                    id="proximas"
                    class="text-base font-semibold text-foreground"
                >
                    Próximas
                    <span
                        v-if="upcomingList.length"
                        class="ml-1 text-sm font-normal text-muted-foreground tabular-nums"
                        >({{ upcomingList.length }})</span
                    >
                </h2>
                <Transition name="portal-fade" mode="out-in">
                    <div
                        v-if="upcomingList.length"
                        :key="kind"
                        class="portal-stagger flex flex-col gap-3"
                        style="--stagger-base: 40ms"
                    >
                        <AppointmentCard
                            v-for="(a, i) in upcomingList"
                            :key="a.key"
                            :appointment="a"
                            :featured="i === 0"
                        />
                    </div>
                    <EmptyState
                        v-else
                        :key="`empty-${kind}`"
                        :icon="CalendarDays"
                        title="No tienes citas próximas"
                        description="Cuando agendes una asesoría o una visita técnica, aparecerá aquí con su enlace, dirección y recordatorio."
                    >
                        <Button as-child variant="outline">
                            <a href="/portal/solicitudes"
                                >Ver mis solicitudes</a
                            >
                        </Button>
                    </EmptyState>
                </Transition>
            </section>

            <aside class="flex flex-col gap-6">
                <SectionCard
                    title="Así funcionan tus citas"
                    :icon="CalendarDays"
                >
                    <ul class="flex flex-col gap-3 text-sm">
                        <li class="flex gap-3">
                            <span
                                class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary"
                                aria-hidden="true"
                                ><Video class="size-4"
                            /></span>
                            <p class="text-muted-foreground">
                                <span class="font-medium text-foreground"
                                    >Asesoría.</span
                                >
                                Entra con el botón “Entrar a la videollamada”
                                unos minutos antes. Puedes cambiarla o
                                cancelarla desde la cita.
                            </p>
                        </li>
                        <li class="flex gap-3">
                            <span
                                class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary"
                                aria-hidden="true"
                                ><Ruler class="size-4"
                            /></span>
                            <p class="text-muted-foreground">
                                <span class="font-medium text-foreground"
                                    >Visita técnica.</span
                                >
                                Un técnico mide tu espacio en tu domicilio. Si
                                necesitas moverla, escríbenos desde Ayuda.
                            </p>
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard
                    v-if="pastList.length"
                    title="Anteriores"
                    :icon="History"
                    flush
                >
                    <ul class="divide-y">
                        <li
                            v-for="item in pastVisible"
                            :key="item.key"
                            class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5"
                        >
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-medium text-foreground"
                                >
                                    {{ item.title }}
                                </p>
                                <DateText
                                    :value="item.starts_at"
                                    format="datetime"
                                    class="text-xs text-muted-foreground"
                                />
                            </div>
                            <StatusBadge
                                v-if="item.status"
                                :tone="item.status.tone"
                                :label="item.status.label"
                                size="sm"
                                class="shrink-0"
                            />
                        </li>
                    </ul>
                    <template v-if="pastList.length > 8" #footer>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="showAllPast = !showAllPast"
                        >
                            {{
                                showAllPast
                                    ? 'Ver menos'
                                    : `Ver las ${pastList.length}`
                            }}
                        </Button>
                    </template>
                </SectionCard>
            </aside>
        </div>
    </PageContainer>
</template>
