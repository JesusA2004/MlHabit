<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { FileSignature, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/format';

type Option = { value: string; label: string; tone?: string };

type Version = {
    id: number;
    version: number;
    fee: string;
    currency: string;
    isCreditable: boolean;
    creditType: string | null;
    creditValue: string | null;
    creditAmount: string;
    creditValidityDays: number | null;
    creditConditions: string | null;
    durationMinutes: number;
    bufferMinutes: number;
    holdMinutes: number;
    minNoticeHours: number;
    bookingWindowDays: number;
    scope: string;
    cancellationPolicy: string;
    reschedulePolicy: string;
    isActive: boolean;
    activatedAt: string | null;
    createdAt: string | null;
    createdBy: string | null;
    visitsCount: number;
};

const props = defineProps<{
    versions: Version[];
    creditTypes: Option[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Visitas', href: '/admin/visitas' },
            { title: 'Política', href: '/admin/visitas/politica' },
        ],
    },
});

const active = computed(() => props.versions.find((v) => v.isActive) ?? null);

function creditSummary(v: Version): string {
    if (!v.isCreditable) {
        return 'No abonable al proyecto';
    }

    const how =
        v.creditType === 'percent'
            ? `${Number(v.creditValue)} % de la tarifa`
            : `monto fijo de ${formatMoney(v.creditValue) ?? ''}`;

    return `Abona ${formatMoney(v.creditAmount)} (${how}) al anticipo, vigente ${v.creditValidityDays ?? '—'} días`;
}

// ---------------------------------------------------------------- Activar

const toActivate = ref<Version | null>(null);
const activateOpen = ref(false);

function askActivate(v: Version) {
    toActivate.value = v;
    activateOpen.value = true;
}

function activate(): Promise<void> {
    const v = toActivate.value;

    return new Promise((resolve, reject) => {
        if (!v) {
            resolve();

            return;
        }

        router.post(
            `/admin/visitas/politica/${v.id}/activar`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (errors) =>
                    reject(
                        new Error(
                            Object.values(errors)[0] ?? 'No se pudo activar.',
                        ),
                    ),
            },
        );
    });
}

// ---------------------------------------------------------------- Nueva versión

const sheetOpen = ref(false);
const source = active.value ?? props.versions[0] ?? null;

const form = useForm({
    fee_amount: source?.fee ?? '',
    is_creditable: source?.isCreditable ?? false,
    credit_type: (source?.creditType ?? 'percent') as string,
    credit_value: source?.creditValue ?? '',
    credit_validity_days: (source?.creditValidityDays ?? 60) as number | string,
    credit_conditions: source?.creditConditions ?? '',
    duration_minutes: (source?.durationMinutes ?? 90) as number | string,
    buffer_minutes: (source?.bufferMinutes ?? 30) as number | string,
    hold_minutes: (source?.holdMinutes ?? 15) as number | string,
    min_notice_hours: (source?.minNoticeHours ?? 24) as number | string,
    booking_window_days: (source?.bookingWindowDays ?? 30) as number | string,
    scope: source?.scope ?? '',
    cancellation_policy: source?.cancellationPolicy ?? '',
    reschedule_policy: source?.reschedulePolicy ?? '',
});

/** Solo para mostrar: el backend calcula el crédito real con precisión decimal. */
const example = computed(() => {
    const fee = Number(form.fee_amount);
    const value = Number(form.credit_value);

    if (!Number.isFinite(fee) || fee <= 0) {
        return null;
    }

    let credit = 0;
    if (form.is_creditable && Number.isFinite(value)) {
        credit =
            form.credit_type === 'percent'
                ? (fee * Math.min(value, 100)) / 100
                : Math.min(value, fee);
    }

    return { fee: formatMoney(fee), credit: formatMoney(credit) };
});

function submit() {
    form.transform((d) => ({
        ...d,
        credit_type: d.is_creditable ? d.credit_type : null,
        credit_value: d.is_creditable ? d.credit_value : null,
        credit_validity_days: d.is_creditable ? d.credit_validity_days : null,
    })).post('/admin/visitas/politica', {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    });
}

const numbers = [
    { key: 'duration_minutes', label: 'Duración de la visita (min)' },
    { key: 'buffer_minutes', label: 'Margen entre visitas (min)' },
    { key: 'hold_minutes', label: 'Apartado mientras paga (min)' },
    { key: 'min_notice_hours', label: 'Anticipación mínima (h)' },
    { key: 'booking_window_days', label: 'Ventana de reserva (días)' },
] as const;

