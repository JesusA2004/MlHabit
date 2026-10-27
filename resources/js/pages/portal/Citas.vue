<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Ruler, UserRound, Video } from '@lucide/vue';
import {
    DateText,
    EmptyState,
    PageContainer,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import type { Appointment } from '@/types/projects';

defineProps<{ upcoming: Appointment[]; past: Appointment[] }>();
</script>

<template>
    <Head title="Mis citas" />

    <PageContainer width="narrow" class="flex flex-col gap-6">
        <PageHeader
            title="Mis citas"
            description="Tus asesorías y visitas técnicas en un solo lugar."
        />

        <SectionCard title="Próximas" flush>
            <ul v-if="upcoming.length" class="divide-y">
                <li
                    v-for="item in upcoming"
                    :key="item.key"
                    class="flex gap-4 px-4 py-4 sm:px-5"
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-soft text-primary"
                        aria-hidden="true"
                    >
                        <Ruler v-if="item.kind === 'visit'" class="size-5" />
                        <Video v-else class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="font-medium text-foreground">
                                {{ item.title }}
                            </p>
                            <StatusBadge
                                v-if="item.status"
                                :tone="item.status.tone"
                                :label="item.status.label"
                                size="sm"
                            />
                        </div>
                        <DateText
                            :value="item.starts_at"
                            format="full"
                            class="mt-0.5 block text-sm text-foreground"
                        />
                        <DateText
                            :value="item.starts_at"
                            format="time"
                            class="text-sm text-muted-foreground"
                        />
                        <p
                            v-if="item.with"
                            class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground"
                        >
                            <UserRound class="size-3.5" aria-hidden="true" />
                            {{ item.with
                            }}<template v-if="item.channel">
                                · {{ item.channel }}</template
                            >
                        </p>
                        <p
                            v-if="item.location"
                            class="mt-1 flex items-start gap-1.5 text-sm text-muted-foreground"
                        >
                            <MapPin
                                class="mt-0.5 size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {{ item.location }}
                        </p>
                        <div
                            v-if="item.meeting_url || item.reschedule_url"
                            class="mt-3 flex flex-wrap gap-2"
                        >
                            <Button v-if="item.meeting_url" as-child size="sm">
                                <a
                                    :href="item.meeting_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >Entrar a la videollamada</a
                                >
                            </Button>
                            <Button
                                v-if="item.reschedule_url"
                                as-child
                                size="sm"
                                variant="outline"
                            >
                                <a :href="item.reschedule_url">Cambiar fecha</a>
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>
            <EmptyState
                v-else
                compact
                :bordered="false"
                :icon="CalendarDays"
                title="No tienes citas próximas"
                description="Cuando agendemos una asesoría o visita contigo, la verás aquí con todos los detalles."
            />
        </SectionCard>

        <SectionCard v-if="past.length" title="Anteriores" flush>
            <ul class="divide-y">
                <li
                    v-for="item in past"
                    :key="item.key"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-5"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-foreground">
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
                    />
                </li>
            </ul>
        </SectionCard>
    </PageContainer>
</template>
