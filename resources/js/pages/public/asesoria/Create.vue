<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarCheck, Clock, Loader2, Wallet } from '@lucide/vue';
import { computed, ref } from 'vue';
import ChoiceCards from '@/components/crm/ChoiceCards.vue';
import ConsultationSlotPicker from '@/components/crm/ConsultationSlotPicker.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney, TIME_ZONE } from '@/lib/format';

type Channel = { value: string; label: string; description: string };

const props = defineProps<{
    lead: { folio: string; firstName: string };
    settings: {
        enabled: boolean;
        durationMinutes: number;
        price: string;
        isFree: boolean;
        bookingWindowDays: number;
        minNoticeHours: number;
        channels: Channel[];
    };
    window: { min: string; max: string };
    availabilityUrl: string;
    submitUrl: string;
}>();

const date = ref<string | null>(null);

const form = useForm({
    starts_at: null as string | null,
    channel: (props.settings.channels[0]?.value ?? null) as string | null,
    notes: '',
});

const whenText = computed(() =>
    form.starts_at
        ? new Intl.DateTimeFormat('es-MX', {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
              hour: 'numeric',
              minute: '2-digit',
              timeZone: TIME_ZONE,
          }).format(new Date(form.starts_at))
        : null,
);

const channelLabel = computed(
    () =>
        props.settings.channels.find((c) => c.value === form.channel)?.label ??
        null,
);

function submit() {
    form.post(props.submitUrl, { preserveScroll: true });
}
</script>

<template>
    <Head title="Agenda tu asesoría" />

    <section class="motion-calm bg-background pb-16">
        <div class="container-site pt-10 md:pt-16">
            <div class="max-w-2xl" data-motion="fade-up">
                <p class="text-sm font-medium text-oak-ink">
                    Solicitud {{ lead.folio }}
                </p>
                <h1 class="mt-2 text-display-md font-display tracking-tight">
                    {{ lead.firstName }}, elige cuándo platicamos
                </h1>
                <p class="mt-3 text-lg text-muted-foreground">
                    En unos {{ settings.durationMinutes }} minutos conocemos tu
                    espacio, tu estilo y tu presupuesto, y te explicamos cómo
                    seguimos.
                </p>
            </div>

            <FormStatus
                v-if="!settings.enabled"
                tone="info"
                class="mt-8 max-w-2xl"
                message="Por ahora no estamos agendando asesorías en línea. Tu asesor te contactará para coordinar una fecha."
            />

            <form
                v-else
                class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]"
                novalidate
                @submit.prevent="submit"
            >
                <div class="space-y-8">
                    <section aria-labelledby="when-title" class="space-y-3">
                        <h2
                            id="when-title"
                            class="font-display text-xl font-semibold tracking-tight"
                        >
                            1. Día y hora
                        </h2>
                        <ConsultationSlotPicker
                            v-model:date="date"
                            v-model:slot="form.starts_at"
                            :availability-url="availabilityUrl"
                            :min="window.min"
                            :max="window.max"
                        />
                        <InputError :message="form.errors.starts_at" />
                    </section>

                    <section aria-labelledby="channel-title" class="space-y-3">
                        <h2
                            id="channel-title"
                            class="font-display text-xl font-semibold tracking-tight"
                        >
                            2. ¿Cómo prefieres tu asesoría?
                        </h2>
                        <ChoiceCards
                            v-model="form.channel"
                            name="channel"
                            label="Medio de la asesoría"
                            :options="settings.channels"
                            :columns="settings.channels.length >= 3 ? 3 : 2"
                            :invalid="!!form.errors.channel"
                            described-by="channel-error"
                        />
                        <InputError
                            id="channel-error"
                            :message="form.errors.channel"
                        />
                    </section>

                    <section class="space-y-2">
                        <Label for="notes"
                            >¿Algo que quieras adelantarnos?
                            <span class="font-normal text-muted-foreground"
                                >(opcional)</span
                            ></Label
                        >
                        <Textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            maxlength="1000"
                            placeholder="Ej. quiero una cocina en L con barra y mucho espacio de guardado"
                        />
                        <InputError :message="form.errors.notes" />
                    </section>
                </div>

                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <div class="rounded-xl border bg-card p-5 shadow-xs">
                        <h2 class="text-sm font-medium">Tu asesoría</h2>
                        <ul class="mt-4 space-y-3 text-sm">
                            <li class="flex gap-3">
                                <CalendarCheck
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <span
                                    :class="
                                        whenText
                                            ? 'first-letter:uppercase'
                                            : 'text-muted-foreground'
                                    "
                                    >{{ whenText ?? 'Elige día y hora' }}</span
                                >
                            </li>
                            <li class="flex gap-3">
                                <Clock
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <span
                                    >{{ settings.durationMinutes }} min<template
                                        v-if="channelLabel"
                                    >
                                        · {{ channelLabel }}</template
                                    ></span
                                >
                            </li>
                            <li class="flex gap-3">
                                <Wallet
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <span v-if="settings.isFree">Sin costo</span>
                                <span v-else>
                                    {{ formatMoney(settings.price) }}. Por ahora
                                    el pago de la asesoría se coordina con tu
                                    asesor.
                                </span>
                            </li>
                        </ul>
                        <Button
                            type="submit"
                            class="mt-5 h-11 w-full"
                            :disabled="
                                form.processing ||
                                !form.starts_at ||
                                !form.channel
                            "
                        >
                            <Loader2
                                v-if="form.processing"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            {{
                                form.processing
                                    ? 'Agendando…'
                                    : 'Confirmar asesoría'
                            }}
                        </Button>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Te enviamos la confirmación por correo y puedes
                            reprogramar si lo necesitas.
                        </p>
                    </div>
                </aside>
            </form>
        </div>
    </section>
</template>
