<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CircleCheck,
    Loader2,
    MapPin,
    MessageCircle,
    ShieldCheck,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import DateSlotPicker from '@/components/ds/DateSlotPicker.vue';
import type { TimeSlot } from '@/components/ds/DateSlotPicker.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import ProgressSteps from '@/components/ds/ProgressSteps.vue';
import type { ProgressStep } from '@/components/ds/ProgressSteps.vue';
import InputError from '@/components/InputError.vue';
import { uuid } from '@/components/payments/openpay';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import VisitSummaryCard from '@/components/visits/VisitSummaryCard.vue';
import { formatIsoDate } from '@/lib/dates';
import { formatMoney } from '@/lib/format';
import { apiFetch } from '@/lib/http';

type Policy = {
    version: number;
    fee: string;
    isCreditable: boolean;
    creditAmount: string;
    creditValidityDays: number | null;
    creditConditions: string | null;
    durationMinutes: number;
    holdMinutes: number;
    minNoticeHours: number;
    bookingWindowDays: number;
    scope: string;
    cancellationPolicy: string;
    reschedulePolicy: string;
};

type Prefill = {
    leadToken: string;
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
        municipality: string;
        state: string;
        references: string | null;
    } | null;
};

const props = defineProps<{
    policy: Policy | null;
    prefill: Prefill | null;
    urls: { coverage: string; slots: string; store: string; contact: string };
}>();

type StepKey = 'cp' | 'fecha' | 'datos' | 'revision' | 'pago';

const steps: ProgressStep[] = [
    { key: 'cp', title: 'Cobertura' },
    { key: 'fecha', title: 'Fecha y hora' },
    { key: 'datos', title: 'Domicilio' },
    { key: 'revision', title: 'Revisión' },
    { key: 'pago', title: 'Pago' },
];

const step = ref<StepKey>('cp');
const heading = ref<HTMLElement | null>(null);

async function go(next: StepKey) {
    step.value = next;
    await nextTick();
    heading.value?.focus();
    heading.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const form = useForm({
    idempotency_key: uuid(),
    lead_token: props.prefill?.leadToken ?? null,
    postal_code: props.prefill?.postalCode ?? '',
    date: null as string | null,
    start: null as string | null,
    first_name: props.prefill?.firstName ?? '',
    last_name: props.prefill?.lastName ?? '',
    email: props.prefill?.email ?? '',
    phone: props.prefill?.phone ?? '',
    street: props.prefill?.address?.street ?? '',
    exterior_number: props.prefill?.address?.exteriorNumber ?? '',
    interior_number: props.prefill?.address?.interiorNumber ?? '',
    neighborhood: props.prefill?.address?.neighborhood ?? '',
    municipality: props.prefill?.address?.municipality ?? '',
    state: props.prefill?.address?.state ?? '',
    references: props.prefill?.address?.references ?? '',
    access_notes: '',
    customer_notes: '',
    terms: false,
});

// ---------------------------------------------------------------- 1. Cobertura

type CoverageResponse = {
    covered: boolean;
    closed: boolean;
    message: string | null;
    zone: { name: string; municipality: string | null; state: string } | null;
    dates: string[];
};

const checking = ref(false);
const coverage = ref<CoverageResponse | null>(null);
const coverageError = ref<string | null>(null);

async function checkCoverage() {
    coverageError.value = null;
    const cp = form.postal_code.replace(/\D/g, '');

    if (cp.length !== 5) {
        coverageError.value = 'Escribe los 5 dígitos de tu código postal.';

        return;
    }

    form.postal_code = cp;
    checking.value = true;

    try {
        const response = await apiFetch(props.urls.coverage, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ postal_code: cp }),
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        coverage.value = (await response.json()) as CoverageResponse;

        if (coverage.value.covered && coverage.value.dates.length > 0) {
            if (form.date && !coverage.value.dates.includes(form.date)) {
                form.date = null;
                form.start = null;
            }

            await go('fecha');
        }
    } catch {
        coverageError.value =
            'No pudimos revisar la cobertura. Inténtalo de nuevo en un momento.';
    } finally {
        checking.value = false;
    }
}

// ---------------------------------------------------------------- 2. Fecha y hora

const slots = ref<TimeSlot[]>([]);
const loadingSlots = ref(false);
const slotsError = ref<string | null>(null);
const availableDates = computed(() => coverage.value?.dates ?? []);

