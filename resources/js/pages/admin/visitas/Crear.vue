<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarCheck, MapPinned, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DateSlotPicker from '@/components/ds/DateSlotPicker.vue';
import type { TimeSlot } from '@/components/ds/DateSlotPicker.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { apiFetch } from '@/lib/http';

type Option = { value: string; label: string; tone?: string };

type Policy = {
    version: number;
    fee: string;
    durationMinutes: number;
    isCreditable: boolean;
    creditAmount: string;
};

type Prefill = {
    lead: string;
    leadFolio: string;
    firstName: string;
    lastName: string | null;
    email: string | null;
    phone: string | null;
    postalCode: string | null;
    address: {
        street: string;
        exteriorNumber: string;
        interiorNumber: string | null;
        neighborhood: string;
        postalCode: string;
        municipality: string;
        state: string;
        references: string | null;
    } | null;
};

const props = defineProps<{
    policy: Policy | null;
    prefill: Prefill | null;
    manualMethods: Option[];
    can: { record: boolean };
    holdHours: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Visitas', href: '/admin/visitas' },
            { title: 'Nueva visita', href: '/admin/visitas/crear' },
        ],
    },
});

const p = props.prefill;
const a = p?.address ?? null;

const form = useForm({
    idempotency_key: crypto.randomUUID(),
    lead: p?.lead ?? null,
    postal_code: p?.postalCode ?? a?.postalCode ?? '',
    date: null as string | null,
    start: null as string | null,
    technician_id: null as string | null,
    first_name: p?.firstName ?? '',
    last_name: p?.lastName ?? '',
    email: p?.email ?? '',
    phone: p?.phone ?? '',
    street: a?.street ?? '',
    exterior_number: a?.exteriorNumber ?? '',
    interior_number: a?.interiorNumber ?? '',
    neighborhood: a?.neighborhood ?? '',
    municipality: a?.municipality ?? '',
    state: a?.state ?? '',
    references: a?.references ?? '',
    access_notes: '',
    internal_notes: '',
    payment_action: 'link' as 'link' | 'manual',
    method: (props.manualMethods[0]?.value ?? 'transfer') as string,
    reference: '',
});

// ---------------------------------------------------------------- Disponibilidad

type Availability = {
    covered: boolean;
    message: string | null;
    zone: { id: number; name: string } | null;
    dates: string[];
    slots: { start: string; end: string; technicians: number }[];
    technicians: Option[];
};

const zone = ref<Availability['zone']>(null);
const dates = ref<string[]>([]);
const technicians = ref<Option[]>([]);
const slots = ref<TimeSlot[]>([]);
const checking = ref(false);
const loadingSlots = ref(false);
const coverageMessage = ref<string | null>(null);

function url(extra: Record<string, string> = {}): string {
    return `/admin/visitas/disponibilidad?${new URLSearchParams({ cp: form.postal_code, ...extra }).toString()}`;
}

async function checkCoverage() {
    if (!/^\d{5}$/.test(form.postal_code)) {
        coverageMessage.value = 'Escribe un código postal de 5 dígitos.';

        return;
    }

    checking.value = true;
    coverageMessage.value = null;
    form.date = null;
    form.start = null;

    try {
        const json = (await (await apiFetch(url())).json()) as Availability;
        zone.value = json.zone;
        dates.value = json.dates;
        technicians.value = json.technicians;
        coverageMessage.value = json.covered
            ? json.dates.length === 0
                ? 'No hay horarios libres en la ventana de reserva.'
                : null
            : json.message;
    } catch {
        coverageMessage.value =
            'No se pudo consultar la cobertura. Inténtalo de nuevo.';
    } finally {
        checking.value = false;
    }
}

async function loadSlots() {
    slots.value = [];
    form.start = null;

    if (!form.date || !zone.value) {
        return;
    }

    loadingSlots.value = true;

    try {
        const extra: Record<string, string> = { fecha: form.date };
        if (form.technician_id) {
            extra.tecnico = form.technician_id;
        }
        const json = (await (
            await apiFetch(url(extra))
        ).json()) as Availability;
        slots.value = json.slots.map((s) => ({
            value: s.start,
            label: `${s.start} – ${s.end}`,
            hint:
                s.technicians > 1
                    ? `${s.technicians} técnicos libres`
                    : undefined,
        }));
    } finally {
        loadingSlots.value = false;
    }
}

