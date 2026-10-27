<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BadgePercent,
    CalendarClock,
    ShieldCheck,
    UserRoundPlus,
} from '@lucide/vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import configuracion from '@/routes/admin/configuracion';

type Props = {
    settings: {
        commercial: {
            deposit_percent: string;
            tax_rate: string;
            prices_include_tax: boolean;
            quote_validity_days: number;
            currency: string;
        };
        consultation: {
            enabled: boolean;
            duration_minutes: number;
            buffer_minutes: number;
            price: string;
            booking_window_days: number;
            min_notice_hours: number;
            channels: string[];
        };
        leads: { follow_up_hours: number };
        security: { require_2fa_for_staff: boolean };
    };
    channels: { value: string; label: string }[];
    links: { visitPolicy: string; site: string };
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Configuración', href: '/admin/configuracion' },
        ],
    },
});

const commercial = useForm({ ...props.settings.commercial });
const consultation = useForm({
    ...props.settings.consultation,
    channels: [...props.settings.consultation.channels],
});
const leads = useForm({ ...props.settings.leads });
const security = useForm({ ...props.settings.security });

const save = (
    form:
        | typeof commercial
        | typeof consultation
        | typeof leads
        | typeof security,
    group: string,
) =>
    form.put(configuracion.update(group).url, {
        preserveScroll: true,
        // Lo guardado pasa a ser el nuevo punto de partida (isDirty = false).
        onSuccess: () => form.defaults(),
    });

function toggleChannel(value: string, checked: boolean | 'indeterminate') {
    const set = new Set(consultation.channels);

    if (checked === true) {
        set.add(value);
    } else {
        set.delete(value);
    }

    consultation.channels = props.channels
        .map((c) => c.value)
        .filter((v) => set.has(v));
}
</script>