const texts = [
    {
        key: 'scope',
        label: 'Alcance de la visita',
        placeholder:
            'Qué incluye: medición, revisión de instalaciones, recomendaciones…',
    },
    {
        key: 'cancellation_policy',
        label: 'Política de cancelación',
        placeholder: 'Ej. cancelación sin costo con 24 h de anticipación',
    },
    {
        key: 'reschedule_policy',
        label: 'Política de reprogramación',
        placeholder:
            'Ej. una reprogramación sin costo con 24 h de anticipación',
    },
] as const;
</script>

<template>
    <Head title="Política de visita" />

    <PageContainer width="narrow">
        <PageHeader
            title="Política de visita técnica"
            description="Tarifa, crédito abonable, agenda y condiciones. Cada versión es inmutable: las visitas ya reservadas conservan los términos con los que se apartaron."
        >
            <template #actions>
                <Button v-if="can.manage" @click="sheetOpen = true"
                    ><Plus class="size-4" /> Nueva versión</Button
                >
            </template>
        </PageHeader>

        <FormStatus
            v-if="!active && versions.length"
            tone="info"
            message="Ninguna versión está activa: el checkout público muestra «Por ahora agenda tu visita con un asesor»."
        />

        <EmptyState
            v-if="!versions.length"
            :icon="FileSignature"
            title="Aún no hay política de visita"
            description="El checkout público queda cerrado hasta activar una versión. La tarifa de la visita es una decisión comercial pendiente: crea la versión cuando esté aprobada."
        >
            <template v-if="can.manage" #action>
                <Button @click="sheetOpen = true"
                    ><Plus class="size-4" /> Crear primera versión</Button
                >
            </template>
        </EmptyState>

        <ol v-else class="space-y-4">
            <li
                v-for="v in versions"
                :key="v.id"
                class="rounded-xl border bg-card p-5 shadow-xs"
                :class="
                    v.isActive && 'border-primary/40 ring-1 ring-primary/20'
                "
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-display text-lg font-semibold">
                                Versión {{ v.version }}
                            </h2>
                            <StatusBadge
                                v-if="v.isActive"
                                tone="success"
                                label="Activa"
                                size="sm"
                            />
                            <StatusBadge
                                v-else-if="!v.activatedAt"
                                tone="neutral"
                                label="Borrador"
                                size="sm"
                            />
                            <StatusBadge
                                v-else
                                tone="neutral"
                                label="Anterior"
                                size="sm"
                            />
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Creada
                            <DateText
                                :value="v.createdAt"
                                format="medium"
                            /><template v-if="v.createdBy">
                                por {{ v.createdBy }}</template
                            >
                            · {{ v.visitsCount }}
                            {{ v.visitsCount === 1 ? 'visita' : 'visitas' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <Money
                            :value="v.fee"
                            class="font-display text-2xl font-semibold"
                        />
                        <Button
                            v-if="can.manage && !v.isActive"
                            size="sm"
                            variant="outline"
                            class="mt-2"
                            @click="askActivate(v)"
                            >Activar</Button
                        >
                    </div>
                </div>
                <p class="mt-3 text-sm">{{ creditSummary(v) }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ v.durationMinutes }} min por visita · margen
                    {{ v.bufferMinutes }} min · apartado {{ v.holdMinutes }} min
                    · anticipación {{ v.minNoticeHours }} h · reservas a
                    {{ v.bookingWindowDays }} días
                </p>
                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer font-medium">
                        Textos para el cliente
                    </summary>
                    <dl class="mt-2 space-y-2 text-muted-foreground">
                        <div>
                            <dt class="font-medium text-foreground">Alcance</dt>
                            <dd class="whitespace-pre-line">{{ v.scope }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-foreground">
                                Cancelación
                            </dt>
                            <dd class="whitespace-pre-line">
                                {{ v.cancellationPolicy }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-foreground">
                                Reprogramación
                            </dt>
                            <dd class="whitespace-pre-line">
                                {{ v.reschedulePolicy }}
                            </dd>
                        </div>
                        <div v-if="v.creditConditions">
                            <dt class="font-medium text-foreground">
                                Condiciones del crédito
                            </dt>
                            <dd class="whitespace-pre-line">
                                {{ v.creditConditions }}
                            </dd>
                        </div>
                    </dl>
                </details>
            </li>
        </ol>
    </PageContainer>

    <ConfirmDialog
        v-model:open="activateOpen"
        :title="`¿Activar la versión ${toActivate?.version ?? ''}?`"
        description="Las nuevas reservas usarán esta tarifa y condiciones. Las visitas ya apartadas o pagadas conservan las suyas."
        confirm-label="Activar"
        loading-label="Activando…"
        :confirm="activate"
    />

    <FormSheet
        v-model:open="sheetOpen"
        title="Nueva versión de la política"
        description="Se guarda como borrador; actívala cuando esté lista."
        size="xl"
    >
        <form
            id="policy-form"
            class="space-y-6"
            novalidate
            @submit.prevent="submit"
        >
            <div class="space-y-2">
                <Label for="fee_amount"
                    >Tarifa de la visita (MXN, IVA incluido)</Label
                >
                <Input
                    id="fee_amount"
                    v-model="form.fee_amount"
                    inputmode="decimal"
                    placeholder="0.00"
                />
                <InputError :message="form.errors.fee_amount" />
            </div>

            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <Label for="is_creditable" class="leading-snug"
                        >Abonable al anticipo si contrata el proyecto</Label
                    >
                    <Switch id="is_creditable" v-model="form.is_creditable" />
                </div>
                <template v-if="form.is_creditable">
                    <RadioGroup
                        v-model="form.credit_type"
                        class="grid gap-2 sm:grid-cols-2"
                        aria-label="Tipo de crédito"
                    >
                        <label
                            v-for="t in creditTypes"
                            :key="t.value"
                            class="flex cursor-pointer items-center gap-2 rounded-md border p-3 text-sm has-[[data-state=checked]]:border-primary"
                        >
                            <RadioGroupItem :value="t.value" /> {{ t.label }}
                        </label>
                    </RadioGroup>
                    <InputError :message="form.errors.credit_type" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="credit_value">{{
                                form.credit_type === 'percent'
                                    ? 'Porcentaje (%)'
                                    : 'Monto (MXN)'
                            }}</Label>
                            <Input
                                id="credit_value"
                                v-model="form.credit_value"
                                inputmode="decimal"
                            />
                            <InputError :message="form.errors.credit_value" />
                        </div>
                        <div class="space-y-2">
                            <Label for="credit_validity_days"
                                >Vigencia (días)</Label
                            >
                            <Input
                                id="credit_validity_days"
                                v-model="form.credit_validity_days"
                                type="number"
                                min="1"
                                max="730"
                            />
                            <InputError
                                :message="form.errors.credit_validity_days"
                            />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="credit_conditions"
                            >Condiciones del crédito (opcional)</Label
                        >
                        <Textarea
                            id="credit_conditions"
                            v-model="form.credit_conditions"
                            rows="2"
                            maxlength="2000"
                        />
                    </div>
                </template>
                <p
                    v-if="example"
                    class="rounded-md bg-muted/60 px-3 py-2 text-sm"
                >
                    Con esta política: tarifa {{ example.fee }}; crédito
                    abonable {{ example.credit }}.
                    <span class="block text-xs text-muted-foreground"
                        >Ejemplo ilustrativo; el sistema calcula y congela el
                        monto exacto en cada visita.</span
                    >
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div v-for="n in numbers" :key="n.key" class="space-y-2">
                    <Label :for="n.key">{{ n.label }}</Label>
                    <Input
                        :id="n.key"
                        v-model="form[n.key]"
                        type="number"
                        min="0"
                    />
                    <InputError :message="form.errors[n.key]" />
                </div>
            </div>

            <div v-for="t in texts" :key="t.key" class="space-y-2">
                <Label :for="t.key">{{ t.label }}</Label>
                <Textarea
                    :id="t.key"
                    v-model="form[t.key]"
                    rows="3"
                    maxlength="5000"
                    :placeholder="t.placeholder"
                />
                <InputError :message="form.errors[t.key]" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="sheetOpen = false"
                >Cancelar</Button
            >
            <Button type="submit" form="policy-form" :disabled="form.processing"
                >Guardar borrador</Button
            >
        </template>
    </FormSheet>
</template>