async function loadSlots(date: string | null) {
    slots.value = [];
    slotsError.value = null;

    if (!date) {
        return;
    }

    loadingSlots.value = true;

    try {
        const url = `${props.urls.slots}?${new URLSearchParams({ cp: form.postal_code, fecha: date }).toString()}`;
        const response = await apiFetch(url);

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const data = (await response.json()) as {
            slots: { start: string; end: string }[];
        };
        slots.value = data.slots.map((s) => ({
            value: s.start,
            label: `${s.start} – ${s.end}`,
        }));
    } catch {
        slotsError.value =
            'No pudimos cargar los horarios. Elige el día otra vez.';
    } finally {
        loadingSlots.value = false;
    }
}

watch(
    () => form.date,
    (date) => loadSlots(date),
);

const slotLabel = computed(
    () => slots.value.find((s) => s.value === form.start)?.label ?? null,
);
const whenText = computed(() => {
    if (!form.date || !form.start) {
        return null;
    }

    const day = formatIsoDate(form.date, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    return `${day}, ${slotLabel.value ?? form.start} h`;
});

// ---------------------------------------------------------------- 3. Datos

const REQUIRED: (keyof typeof form)[] = [
    'first_name',
    'email',
    'phone',
    'street',
    'exterior_number',
    'neighborhood',
    'municipality',
    'state',
];
const localErrors = ref<Record<string, string>>({});

function validateDetails(): boolean {
    const errors: Record<string, string> = {};

    for (const field of REQUIRED) {
        if (!String(form[field] ?? '').trim()) {
            errors[field as string] = 'Este dato es necesario.';
        }
    }

    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        errors.email = 'Revisa tu correo electrónico.';
    }

    if (form.phone && form.phone.replace(/\D/g, '').length < 10) {
        errors.phone = 'Escribe un teléfono de 10 dígitos.';
    }

    localErrors.value = errors;

    return Object.keys(errors).length === 0;
}

function err(field: string): string | undefined {
    return (
        localErrors.value[field] ??
        (form.errors as Record<string, string | undefined>)[field]
    );
}

const addressLine = computed(() => {
    if (!form.street) {
        return null;
    }

    const number = [
        form.exterior_number,
        form.interior_number ? `int. ${form.interior_number}` : '',
    ]
        .filter(Boolean)
        .join(' ');

    return `${form.street} ${number}, ${form.neighborhood}, C.P. ${form.postal_code}, ${form.municipality}`;
});

// ---------------------------------------------------------------- 4/5. Revisión y pago

function submit() {
    if (form.processing) {
        return;
    }

    form.post(props.urls.store, {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.start || errors.date) {
                form.start = null;
                loadSlots(form.date);
                void go('fecha');
            } else if (errors.postal_code) {
                coverage.value = null;
                void go('cp');
            } else if (
                Object.keys(errors).some((k) => !['form', 'terms'].includes(k))
            ) {
                void go('datos');
            }
        },
    });
}

const currentStep = computed(() => step.value);
const formError = computed(
    () => (form.errors as Record<string, string | undefined>).form ?? null,
);
</script>

