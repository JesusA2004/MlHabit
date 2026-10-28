<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarX,
    Clock,
    ExternalLink,
    Loader2,
    MessageCircle,
    Phone,
    UserRound,
    Video,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConsultationSlotPicker from '@/components/crm/ConsultationSlotPicker.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { TIME_ZONE } from '@/lib/format';

type Opt = { value: string; label: string; tone?: Tone };

const props = defineProps<{
    consultation: {
        uuid: string;
        folio: string;
        startsAt: string;
        endsAt: string;
        status: Opt;
        channel: Opt;
        advisor: { name: string };
        meetingUrl: string | null;
        price: string;
        isActive: boolean;
        durationMinutes: number;
    };
    lead: { folio: string; firstName: string };
    canChange: boolean;
    settings: { enabled: boolean; durationMinutes: number };
    window: { min: string; max: string };
    availabilityUrl: string;
    rescheduleUrl: string;
    cancelUrl: string;
    bookAgainUrl: string | null;
}>();

const tz = { timeZone: TIME_ZONE } as const;
const dayText = computed(() =>
    new Intl.DateTimeFormat('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        ...tz,
    }).format(new Date(props.consultation.startsAt)),
);
const timeText = computed(() => {
    const f = new Intl.DateTimeFormat('es-MX', {
        hour: 'numeric',
        minute: '2-digit',
        ...tz,
    });

    return `${f.format(new Date(props.consultation.startsAt))} – ${f.format(new Date(props.consultation.endsAt))}`;
});

const channelIcon = computed(
    () =>
        ({ video: Video, phone: Phone, whatsapp: MessageCircle })[
            props.consultation.channel.value
        ] ?? Video,
);

const rescheduling = ref(false);
const date = ref<string | null>(null);
const form = useForm({ starts_at: null as string | null });

function reschedule() {
    form.post(props.rescheduleUrl, {
        preserveScroll: true,
        onSuccess: () => {
            rescheduling.value = false;
            date.value = null;
        },
    });
}

const cancelOpen = ref(false);
const reason = ref('');

function cancel(): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(
            props.cancelUrl,
            { reason: reason.value || null },
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (errors) =>
                    reject(
                        new Error(
                            Object.values(errors)[0] ??
                                'No pudimos cancelar. Inténtalo de nuevo.',
                        ),
                    ),
            },
        );
    });
}
</script>

