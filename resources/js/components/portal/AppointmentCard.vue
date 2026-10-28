<script setup lang="ts">
import {
    CalendarPlus,
    CreditCard,
    MapPin,
    MessageCircle,
    Phone,
    Ruler,
    UserRound,
    Video,
} from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { StatusBadge } from '@/components/ds';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    countdown,
    downloadIcs,
    googleCalendarUrl,
} from '@/components/portal/calendar';
import type { CalendarEvent } from '@/components/portal/calendar';
import type { PortalAppointment } from '@/components/portal/types';
import { cn } from '@/lib/utils';

/**
 * Cita próxima: "tarjeta de calendario" con día grande, cuenta regresiva,
 * entrar a la videollamada, pagar, cambiar fecha y agregar al calendario.
 */
const props = withDefaults(
    defineProps<{
        appointment: PortalAppointment;
        featured?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { featured: false },
);

const a = computed(() => props.appointment);
const start = computed(() =>
    a.value.starts_at ? new Date(a.value.starts_at) : null,
);
const day = computed(() =>
    start.value
        ? new Intl.DateTimeFormat('es-MX', { day: 'numeric' }).format(
              start.value,
          )
        : '—',
);
const month = computed(() =>
    start.value
        ? new Intl.DateTimeFormat('es-MX', { month: 'short' })
              .format(start.value)
              .replace('.', '')
        : '',
);
const weekday = computed(() =>
    start.value
        ? new Intl.DateTimeFormat('es-MX', {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
          }).format(start.value)
        : 'Fecha por confirmar',
);
const time = computed(() => {
    if (!start.value) {
        return null;
    }

    const fmt = new Intl.DateTimeFormat('es-MX', {
        hour: 'numeric',
        minute: '2-digit',
    });
    const end = a.value.ends_at ? new Date(a.value.ends_at) : null;

    return end
        ? `${fmt.format(start.value)} – ${fmt.format(end)}`
        : fmt.format(start.value);
});
const inSoon = computed(() => countdown(a.value.starts_at));
const live = computed(() => {
    if (!a.value.starts_at || !a.value.ends_at) {
        return false;
    }

    const now = Date.now();

    return (
        now >= new Date(a.value.starts_at).getTime() - 10 * 60000 &&
        now <= new Date(a.value.ends_at).getTime()
    );
});

const channelIcon = computed(() => {
    if (a.value.kind === 'visit') {
        return Ruler;
    }

    if (a.value.is_video) {
        return Video;
    }

    return a.value.channel?.toLowerCase().includes('whatsapp')
        ? MessageCircle
        : Phone;
});

const calendarEvent = computed<CalendarEvent | null>(() =>
    a.value.starts_at
        ? {
              uid: a.value.key,
              title: `${a.value.title} · ML Habitat`,
              start: a.value.starts_at,
              end: a.value.ends_at,
              description: [
                  a.value.with ? `Con ${a.value.with}` : null,
                  a.value.channel,
              ]
                  .filter(Boolean)
                  .join(' · '),
              location: a.value.location,
              url: a.value.meeting_url,
          }
        : null,
);
</script>

<template>
    <article
        :class="
            cn(
                'portal-lift flex gap-4 rounded-xl border bg-card p-4 shadow-xs sm:p-5',
                featured && 'border-primary/25',
                props.class,
            )
        "
    >
        <!-- Hoja de calendario -->
        <div
            class="flex w-16 shrink-0 flex-col overflow-hidden rounded-lg border text-center shadow-xs sm:w-[4.5rem]"
            aria-hidden="true"
        >
            <span
                class="bg-primary py-1 text-[11px] font-semibold text-primary-foreground uppercase"
                >{{ month }}</span
            >
            <span
                class="grid flex-1 place-items-center py-1.5 font-display text-2xl font-semibold tracking-tight text-foreground tabular-nums"
                >{{ day }}</span
            >
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <component
                    :is="channelIcon"
                    class="size-4 text-primary"
                    aria-hidden="true"
                />
                <h3 class="font-medium text-foreground">{{ a.title }}</h3>
                <StatusBadge
                    v-if="a.status"
                    :tone="a.status.tone"
                    :label="a.status.label"
                    size="sm"
                />
                <span
                    v-if="live && a.meeting_url"
                    class="inline-flex items-center gap-1.5 rounded-full bg-danger-soft px-2 py-0.5 text-xs font-medium text-danger-ink"
                >
                    <span
                        class="portal-live-dot relative size-1.5 rounded-full bg-danger text-danger"
                        aria-hidden="true"
                    />
                    En curso
                </span>
            </div>
            <p class="mt-1 text-sm text-foreground first-letter:uppercase">
                {{ weekday }}
                <template v-if="time"> · {{ time }}</template>
            </p>
            <p v-if="inSoon" class="text-xs font-medium text-primary">
                {{ inSoon.charAt(0).toUpperCase() + inSoon.slice(1) }}
            </p>
            <div class="mt-2 flex flex-col gap-1 text-sm text-muted-foreground">
                <p v-if="a.with" class="flex items-center gap-1.5">
                    <UserRound class="size-3.5 shrink-0" aria-hidden="true" />
                    {{ a.with
                    }}<template v-if="a.channel"> · {{ a.channel }}</template>
                </p>
                <p v-if="a.location" class="flex items-start gap-1.5">
                    <MapPin
                        class="mt-0.5 size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="min-w-0">{{ a.location }}</span>
                </p>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <Button v-if="a.pay_url" as-child size="sm">
                    <a :href="a.pay_url"
                        ><CreditCard class="size-4" aria-hidden="true" /> Pagar
                        para confirmar</a
                    >
                </Button>
                <Button v-if="a.meeting_url" as-child size="sm">
                    <a
                        :href="a.meeting_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        ><Video class="size-4" aria-hidden="true" /> Entrar a la
                        videollamada</a
                    >
                </Button>
                <Button
                    v-if="a.reschedule_url"
                    as-child
                    size="sm"
                    variant="outline"
                >
                    <a :href="a.reschedule_url">Cambiar o cancelar</a>
                </Button>
                <DropdownMenu v-if="calendarEvent && !a.pay_url">
                    <DropdownMenuTrigger as-child>
                        <Button size="sm" variant="ghost">
                            <CalendarPlus class="size-4" aria-hidden="true" />
                            Agregar al calendario
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem as-child>
                            <a
                                :href="googleCalendarUrl(calendarEvent)"
                                target="_blank"
                                rel="noopener noreferrer"
                                >Google Calendar</a
                            >
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="downloadIcs(calendarEvent)">
                            Apple, Outlook u otro (.ics)
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </article>
</template>