watch(() => [form.date, form.technician_id], loadSlots);

if (form.postal_code) {
    void checkCoverage();
}

const isDateDisabled = (iso: string) => !dates.value.includes(iso);
const generalError = computed(
    () => (form.errors as Record<string, string | undefined>).form ?? null,
);

function submit() {
    form.transform((d) => ({
        ...d,
        technician_id: d.technician_id ? Number(d.technician_id) : null,
        method: d.payment_action === 'manual' ? d.method : null,
        reference: d.payment_action === 'manual' ? d.reference : null,
    })).post('/admin/visitas', { preserveScroll: true });
}

const fields = [
    {
        key: 'street',
        label: 'Calle',
        autocomplete: 'address-line1',
        span: 'md:col-span-2',
    },
    {
        key: 'exterior_number',
        label: 'Número exterior',
        autocomplete: 'off',
        span: '',
    },
    {
        key: 'interior_number',
        label: 'Número interior (opcional)',
        autocomplete: 'off',
        span: '',
    },
    {
        key: 'neighborhood',
        label: 'Colonia',
        autocomplete: 'address-level3',
        span: '',
    },
    {
        key: 'municipality',
        label: 'Municipio o alcaldía',
        autocomplete: 'address-level2',
        span: '',
    },
    { key: 'state', label: 'Estado', autocomplete: 'address-level1', span: '' },
    {
        key: 'references',
        label: 'Referencias (opcional)',
        autocomplete: 'off',
        span: 'md:col-span-2',
    },
] as const;
</script>