<template>
    <Head title="Tu asesoría" />

    <section class="motion-calm bg-background pb-16">
        <div class="container-site pt-10 md:pt-16">
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-oak-ink">
                    Asesoría {{ consultation.folio }}
                </p>
                <h1 class="mt-2 text-display-md font-display tracking-tight">
                    <template v-if="consultation.isActive"
                        >{{ lead.firstName }}, tu asesoría está
                        agendada</template
                    >
                    <template
                        v-else-if="consultation.status.value === 'cancelled'"
                        >Tu asesoría se canceló</template
                    >
                    <template v-else>Tu asesoría</template>
                </h1>

                <article
                    class="mt-8 overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-4 border-b p-6"
                    >
                        <div>
                            <p
                                class="font-display text-2xl font-semibold tracking-tight first-letter:uppercase"
                            >
                                {{ dayText }}
                            </p>
                            <p
                                class="mt-1 text-lg text-muted-foreground tabular-nums"
                            >
                                {{ timeText }}
                                <span class="text-sm"
                                    >(hora del centro de México)</span
                                >
                            </p>
                        </div>
                        <StatusBadge
                            :label="consultation.status.label"
                            :tone="consultation.status.tone ?? 'neutral'"
                        />
                    </div>
                    <dl class="grid gap-5 p-6 sm:grid-cols-3">
                        <div class="flex gap-3">
                            <component
                                :is="channelIcon"
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Medio
                                </dt>
                                <dd class="text-sm font-medium">
                                    {{ consultation.channel.label }}
                                </dd>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <UserRound
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Te atiende
                                </dt>
                                <dd class="text-sm font-medium">
                                    {{ consultation.advisor.name }}
                                </dd>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <Clock
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Duración
                                </dt>
                                <dd class="text-sm font-medium">
                                    {{ consultation.durationMinutes }} minutos
                                </dd>
                            </div>
                        </div>
                    </dl>
                    <div
                        v-if="consultation.isActive"
                        class="border-t bg-muted/40 px-6 py-4 text-sm"
                    >
                        <template v-if="consultation.meetingUrl">
                            <Button as-child class="h-11">
                                <a
                                    :href="consultation.meetingUrl"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Entrar a la reunión
                                    <ExternalLink aria-hidden="true" />
                                </a>
                            </Button>
                        </template>
                        <p
                            v-else-if="consultation.channel.value === 'video'"
                            class="text-muted-foreground"
                        >
                            Te enviaremos el enlace de la videollamada por
                            correo antes de la cita.
                        </p>
                        <p v-else class="text-muted-foreground">
                            Tu asesor se comunicará contigo a la hora acordada.
                        </p>
                    </div>
                </article>

                <div
                    v-if="canChange && !rescheduling"
                    class="mt-6 flex flex-wrap gap-3"
                >
                    <Button
                        variant="outline"
                        class="h-11"
                        @click="rescheduling = true"
                    >
                        <CalendarClock aria-hidden="true" /> Reprogramar
                    </Button>
                    <Button
                        variant="ghost"
                        class="h-11 text-danger-ink"
                        @click="cancelOpen = true"
                    >
                        <CalendarX aria-hidden="true" /> Cancelar asesoría
                    </Button>
                </div>

                <section
                    v-if="canChange && rescheduling"
                    class="mt-8 space-y-4"
                    aria-labelledby="reschedule-title"
                >
                    <div>
                        <h2
                            id="reschedule-title"
                            class="font-display text-xl font-semibold tracking-tight"
                        >
                            Elige una nueva fecha
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Tu horario actual se libera solo cuando confirmes el
                            nuevo.
                        </p>
                    </div>
                    <ConsultationSlotPicker
                        v-model:date="date"
                        v-model:slot="form.starts_at"
                        :availability-url="availabilityUrl"
                        :min="window.min"
                        :max="window.max"
                        :exclude-slot="consultation.startsAt"
                    />
                    <InputError :message="form.errors.starts_at" />
                    <div class="flex flex-wrap gap-3">
                        <Button
                            class="h-11"
                            :disabled="!form.starts_at || form.processing"
                            @click="reschedule"
                        >
                            <Loader2
                                v-if="form.processing"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            {{
                                form.processing
                                    ? 'Reprogramando…'
                                    : 'Confirmar nueva fecha'
                            }}
                        </Button>
                        <Button
                            variant="ghost"
                            class="h-11"
                            :disabled="form.processing"
                            @click="rescheduling = false"
                            >Mantener mi horario</Button
                        >
                    </div>
                </section>

                <div
                    v-if="bookAgainUrl"
                    class="mt-8 rounded-xl border bg-card p-6"
                >
                    <h2
                        class="font-display text-lg font-semibold tracking-tight"
                    >
                        ¿Quieres agendar otra fecha?
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Elige un nuevo horario cuando te acomode.
                    </p>
                    <Button as-child class="mt-4 h-11">
                        <a :href="bookAgainUrl">Agendar asesoría</a>
                    </Button>
                </div>

                <p
                    v-if="!consultation.isActive && !bookAgainUrl"
                    class="mt-6 text-sm text-muted-foreground"
                >
                    Si necesitas algo más, responde al correo de confirmación y
                    con gusto te ayudamos.
                </p>
            </div>

            <ConfirmDialog
                v-model:open="cancelOpen"
                title="¿Cancelar tu asesoría?"
                confirm-label="Sí, cancelar"
                loading-label="Cancelando…"
                cancel-label="Mantener asesoría"
                tone="destructive"
                :confirm="cancel"
            >
                <span class="block"
                    >Liberaremos tu horario del {{ dayText }}. Puedes agendar
                    otra fecha cuando quieras.</span
                >
                <label
                    for="cancel-reason"
                    class="mt-4 block text-sm font-medium text-foreground"
                    >Motivo (opcional)</label
                >
                <Textarea
                    id="cancel-reason"
                    v-model="reason"
                    class="mt-2"
                    rows="2"
                    maxlength="200"
                    placeholder="Nos ayuda a mejorar"
                />
            </ConfirmDialog>
        </div>
    </section>
</template>