<template>
    <Head title="Configuración" />

    <PageContainer width="narrow">
        <PageHeader
            title="Configuración"
            description="Parámetros de negocio que usan cotizaciones, contratos, asesorías y seguimiento. Cada cambio queda en la bitácora."
        />

        <div class="grid gap-3 sm:grid-cols-2">
            <Link
                :href="links.visitPolicy"
                class="group flex items-start justify-between gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-muted/40"
            >
                <span>
                    <span class="block font-medium">Política de visitas</span>
                    <span class="text-sm text-muted-foreground"
                        >Tarifa, acreditación y reglas de la visita
                        técnica.</span
                    >
                </span>
                <ArrowUpRight
                    class="size-4 shrink-0 text-muted-foreground group-hover:text-foreground"
                    aria-hidden="true"
                />
            </Link>
            <Link
                :href="links.site"
                class="group flex items-start justify-between gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-muted/40"
            >
                <span>
                    <span class="block font-medium">Marca, contacto y SEO</span>
                    <span class="text-sm text-muted-foreground"
                        >Se editan desde Sitio web.</span
                    >
                </span>
                <ArrowUpRight
                    class="size-4 shrink-0 text-muted-foreground group-hover:text-foreground"
                    aria-hidden="true"
                />
            </Link>
        </div>

        <!-- Comercial -->
        <SectionCard
            title="Comercial"
            description="Anticipo, impuestos y vigencia de cotizaciones."
            :icon="BadgePercent"
        >
            <form
                id="form-commercial"
                class="grid gap-5"
                @submit.prevent="save(commercial, 'commercial')"
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="deposit_percent"
                            >Anticipo sugerido (%)</Label
                        >
                        <Input
                            id="deposit_percent"
                            v-model="commercial.deposit_percent"
                            inputmode="decimal"
                            :aria-invalid="
                                commercial.errors.deposit_percent
                                    ? true
                                    : undefined
                            "
                            aria-describedby="deposit-help"
                        />
                        <p
                            id="deposit-help"
                            class="text-sm text-muted-foreground"
                        >
                            Aplica a contratos nuevos. Los contratos ya firmados
                            conservan el porcentaje con el que se pactaron.
                        </p>
                        <InputError
                            :message="commercial.errors.deposit_percent"
                        />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="tax_rate">IVA (%)</Label>
                        <Input
                            id="tax_rate"
                            v-model="commercial.tax_rate"
                            inputmode="decimal"
                            :aria-invalid="
                                commercial.errors.tax_rate ? true : undefined
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            En México la tasa general es 16 %.
                        </p>
                        <InputError :message="commercial.errors.tax_rate" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="quote_validity_days"
                            >Vigencia de cotizaciones (días)</Label
                        >
                        <Input
                            id="quote_validity_days"
                            v-model.number="commercial.quote_validity_days"
                            type="number"
                            min="1"
                            max="365"
                            inputmode="numeric"
                            :aria-invalid="
                                commercial.errors.quote_validity_days
                                    ? true
                                    : undefined
                            "
                        />
                        <InputError
                            :message="commercial.errors.quote_validity_days"
                        />
                    </div>
                    <div
                        class="flex items-start justify-between gap-4 rounded-lg border p-4"
                    >
                        <div class="grid gap-1">
                            <Label for="prices_include_tax"
                                >Precios con IVA incluido</Label
                            >
                            <p class="text-sm text-muted-foreground">
                                Si está apagado, el IVA se suma al subtotal de
                                la cotización.
                            </p>
                        </div>
                        <Switch
                            id="prices_include_tax"
                            v-model="commercial.prices_include_tax"
                        />
                    </div>
                </div>
                <p class="text-sm text-muted-foreground">
                    Moneda: {{ settings.commercial.currency }}
                </p>
            </form>
            <template #footer>
                <Button
                    type="submit"
                    form="form-commercial"
                    :disabled="commercial.processing || !commercial.isDirty"
                >
                    <Spinner v-if="commercial.processing" />
                    {{ commercial.processing ? 'Guardando…' : 'Guardar' }}
                </Button>
            </template>
        </SectionCard>

        <!-- Asesorías -->
        <SectionCard
            title="Asesorías"
            description="Cita remota inicial con un asesor."
            :icon="CalendarClock"
        >
            <form
                id="form-consultation"
                class="grid gap-5"
                @submit.prevent="save(consultation, 'consultation')"
            >
                <div
                    class="flex items-start justify-between gap-4 rounded-lg border p-4"
                >
                    <div class="grid gap-1">
                        <Label for="consultation_enabled"
                            >Reservas de asesoría abiertas</Label
                        >
                        <p class="text-sm text-muted-foreground">
                            Si las cierras, el sitio deja de ofrecer horarios.
                        </p>
                    </div>
                    <Switch
                        id="consultation_enabled"
                        v-model="consultation.enabled"
                    />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="duration_minutes">Duración (minutos)</Label>
                        <Input
                            id="duration_minutes"
                            v-model.number="consultation.duration_minutes"
                            type="number"
                            min="10"
                            max="240"
                            inputmode="numeric"
                        />
                        <InputError
                            :message="consultation.errors.duration_minutes"
                        />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="buffer_minutes"
                            >Margen entre citas (minutos)</Label
                        >
                        <Input
                            id="buffer_minutes"
                            v-model.number="consultation.buffer_minutes"
                            type="number"
                            min="0"
                            max="120"
                            inputmode="numeric"
                        />
                        <InputError
                            :message="consultation.errors.buffer_minutes"
                        />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="price">Precio (MXN)</Label>
                        <Input
                            id="price"
                            v-model="consultation.price"
                            inputmode="decimal"
                        />
                        <p class="text-sm text-muted-foreground">
                            0 = asesoría gratuita.
                        </p>
                        <InputError :message="consultation.errors.price" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="booking_window_days"
                            >Se puede reservar hasta (días)</Label
                        >
                        <Input
                            id="booking_window_days"
                            v-model.number="consultation.booking_window_days"
                            type="number"
                            min="1"
                            max="120"
                            inputmode="numeric"
                        />
                        <InputError
                            :message="consultation.errors.booking_window_days"
                        />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="min_notice_hours"
                            >Anticipación mínima (horas)</Label
                        >
                        <Input
                            id="min_notice_hours"
                            v-model.number="consultation.min_notice_hours"
                            type="number"
                            min="0"
                            max="168"
                            inputmode="numeric"
                        />
                        <InputError
                            :message="consultation.errors.min_notice_hours"
                        />
                    </div>
                </div>
                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        Canales disponibles
                    </legend>
                    <label
                        v-for="channel in channels"
                        :key="channel.value"
                        class="flex min-h-11 items-center gap-3"
                    >
                        <Checkbox
                            :model-value="
                                consultation.channels.includes(channel.value)
                            "
                            @update:model-value="
                                (v) => toggleChannel(channel.value, v)
                            "
                        />
                        <span class="text-sm">{{ channel.label }}</span>
                    </label>
                    <InputError :message="consultation.errors.channels" />
                </fieldset>
            </form>
            <template #footer>
                <Button
                    type="submit"
                    form="form-consultation"
                    :disabled="consultation.processing || !consultation.isDirty"
                >
                    <Spinner v-if="consultation.processing" />
                    {{ consultation.processing ? 'Guardando…' : 'Guardar' }}
                </Button>
            </template>
        </SectionCard>

        <!-- Prospectos -->
        <SectionCard
            title="Prospectos"
            description="Tiempo de respuesta esperado."
            :icon="UserRoundPlus"
        >
            <form
                id="form-leads"
                class="grid gap-2 sm:max-w-sm"
                @submit.prevent="save(leads, 'leads')"
            >
                <Label for="follow_up_hours"
                    >Primer contacto antes de (horas)</Label
                >
                <Input
                    id="follow_up_hours"
                    v-model.number="leads.follow_up_hours"
                    type="number"
                    min="1"
                    max="336"
                    inputmode="numeric"
                />
                <p class="text-sm text-muted-foreground">
                    Pasado este tiempo, un prospecto nuevo sin contactar aparece
                    en "Requiere tu atención".
                </p>
                <InputError :message="leads.errors.follow_up_hours" />
            </form>
            <template #footer>
                <Button
                    type="submit"
                    form="form-leads"
                    :disabled="leads.processing || !leads.isDirty"
                >
                    <Spinner v-if="leads.processing" />
                    {{ leads.processing ? 'Guardando…' : 'Guardar' }}
                </Button>
            </template>
        </SectionCard>

        <!-- Seguridad -->
        <SectionCard
            title="Seguridad"
            description="Acceso del equipo interno al panel."
            :icon="ShieldCheck"
        >
            <form
                id="form-security"
                class="grid gap-2"
                @submit.prevent="save(security, 'security')"
            >
                <div
                    class="flex items-start justify-between gap-4 rounded-lg border p-4"
                >
                    <div class="grid gap-1">
                        <Label for="require_2fa_for_staff"
                            >Exigir verificación en dos pasos al equipo</Label
                        >
                        <p class="text-sm text-muted-foreground">
                            Quien no la tenga activa será enviado a Cuenta →
                            Seguridad antes de entrar al panel. Clientes y
                            técnicos no se ven afectados.
                        </p>
                    </div>
                    <Switch
                        id="require_2fa_for_staff"
                        v-model="security.require_2fa_for_staff"
                    />
                </div>
                <InputError :message="security.errors.require_2fa_for_staff" />
            </form>
            <template #footer>
                <Button
                    type="submit"
                    form="form-security"
                    :disabled="security.processing || !security.isDirty"
                >
                    <Spinner v-if="security.processing" />
                    {{ security.processing ? 'Guardando…' : 'Guardar' }}
                </Button>
            </template>
        </SectionCard>
    </PageContainer>
</template>