<template>
    <Head title="Nueva visita" />

    <PageContainer width="narrow">
        <PageHeader
            title="Nueva visita técnica"
            :description="
                prefill
                    ? `Para el prospecto ${prefill.leadFolio}. Aparta el horario y envía el enlace de pago o registra un pago recibido.`
                    : 'Aparta un horario para un cliente y envía el enlace de pago o registra un pago recibido.'
            "
        />

        <EmptyState
            v-if="!policy"
            :icon="CalendarCheck"
            title="No hay una política de visita activa"
            description="Define la tarifa y condiciones de la visita antes de agendar."
        >
            <template #action>
                <Button as-child
                    ><Link href="/admin/visitas/politica"
                        >Ir a la política de visita</Link
                    ></Button
                >
            </template>
        </EmptyState>

        <form v-else class="space-y-6" novalidate @submit.prevent="submit">
            <FormStatus
                v-if="generalError"
                tone="danger"
                :message="generalError"
            />

            <SectionCard title="1. Cobertura y horario" :icon="MapPinned">
                <div class="space-y-5">
                    <div class="flex items-end gap-2">
                        <div class="flex-1 space-y-2">
                            <Label for="postal_code"
                                >Código postal del domicilio</Label
                            >
                            <Input
                                id="postal_code"
                                v-model="form.postal_code"
                                inputmode="numeric"
                                maxlength="5"
                                autocomplete="postal-code"
                                @keydown.enter.prevent="checkCoverage"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="checking"
                            @click="checkCoverage"
                        >
                            <Spinner v-if="checking" /><Search
                                v-else
                                class="size-4"
                            />
                            Consultar
                        </Button>
                    </div>
                    <InputError :message="form.errors.postal_code" />
                    <FormStatus
                        v-if="coverageMessage"
                        tone="info"
                        :message="coverageMessage"
                    />
                    <p v-if="zone" class="text-sm text-muted-foreground">
                        Zona:
                        <span class="font-medium text-foreground">{{
                            zone.name
                        }}</span>
                    </p>

                    <template v-if="zone && dates.length">
                        <div class="space-y-2">
                            <Label for="technician">Técnico (opcional)</Label>
                            <SearchSelect
                                id="technician"
                                v-model="form.technician_id"
                                :options="technicians"
                                placeholder="Asignar al menos cargado"
                                clearable
                            />
                        </div>
                        <DateSlotPicker
                            v-model:date="form.date"
                            v-model:slot="form.start"
                            :slots="slots"
                            :loading="loadingSlots"
                            :is-date-disabled="isDateDisabled"
                            :min="dates[0]"
                            :max="dates[dates.length - 1]"
                        />
                        <InputError
                            :message="form.errors.start ?? form.errors.date"
                        />
                    </template>
                </div>
            </SectionCard>

            <SectionCard title="2. Cliente">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="first_name">Nombre</Label>
                        <Input
                            id="first_name"
                            v-model="form.first_name"
                            autocomplete="given-name"
                        />
                        <InputError :message="form.errors.first_name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="last_name">Apellidos</Label>
                        <Input
                            id="last_name"
                            v-model="form.last_name"
                            autocomplete="family-name"
                        />
                        <InputError :message="form.errors.last_name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="space-y-2">
                        <Label for="phone">Teléfono</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            inputmode="tel"
                            autocomplete="tel"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="3. Domicilio">
                <div class="grid gap-4 md:grid-cols-2">
                    <div
                        v-for="f in fields"
                        :key="f.key"
                        :class="['space-y-2', f.span]"
                    >
                        <Label :for="f.key">{{ f.label }}</Label>
                        <Input
                            :id="f.key"
                            v-model="form[f.key]"
                            :autocomplete="f.autocomplete"
                        />
                        <InputError :message="form.errors[f.key]" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label for="access_notes"
                            >Indicaciones de acceso (opcional)</Label
                        >
                        <Textarea
                            id="access_notes"
                            v-model="form.access_notes"
                            rows="2"
                            maxlength="1000"
                            placeholder="Ej. caseta de vigilancia, dejar identificación"
                        />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label for="internal_notes"
                            >Notas internas (opcional)</Label
                        >
                        <Textarea
                            id="internal_notes"
                            v-model="form.internal_notes"
                            rows="2"
                            maxlength="2000"
                        />
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="4. Pago">
                <p class="mb-4 text-sm text-muted-foreground">
                    Tarifa vigente:
                    <Money
                        :value="policy.fee"
                        class="font-medium text-foreground"
                    />
                    (política v{{ policy.version }},
                    {{ policy.durationMinutes }} min).
                </p>
                <RadioGroup
                    v-model="form.payment_action"
                    class="gap-3"
                    aria-label="Forma de pago"
                >
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 has-[[data-state=checked]]:border-primary"
                    >
                        <RadioGroupItem value="link" class="mt-0.5" />
                        <span>
                            <span class="block text-sm font-medium"
                                >Enviar enlace de pago al cliente</span
                            >
                            <span class="block text-sm text-muted-foreground"
                                >El horario queda apartado {{ holdHours }} h; se
                                confirma al pagar con tarjeta.</span
                            >
                        </span>
                    </label>
                    <label
                        v-if="can.record"
                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 has-[[data-state=checked]]:border-primary"
                    >
                        <RadioGroupItem value="manual" class="mt-0.5" />
                        <span>
                            <span class="block text-sm font-medium"
                                >Registrar pago manual</span
                            >
                            <span class="block text-sm text-muted-foreground"
                                >Transferencia o efectivo ya recibido: la visita
                                queda agendada de inmediato.</span
                            >
                        </span>
                    </label>
                </RadioGroup>
                <InputError :message="form.errors.payment_action" />

                <div
                    v-if="form.payment_action === 'manual'"
                    class="mt-4 grid gap-4 md:grid-cols-2"
                >
                    <div class="space-y-2">
                        <Label for="method">Método</Label>
                        <Select v-model="form.method">
                            <SelectTrigger id="method" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="m in manualMethods"
                                    :key="m.value"
                                    :value="m.value"
                                    >{{ m.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.method" />
                    </div>
                    <div class="space-y-2">
                        <Label for="reference">Referencia</Label>
                        <Input
                            id="reference"
                            v-model="form.reference"
                            maxlength="120"
                            placeholder="Ej. SPEI 0045821"
                        />
                        <InputError :message="form.errors.reference" />
                    </div>
                </div>
            </SectionCard>

            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child
                    ><Link href="/admin/visitas">Cancelar</Link></Button
                >
                <Button
                    type="submit"
                    :disabled="form.processing || !form.date || !form.start"
                >
                    <Spinner v-if="form.processing" />
                    {{
                        form.payment_action === 'manual'
                            ? 'Agendar con pago registrado'
                            : 'Apartar y enviar enlace'
                    }}
                </Button>
            </div>
        </form>
    </PageContainer>
</template>
