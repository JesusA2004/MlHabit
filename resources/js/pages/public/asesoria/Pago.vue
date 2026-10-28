<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import type { OpenpayClientConfig } from '@/components/payments/openpay';
import SecureCheckout from '@/components/payments/SecureCheckout.vue';
import type { CheckoutSummaryItem } from '@/components/payments/SecureCheckout.vue';
import { TIME_ZONE } from '@/lib/format';

type Option = { value: string; label: string; tone?: string };

const props = defineProps<{
    consultation: {
        uuid: string;
        folio: string;
        startsAt: string;
        endsAt: string;
        durationMinutes: number;
        channel: { value: string; label: string; description: string };
        advisorFirstName: string;
        customerFirstName: string;
        holdExpiresAt: string | null;
        price: string;
    };
    payment: {
        uuid: string;
        folio: string;
        amount: string;
        description: string;
        status: Option;
        errorMessage: string | null;
    };
    openpay: OpenpayClientConfig;
    chargeUrl: string;
    manageUrl: string;
    restartUrl: string;
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

    return `${f.format(new Date(props.consultation.startsAt))} – ${f.format(new Date(props.consultation.endsAt))} (hora del centro)`;
});

const channelIcon = computed<CheckoutSummaryItem['icon']>(() => {
    switch (props.consultation.channel.value) {
        case 'phone':
            return 'phone';
        case 'whatsapp':
            return 'whatsapp';
        default:
            return 'video';
    }
});

const summary = computed(() => ({
    concept: `Asesoría por ${props.consultation.channel.label.toLowerCase()}`,
    items: [
        { icon: 'calendar', label: 'Fecha', value: dayText.value },
        { icon: 'clock', label: 'Horario', value: timeText.value },
        {
            icon: channelIcon.value,
            label: 'Medio',
            value: `${props.consultation.channel.label} · ${props.consultation.durationMinutes} min`,
        },
        {
            icon: 'user',
            label: 'Te atiende',
            value: props.consultation.advisorFirstName,
        },
    ] satisfies CheckoutSummaryItem[],
    note: `Folio de asesoría ${props.consultation.folio}. Si necesitas cambiar la fecha después de pagar, puedes reprogramarla sin volver a pagar.`,
}));
</script>

<template>
    <Head title="Paga tu asesoría" />

    <SecureCheckout
        :eyebrow="`Asesoría ${consultation.folio} · pago seguro`"
        :title="`${consultation.customerFirstName}, confirma tu asesoría con el pago`"
        subtitle="Último paso: al autorizarse el cargo tu horario queda confirmado y te enviamos los detalles por correo."
        :hold-expires-at="consultation.holdExpiresAt"
        :payment="payment"
        :openpay="openpay"
        :charge-url="chargeUrl"
        :restart-url="restartUrl"
        restart-label="Elegir otro horario"
        :summary="summary"
        confirm-hint="Crédito o débito. Tu asesoría queda confirmada en cuanto el banco autoriza el cargo."
    >
        <template #aside>
            <Link
                :href="manageUrl"
                class="flex items-center gap-2 px-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Ver mi asesoría o cancelar el apartado
            </Link>
        </template>
    </SecureCheckout>
</template>