<template>
    <Head title="Agenda tu visita técnica" />

    <section class="motion-calm bg-background pb-20">
        <div class="container-site pt-10 md:pt-16">
            <div class="max-w-2xl" data-motion="fade-up">
                <p v-if="prefill" class="text-sm font-medium text-oak-ink">
                    Solicitud {{ prefill.leadFolio }}
                </p>
                <p v-else class="text-sm font-medium text-oak-ink">
                    Visita técnica a domicilio
                </p>
                <h1 class="mt-2 text-display-md font-display tracking-tight">
                    {{
                        prefill
                            ? `${prefill.firstName}, agenda tu visita técnica`
                            : 'Agenda tu visita técnica'
                    }}
                </h1>
                <p class="mt-3 text-lg text-muted-foreground">
                    Un técnico de ML Habitat mide tu espacio, revisa
                    instalaciones y te orienta para que tu diseño quede exacto.
                </p>
            </div>

            <!-- Sin política activa: el checkout queda cerrado. -->
            <div
                v-if="!policy"
                class="mt-10 max-w-2xl rounded-xl border bg-card p-6 shadow-xs"
            >
                <div class="flex gap-3">
                    <MessageCircle
                        class="mt-0.5 size-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <h2 class="font-medium">
                            Por ahora agenda tu visita con un asesor
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Déjanos tus datos y un asesor te contacta para
                            coordinar la fecha de tu visita técnica.
                        </p>
                        <Button as-child class="mt-4 h-11">
                            <Link :href="urls.contact">Solicitar asesoría</Link>
                        </Button>
                    </div>
                </div>
            </div>

            <template v-else>
                <ProgressSteps
                    :steps="steps"
                    :current="currentStep"
                    label="Pasos para agendar"
                    orientation="horizontal"
                    class="mt-8 hidden max-w-3xl sm:flex"
                />
                <p class="mt-6 text-sm text-muted-foreground sm:hidden">
                    Paso {{ steps.findIndex((s) => s.key === step) + 1 }} de
                    {{ steps.length }} ·
                    {{ steps.find((s) => s.key === step)?.title }}
                </p>

                <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <div class="min-w-0">
                        <FormStatus
                            v-if="formError"
                            tone="danger"
                            :message="formError"
                            class="mb-6"
                        />

                        <!-- 1. Código postal -->
                        <section
                            v-if="step === 'cp'"
                            aria-labelledby="step-title"
                            class="step-panel space-y-5"
                        >
                            <h2
                                id="step-title"
                                ref="heading"
                                tabindex="-1"
                                class="font-display text-xl font-semibold tracking-tight outline-none"
                            >
                                ¿Dónde será la visita?
                            </h2>
                            <form
                                class="max-w-sm space-y-2"
                                novalidate
                                @submit.prevent="checkCoverage"
                            >
                                <Label for="postal_code">Código postal</Label>
                                <div class="flex gap-2">
                                    <Input
                                        id="postal_code"
                                        v-model="form.postal_code"
                                        inputmode="numeric"
                                        autocomplete="postal-code"
                                        maxlength="5"
                                        placeholder="06700"
                                        class="h-12 font-mono text-base tracking-widest"
                                        :aria-invalid="
                                            !!(
                                                coverageError ||
                                                form.errors.postal_code
                                            )
                                        "
                                        aria-describedby="postal_code-error"
                                    />
                                    <Button
                                        type="submit"
                                        class="h-12 shrink-0"
                                        :disabled="checking"
                                    >
                                        <Loader2
                                            v-if="checking"
                                            class="animate-spin"
                                            aria-hidden="true"
                                        />
                                        {{
                                            checking
                                                ? 'Revisando…'
                                                : 'Continuar'
                                        }}
                                    </Button>
                                </div>
                                <InputError
                                    id="postal_code-error"
                                    :message="
                                        coverageError ?? form.errors.postal_code
                                    "
                                />
                            </form>

                            <div
                                v-if="coverage && !coverage.covered"
                                class="max-w-xl rounded-xl border bg-card p-5"
                                role="status"
                            >
                                <div class="flex gap-3">
                                    <MapPin
                                        class="mt-0.5 size-5 shrink-0 text-oak-ink"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <p class="font-medium">
                                            {{
                                                coverage.closed
                                                    ? 'Por ahora agenda tu visita con un asesor'
                                                    : 'Aún no llegamos a tu zona'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{ coverage.message }}
                                        </p>
                                        <Button
                                            as-child
                                            variant="outline"
                                            class="mt-4"
                                        >
                                            <Link :href="urls.contact"
                                                >Hablar con un asesor</Link
                                            >
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else-if="
                                    coverage &&
                                    coverage.covered &&
                                    coverage.dates.length === 0
                                "
                                class="max-w-xl rounded-xl border bg-card p-5"
                                role="status"
                            >
                                <p class="font-medium">
                                    Sí damos visitas en
                                    {{ coverage.zone?.name }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ coverage.message }}
                                </p>
                                <Button as-child variant="outline" class="mt-4">
                                    <Link :href="urls.contact"
                                        >Pedir que me contacten</Link
                                    >
                                </Button>
                            </div>
                        </section>

                        <!-- 2. Fecha y horario -->
                        <section
                            v-else-if="step === 'fecha'"
                            aria-labelledby="step-title"
                            class="step-panel space-y-5"
                        >
                            <div>
                                <h2
                                    id="step-title"
                                    ref="heading"
                                    tabindex="-1"
                                    class="font-display text-xl font-semibold tracking-tight outline-none"
                                >
                                    Elige día y horario
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Zona {{ coverage?.zone?.name }} · C.P.
                                    {{ form.postal_code }}. El técnico llega
                                    dentro de la ventana que elijas.
                                </p>
                            </div>

                            <DateSlotPicker
                                v-model:date="form.date"
                                v-model:slot="form.start"
                                :slots="slots"
                                :loading="loadingSlots"
                                :min="availableDates[0]"
                                :max="availableDates[availableDates.length - 1]"
                                :is-date-disabled="
                                    (iso: string) =>
                                        !availableDates.includes(iso)
                                "
                                empty-text="Ya no quedan horarios este día. Prueba con otra fecha."
                            />
                            <InputError
                                :message="
                                    slotsError ??
                                    form.errors.start ??
                                    form.errors.date
                                "
                            />

                            <div
                                class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="h-11"
                                    @click="go('cp')"
                                >
                                    <ArrowLeft aria-hidden="true" /> Cambiar
                                    código postal
                                </Button>
                                <Button
                                    type="button"
                                    class="h-11"
                                    :disabled="!form.date || !form.start"
                                    @click="go('datos')"
                                >
                                    Continuar <ArrowRight aria-hidden="true" />
                                </Button>
                            </div>
                        </section>

                        <!-- 3. Domicilio y contacto -->
                        <section
                            v-else-if="step === 'datos'"
                            aria-labelledby="step-title"
                            class="step-panel space-y-6"
                        >
                            <h2
                                id="step-title"
                                ref="heading"
                                tabindex="-1"
                                class="font-display text-xl font-semibold tracking-tight outline-none"
                            >
                                Domicilio y contacto
                            </h2>

                            <form
                                class="space-y-6"
                                novalidate
                                @submit.prevent="
                                    validateDetails() && go('revision')
                                "
                            >
                                <fieldset class="grid gap-4 sm:grid-cols-6">
                                    <legend class="sr-only">
                                        Domicilio de la visita
                                    </legend>
                                    <div class="space-y-2 sm:col-span-6">
                                        <Label for="street">Calle</Label>
                                        <Input
                                            id="street"
                                            v-model="form.street"
                                            autocomplete="address-line1"
                                            class="h-11"
                                            :aria-invalid="!!err('street')"
                                        />
                                        <InputError :message="err('street')" />
                                    </div>
                                    <div class="space-y-2 sm:col-span-3">
                                        <Label for="exterior_number"
                                            >Número exterior</Label
                                        >
                                        <Input
                                            id="exterior_number"
                                            v-model="form.exterior_number"
                                            class="h-11"
                                            :aria-invalid="
                                                !!err('exterior_number')
                                            "
                                        />
                                        <InputError
                                            :message="err('exterior_number')"
                                        />
                                    </div>
                                    <div class="space-y-2 sm:col-span-3">
                                        <Label for="interior_number"
                                            >Interior
                                            <span
                                                class="font-normal text-muted-foreground"
                                                >(opcional)</span
                                            ></Label
                                        >
                                        <Input
                                            id="interior_number"
                                            v-model="form.interior_number"
                                            autocomplete="address-line2"
                                            class="h-11"
                                        />
                                    </div>
                                    <div class="space-y-2 sm:col-span-6">
                                        <Label for="neighborhood"
                                            >Colonia</Label
                                        >
                                        <Input
                                            id="neighborhood"
                                            v-model="form.neighborhood"
                                            class="h-11"
                                            :aria-invalid="
                                                !!err('neighborhood')
                                            "
                                        />
                                        <InputError
                                            :message="err('neighborhood')"
                                        />
                                    </div>
                                    <div class="space-y-2 sm:col-span-3">
                                        <Label for="municipality"
                                            >Municipio o alcaldía</Label
                                        >
                                        <Input
                                            id="municipality"
                                            v-model="form.municipality"
                                            autocomplete="address-level2"
                                            class="h-11"
                                            :aria-invalid="
                                                !!err('municipality')
                                            "
                                        />
                                        <InputError
                                            :message="err('municipality')"
                                        />
                                    </div>
                                    <div class="space-y-2 sm:col-span-3">
                                        <Label for="state">Estado</Label>
                                        <Input
                                            id="state"
                                            v-model="form.state"
                                            autocomplete="address-level1"
                                            class="h-11"
                                            :aria-invalid="!!err('state')"
                                        />
                                        <InputError :message="err('state')" />
                                    </div>
                                    <div class="space-y-2 sm:col-span-6">
                                        <Label for="references"
                                            >Referencias
                                            <span
                                                class="font-normal text-muted-foreground"
                                                >(opcional)</span
                                            ></Label
                                        >
                                        <Input
                                            id="references"
                                            v-model="form.references"
                                            placeholder="Ej. casa blanca con portón negro"
                                            class="h-11"
                                        />
                                    </div>
                                    <div class="space-y-2 sm:col-span-6">
                                        <Label for="access_notes"
                                            >Indicaciones de acceso
                                            <span
                                                class="font-normal text-muted-foreground"
                                                >(opcional)</span
                                            ></Label
                                        >
                                        <Textarea
                                            id="access_notes"
                                            v-model="form.access_notes"
                                            rows="2"
                                            maxlength="1000"
                                            placeholder="Ej. avisar en caseta, estacionamiento de visitas, piso 4 sin elevador"
                                        />
                                    </div>
                                </fieldset>

                                <fieldset
                                    class="grid gap-4 border-t pt-6 sm:grid-cols-2"
                                >
                                    <legend class="mb-2 text-sm font-medium">
                                        ¿Con quién nos comunicamos?
                                    </legend>
                                    <div class="space-y-2">
                                        <Label for="first_name">Nombre</Label>
                                        <Input
                                            id="first_name"
                                            v-model="form.first_name"
                                            autocomplete="given-name"
                                            class="h-11"
                                            :aria-invalid="!!err('first_name')"
                                        />
                                        <InputError
                                            :message="err('first_name')"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="last_name"
                                            >Apellidos
                                            <span
                                                class="font-normal text-muted-foreground"
                                                >(opcional)</span
                                            ></Label
                                        >
                                        <Input
                                            id="last_name"
                                            v-model="form.last_name"
                                            autocomplete="family-name"
                                            class="h-11"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="email"
                                            >Correo electrónico</Label
                                        >
                                        <Input
                                            id="email"
                                            v-model="form.email"
                                            type="email"
                                            autocomplete="email"
                                            class="h-11"
                                            :aria-invalid="!!err('email')"
                                            aria-describedby="email-help"
                                        />
                                        <p
                                            id="email-help"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Aquí te enviamos la confirmación y
                                            el comprobante.
                                        </p>
                                        <InputError :message="err('email')" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="phone"
                                            >Teléfono celular</Label
                                        >
                                        <Input
                                            id="phone"
                                            v-model="form.phone"
                                            type="tel"
                                            inputmode="tel"
                                            autocomplete="tel-national"
                                            placeholder="55 1234 5678"
                                            class="h-11"
                                            :aria-invalid="!!err('phone')"
                                        />
                                        <InputError :message="err('phone')" />
                                    </div>
                                    <div class="space-y-2 sm:col-span-2">
                                        <Label for="customer_notes"
                                            >¿Qué quieres que revisemos?
                                            <span
                                                class="font-normal text-muted-foreground"
                                                >(opcional)</span
                                            ></Label
                                        >
                                        <Textarea
                                            id="customer_notes"
                                            v-model="form.customer_notes"
                                            rows="2"
                                            maxlength="1000"
                                            placeholder="Ej. cocina en L y clóset de la recámara principal"
                                        />
                                    </div>
                                </fieldset>

                                <div
                                    class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between"
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        class="h-11"
                                        @click="go('fecha')"
                                    >
                                        <ArrowLeft aria-hidden="true" /> Cambiar
                                        fecha
                                    </Button>
                                    <Button type="submit" class="h-11"
                                        >Revisar y continuar
                                        <ArrowRight aria-hidden="true"
                                    /></Button>
                                </div>
                            </form>
                        </section>

                        <!-- 4. Revisión -->
                        <section
                            v-else
                            aria-labelledby="step-title"
                            class="step-panel space-y-6"
                        >
                            <h2
                                id="step-title"
                                ref="heading"
                                tabindex="-1"
                                class="font-display text-xl font-semibold tracking-tight outline-none"
                            >
                                Revisa y confirma
                            </h2>

                            <div class="divide-y rounded-xl border bg-card">
                                <div class="p-5">
                                    <h3 class="text-sm font-medium">
                                        Qué incluye la visita
                                    </h3>
                                    <p
                                        class="mt-2 text-sm whitespace-pre-line text-muted-foreground"
                                    >
                                        {{ policy.scope }}
                                    </p>
                                </div>
                                <div class="grid gap-5 p-5 sm:grid-cols-2">
                                    <div>
                                        <h3 class="text-sm font-medium">
                                            Cancelación
                                        </h3>
                                        <p
                                            class="mt-2 text-sm whitespace-pre-line text-muted-foreground"
                                        >
                                            {{ policy.cancellationPolicy }}
                                        </p>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium">
                                            Reprogramación
                                        </h3>
                                        <p
                                            class="mt-2 text-sm whitespace-pre-line text-muted-foreground"
                                        >
                                            {{ policy.reschedulePolicy }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-if="
                                        policy.isCreditable &&
                                        Number(policy.creditAmount) > 0
                                    "
                                    class="p-5"
                                >
                                    <h3 class="text-sm font-medium">
                                        Abono a tu proyecto
                                    </h3>
                                    <p
                                        class="mt-2 text-sm text-muted-foreground"
                                    >
                                        Si contratas tu proyecto, abonaremos
                                        {{ formatMoney(policy.creditAmount) }} a
                                        tu anticipo<template
                                            v-if="policy.creditValidityDays"
                                            >, vigente
                                            {{ policy.creditValidityDays }} días
                                            a partir del pago</template
                                        >.
                                        <template
                                            v-if="policy.creditConditions"
                                        >
                                            {{
                                                policy.creditConditions
                                            }}</template
                                        >
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex items-start gap-3 rounded-xl border bg-card p-4"
                            >
                                <Checkbox
                                    id="terms"
                                    v-model="form.terms"
                                    class="mt-0.5"
                                    :aria-invalid="!!form.errors.terms"
                                    aria-describedby="terms-error"
                                />
                                <div>
                                    <Label
                                        for="terms"
                                        class="leading-snug font-normal"
                                    >
                                        Acepto el alcance de la visita y las
                                        condiciones de cancelación y
                                        reprogramación, y autorizo el uso de mis
                                        datos para agendarla.
                                    </Label>
                                    <InputError
                                        id="terms-error"
                                        :message="form.errors.terms"
                                        class="mt-1"
                                    />
                                </div>
                            </div>

                            <p class="flex gap-2 text-sm text-muted-foreground">
                                <ShieldCheck
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                Al continuar apartamos tu horario por
                                {{ policy.holdMinutes }} minutos mientras
                                completas el pago con tarjeta.
                            </p>

                            <div
                                class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="h-11"
                                    :disabled="form.processing"
                                    @click="go('datos')"
                                >
                                    <ArrowLeft aria-hidden="true" /> Editar
                                    datos
                                </Button>
                                <Button
                                    type="button"
                                    class="h-12 sm:min-w-56"
                                    :disabled="form.processing || !form.terms"
                                    @click="submit"
                                >
                                    <Loader2
                                        v-if="form.processing"
                                        class="animate-spin"
                                        aria-hidden="true"
                                    />
                                    {{
                                        form.processing
                                            ? 'Apartando tu horario…'
                                            : 'Continuar al pago'
                                    }}
                                    <ArrowRight
                                        v-if="!form.processing"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>
                        </section>
                    </div>

                    <aside class="lg:sticky lg:top-24 lg:self-start">
                        <VisitSummaryCard
                            :when="whenText"
                            :duration-minutes="policy.durationMinutes"
                            :address="step === 'revision' ? addressLine : null"
                            :fee="policy.fee"
                            :is-creditable="
                                policy.isCreditable &&
                                Number(policy.creditAmount) > 0
                            "
                            :credit-amount="policy.creditAmount"
                            :credit-validity-days="policy.creditValidityDays"
                        >
                            <ul
                                class="mt-5 space-y-2 border-t pt-4 text-xs text-muted-foreground"
                            >
                                <li class="flex gap-2">
                                    <CircleCheck
                                        class="size-3.5 shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    Pago seguro con tarjeta (Openpay)
                                </li>
                                <li class="flex gap-2">
                                    <CircleCheck
                                        class="size-3.5 shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    Confirmación y comprobante por correo
                                </li>
                                <li class="flex gap-2">
                                    <CircleCheck
                                        class="size-3.5 shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    Puedes reprogramar según las condiciones de
                                    la visita
                                </li>
                            </ul>
                        </VisitSummaryCard>
                    </aside>
                </div>
            </template>
        </div>
    </section>
</template>
