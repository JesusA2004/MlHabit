<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CircleCheck,
    Clock,
    Loader2,
    MapPin,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import ChoiceCards from '@/components/crm/ChoiceCards.vue';
import { categoryIcon, ICON_STROKE } from '@/components/web/visual/icons';
import { illustrationFor } from '@/components/web/visual/illustrations';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import FilePicker from '@/components/crm/FilePicker.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import ProgressSteps from '@/components/ds/ProgressSteps.vue';
import type { ProgressStep } from '@/components/ds/ProgressSteps.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { apiFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

type Option = { value: string; label: string };
type Category = {
    id: number;
    name: string;
    slug: string;
    tagline: string | null;
    coverUrl: string | null;
};
type Coverage = {
    valid: boolean;
    covered: boolean;
    visitsEnabled: boolean;
    zone: { name: string; municipality: string | null; state: string } | null;
    message: string;
};

const props = defineProps<{
    categories: Category[];
    preselected: number[];
    options: {
        budgetRanges: Option[];
        paymentPreferences: Option[];
        timeframes: Option[];
        preferredContacts: Option[];
    };
    attribution: Record<string, string | null>;
    limits: { maxPhotos: number; maxPhotoMb: number; photoTypes: string[] };
    privacyUrl: string;
    coverageUrl: string;
}>();

const steps: ProgressStep[] = [
    { key: 'espacio', title: 'Tu espacio' },
    { key: 'ubicacion', title: 'Ubicación' },
    { key: 'presupuesto', title: 'Presupuesto' },
    { key: 'contacto', title: 'Contacto' },
    { key: 'detalles', title: 'Detalles' },
];

const FIELD_STEP: Record<string, number> = {
    category_ids: 0,
    postal_code: 1,
    city: 1,
    state: 1,
    budget_range: 2,
    payment_preference: 2,
    timeframe: 2,
    first_name: 3,
    last_name: 3,
    phone: 3,
    email: 3,
    preferred_contact: 3,
    photos: 4,
    measurements_note: 4,
    comments: 4,
    privacy: 4,
};

const ATTRIBUTION_KEYS = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_term',
    'utm_content',
    'referrer',
    'landing_url',
] as const;

function newKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
              const r = (Math.random() * 16) | 0;

              return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
          });
}

const form = useForm({
    submission_key: newKey(),
    website: '',
    category_ids: [...props.preselected] as number[],
    postal_code: '',
    city: '',
    state: '',
    budget_range: null as string | null,
    payment_preference: null as string | null,
    timeframe: null as string | null,
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    preferred_contact: 'whatsapp' as string | null,
    photos: [] as File[],
    measurements_note: '',
    comments: '',
    privacy: false,
    ...Object.fromEntries(
        ATTRIBUTION_KEYS.map((k) => [k, props.attribution[k] ?? '']),
    ),
});

const current = ref(props.preselected.length > 0 ? 1 : 0);
/** Sentido de la última transición entre pasos (anima a izquierda/derecha). */
const direction = ref<'forward' | 'back'>('forward');
const progressRatio = computed(() => (current.value + 1) / steps.length);
const clientErrors = ref<Record<string, string>>({});
const stepHeading = ref<HTMLElement | null>(null);

const allErrors = computed<Record<string, string>>(() => ({
    ...(form.errors as Record<string, string>),
    ...clientErrors.value,
}));

function error(field: string): string | undefined {
    if (allErrors.value[field]) {
        return allErrors.value[field];
    }

    const nested = Object.entries(allErrors.value).find(([k]) =>
        k.startsWith(`${field}.`),
    );

    return nested?.[1];
}

/* ── Paso 1: categorías ─────────────────────────────────────────── */
function toggleCategory(id: number) {
    form.category_ids = form.category_ids.includes(id)
        ? form.category_ids.filter((c) => c !== id)
        : [...form.category_ids, id];
    delete clientErrors.value.category_ids;
}

const selectedNames = computed(() =>
    props.categories
        .filter((c) => form.category_ids.includes(c.id))
        .map((c) => c.name),
);

/* ── Paso 2: cobertura en vivo ──────────────────────────────────── */
const coverage = ref<Coverage | null>(null);
const coverageState = ref<'idle' | 'loading' | 'done' | 'error'>('idle');

const checkCoverage = useDebounceFn(async (cp: string) => {
    coverageState.value = 'loading';

    try {
        const response = await apiFetch(
            `${props.coverageUrl}?cp=${encodeURIComponent(cp)}`,
        );
        const data = (await response.json()) as Coverage;

        if (form.postal_code !== cp) {
            return;
        }

        coverage.value = data;
        coverageState.value = 'done';

        if (data.zone) {
            form.state ||= data.zone.state;
            form.city ||= data.zone.municipality ?? '';
        }
    } catch {
        coverageState.value = 'error';
    }
}, 350);

watch(
    () => form.postal_code,
    (value) => {
        const digits = value.replace(/\D/g, '').slice(0, 5);

        if (digits !== value) {
            form.postal_code = digits;

            return;
        }

        delete clientErrors.value.postal_code;
        coverage.value = null;

        if (digits.length === 5) {
            void checkCoverage(digits);
        } else {
            coverageState.value = 'idle';
        }
    },
);

/* ── Validación por paso ────────────────────────────────────────── */
function phoneDigits(): string {
    let digits = form.phone.replace(/\D/g, '');

    if (digits.length === 12 && digits.startsWith('52')) {
        digits = digits.slice(2);
    }

    return digits;
}

function validate(step: number): boolean {
    const errors: Record<string, string> = {};

    if (step === 0 && form.category_ids.length === 0) {
        errors.category_ids = 'Elige al menos un espacio que quieras renovar.';
    }

    if (step === 1 && !/^\d{5}$/.test(form.postal_code)) {
        errors.postal_code = 'Escribe tu código postal de 5 dígitos.';
    }

    if (step === 3) {
        if (!form.first_name.trim()) {
            errors.first_name = 'Escribe tu nombre.';
        }

        if (phoneDigits().length !== 10) {
            errors.phone =
                'Escribe tu teléfono a 10 dígitos, sin lada internacional.';
        }

        if (!form.preferred_contact) {
            errors.preferred_contact =
                'Elige cómo prefieres que te contactemos.';
        }

        if (form.preferred_contact === 'email' && !form.email.trim()) {
            errors.email = 'Escribe tu correo para contactarte por ese medio.';
        } else if (
            form.email.trim() &&
            !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())
        ) {
            errors.email = 'Revisa tu correo; parece incompleto.';
        }
    }

    if (step === 4 && !form.privacy) {
        errors.privacy = 'Para continuar, acepta el aviso de privacidad.';
    }

    for (const key of Object.keys(clientErrors.value)) {
        if (FIELD_STEP[key.split('.')[0]] === step) {
            delete clientErrors.value[key];
        }
    }

    Object.assign(clientErrors.value, errors);

    if (Object.keys(errors).length > 0) {
        void focusFirstError();

        return false;
    }

    return true;
}

async function focusStep() {
    await nextTick();
    stepHeading.value?.focus();
    stepHeading.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function focusFirstError() {
    await nextTick();
    document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
}

function next() {
    if (!validate(current.value)) {
        return;
    }

    direction.value = 'forward';
    current.value = Math.min(current.value + 1, steps.length - 1);
    void focusStep();
}

function back() {
    direction.value = 'back';
    current.value = Math.max(current.value - 1, 0);
    void focusStep();
}

function goTo(index: number) {
    if (index < current.value) {
        direction.value = 'back';
        current.value = index;
        void focusStep();
    }
}

function submit() {
    for (let i = 0; i < steps.length; i++) {
        if (!validate(i)) {
            current.value = i;

            return;
        }
    }

    clientErrors.value = {};
    form.transform((data) => ({
        ...data,
        phone: phoneDigits(),
        email: data.email.trim(),
    })).post('/solicitar', {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
            const steps = Object.keys(errors)
                .map((k) => FIELD_STEP[k.split('.')[0]])
                .filter((s): s is number => s !== undefined);

            if (steps.length > 0) {
                current.value = Math.min(...steps);
                void focusStep();
            }

            if (errors.submission_key) {
                form.submission_key = newKey();
            }
        },
    });
}

const isLast = computed(() => current.value === steps.length - 1);

const stepCopy = [
    {
        title: '¿Qué espacio quieres renovar?',
        lead: 'Puedes elegir más de uno. Así preparamos ideas y rangos de precio para tu proyecto.',
    },
    {
        title: '¿Dónde está tu proyecto?',
        lead: 'Con tu código postal revisamos si podemos visitarte para tomar medidas.',
    },
    {
        title: 'Tu presupuesto y tiempos',
        lead: 'Es solo orientativo, no es un compromiso. Nos ayuda a proponerte opciones realistas.',
    },
    {
        title: '¿Cómo te contactamos?',
        lead: 'Un asesor te escribirá en horario laboral por el medio que prefieras.',
    },
    {
        title: 'Cuéntanos un poco más',
        lead: 'Opcional, pero muy útil: unas fotos del espacio y medidas aproximadas aceleran tu propuesta.',
    },
];

const contactOptions = computed(() =>
    props.options.preferredContacts.map((o) => ({
        ...o,
        description:
            (
                {
                    whatsapp: 'Mensajes y fotos por WhatsApp.',
                    phone: 'Te llamamos al número que registres.',
                    email: 'Te escribimos con la información.',
                } as Record<string, string>
            )[o.value] ?? null,
    })),
);
</script>

<template>
    <Head title="Solicita tu propuesta">
        <meta
            name="description"
            content="Cuéntanos qué espacio quieres renovar y un asesor de ML Habitat te contacta para diseñar tu cocina, clóset o mueble a la medida."
        />
    </Head>

    <section
        class="motion-calm relative isolate overflow-hidden bg-background pb-32 md:pb-20"
    >
        <div
            class="tex-blueprint pointer-events-none absolute inset-x-0 top-0 -z-10 h-80 [mask-image:linear-gradient(to_bottom,#000,transparent)]"
            aria-hidden="true"
        />
        <div class="container-site pt-10 md:pt-16">
            <div class="flex items-end justify-between gap-8">
                <div class="max-w-2xl" data-motion="fade-up">
                    <p class="text-sm font-medium text-oak-ink">
                        Solicitud sin compromiso
                    </p>
                    <h1
                        class="mt-2 text-display-md font-display tracking-tight text-foreground"
                    >
                        Diseñemos un espacio hecho a tu medida
                    </h1>
                    <p class="mt-3 text-lg text-muted-foreground">
                        Responde unas preguntas rápidas (unos 2 minutos). Un
                        asesor revisará tu solicitud y te contactará.
                    </p>
                </div>
                <LineIllustration
                    name="blueprint"
                    class="hidden w-44 shrink-0 text-foreground/70 lg:block"
                />
            </div>

            <ProgressSteps
                class="mt-8 hidden max-w-3xl sm:block"
                :steps="steps"
                :current="steps[current].key"
                orientation="horizontal"
                label="Avance de tu solicitud"
            />

            <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <form
                    class="relative overflow-hidden rounded-xl border bg-card p-5 shadow-xs sm:p-8"
                    novalidate
                    :data-direction="direction"
                    @submit.prevent="isLast ? submit() : next()"
                >
                    <span
                        class="absolute inset-x-0 top-0 h-1 bg-muted"
                        aria-hidden="true"
                    >
                        <span
                            class="block h-full origin-left bg-primary transition-transform duration-700 ease-out"
                            :style="{ transform: `scaleX(${progressRatio})` }"
                        />
                    </span>
                    <!-- Honeypot: invisible para personas. -->
                    <div
                        class="absolute -left-[9999px] h-px w-px overflow-hidden"
                        aria-hidden="true"
                    >
                        <label for="website">No llenes este campo</label>
                        <input
                            id="website"
                            v-model="form.website"
                            type="text"
                            name="website"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>

                    <FormStatus
                        v-if="allErrors.form"
                        tone="danger"
                        :message="allErrors.form"
                        class="mb-6"
                    />

                    <header class="mb-6">
                        <p
                            class="text-xs font-medium text-muted-foreground tabular-nums"
                        >
                            Paso {{ current + 1 }} de {{ steps.length }}
                        </p>
                        <h2
                            ref="stepHeading"
                            tabindex="-1"
                            class="mt-1 scroll-mt-28 font-display text-2xl font-semibold tracking-tight outline-none"
                        >
                            {{ stepCopy[current].title }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ stepCopy[current].lead }}
                        </p>
                    </header>

                    <!-- 1. Espacio -->
                    <fieldset
                        v-show="current === 0"
                        class="step-panel"
                        aria-describedby="category_ids-error"
                    >
                        <legend class="sr-only">
                            Espacios que quieres renovar
                        </legend>
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                            <button
                                v-for="category in categories"
                                :key="category.id"
                                type="button"
                                :aria-pressed="
                                    form.category_ids.includes(category.id)
                                "
                                :aria-invalid="
                                    !!error('category_ids') || undefined
                                "
                                :class="
                                    cn(
                                        'group relative flex flex-col overflow-hidden rounded-xl border bg-card text-left transition-[border-color,box-shadow,translate] duration-300 ease-out',
                                        'hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none',
                                        form.category_ids.includes(category.id)
                                            ? 'border-primary shadow-sm ring-1 ring-primary'
                                            : 'hover:border-primary/40',
                                    )
                                "
                                @click="toggleCategory(category.id)"
                            >
                                <span
                                    class="relative block aspect-[4/3] overflow-hidden bg-linen"
                                >
                                    <img
                                        v-if="category.coverUrl"
                                        :src="category.coverUrl"
                                        :alt="category.name"
                                        class="size-full object-cover transition-[scale] duration-700 ease-out group-hover:scale-[1.06]"
                                        loading="lazy"
                                    />
                                    <span
                                        v-else
                                        class="tex-blueprint flex size-full items-center justify-center p-4"
                                    >
                                        <LineIllustration
                                            :name="
                                                illustrationFor(
                                                    category.slug,
                                                    category.name,
                                                )
                                            "
                                            class="w-full text-foreground/70"
                                        />
                                    </span>
                                    <span
                                        :class="
                                            cn(
                                                'absolute inset-0 bg-primary/0 transition-colors duration-300',
                                                form.category_ids.includes(
                                                    category.id,
                                                ) && 'bg-primary/15',
                                            )
                                        "
                                        aria-hidden="true"
                                    />
                                    <span
                                        v-if="
                                            form.category_ids.includes(
                                                category.id,
                                            )
                                        "
                                        class="check-in absolute top-2 right-2 flex size-7 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-md"
                                        aria-hidden="true"
                                    >
                                        <Check
                                            class="size-4"
                                            :stroke-width="2.5"
                                        />
                                    </span>
                                </span>
                                <span class="block px-3 py-3">
                                    <span
                                        class="flex items-center gap-2 text-sm font-medium text-foreground"
                                        ><component
                                            :is="
                                                categoryIcon(
                                                    category.slug,
                                                    category.name,
                                                )
                                            "
                                            :class="
                                                cn(
                                                    'size-4 shrink-0 transition-[color,rotate] duration-300 group-hover:-rotate-12',
                                                    form.category_ids.includes(
                                                        category.id,
                                                    )
                                                        ? 'text-primary'
                                                        : 'text-oak-ink',
                                                )
                                            "
                                            :stroke-width="ICON_STROKE"
                                            aria-hidden="true"
                                        />{{ category.name }}</span
                                    >
                                    <span
                                        v-if="category.tagline"
                                        class="mt-0.5 line-clamp-2 block text-xs text-muted-foreground"
                                        >{{ category.tagline }}</span
                                    >
                                </span>
                            </button>
                        </div>
                        <p
                            v-if="categories.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Aún no hay categorías publicadas. Cuéntanos tu idea
                            en el último paso.
                        </p>
                        <Transition
                            enter-active-class="transition duration-300 ease-out"
                            enter-from-class="opacity-0 translate-y-1"
                            leave-active-class="transition duration-150 ease-in"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-if="selectedNames.length"
                                class="mt-4 inline-flex items-center gap-2 rounded-full bg-primary-soft px-3 py-1.5 text-sm text-primary"
                                aria-live="polite"
                            >
                                <Check
                                    class="size-4"
                                    :stroke-width="2.25"
                                    aria-hidden="true"
                                />
                                {{ selectedNames.length }}
                                {{
                                    selectedNames.length === 1
                                        ? 'espacio elegido'
                                        : 'espacios elegidos'
                                }}
                            </p>
                        </Transition>
                        <InputError
                            id="category_ids-error"
                            class="mt-3"
                            :message="error('category_ids')"
                        />
                    </fieldset>

                    <!-- 2. Ubicación -->
                    <div v-show="current === 1" class="step-panel space-y-5">
                        <div class="max-w-xs space-y-2">
                            <Label for="postal_code">Código postal</Label>
                            <Input
                                id="postal_code"
                                v-model="form.postal_code"
                                inputmode="numeric"
                                autocomplete="postal-code"
                                maxlength="5"
                                placeholder="Ej. 06700"
                                class="h-11 text-base tabular-nums"
                                :aria-invalid="
                                    !!error('postal_code') || undefined
                                "
                                aria-describedby="postal_code-help postal_code-error"
                            />
                            <InputError
                                id="postal_code-error"
                                :message="error('postal_code')"
                            />
                        </div>

                        <div
                            id="postal_code-help"
                            aria-live="polite"
                            class="min-h-6"
                        >
                            <p
                                v-if="coverageState === 'loading'"
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <Loader2
                                    class="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                                Revisando cobertura…
                            </p>
                            <div
                                v-else-if="coverageState === 'done' && coverage"
                                :class="
                                    cn(
                                        'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm',
                                        coverage.covered
                                            ? 'border-success/25 bg-success-soft text-success-ink'
                                            : 'border-border bg-muted text-foreground',
                                    )
                                "
                            >
                                <component
                                    :is="
                                        coverage.covered ? CircleCheck : MapPin
                                    "
                                    class="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <p>{{ coverage.message }}</p>
                            </div>
                            <p
                                v-else-if="coverageState === 'error'"
                                class="text-sm text-muted-foreground"
                            >
                                No pudimos revisar la cobertura en este momento;
                                no te preocupes, la revisamos al recibir tu
                                solicitud.
                            </p>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="city"
                                    >Ciudad o municipio
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opcional)</span
                                    ></Label
                                >
                                <Input
                                    id="city"
                                    v-model="form.city"
                                    autocomplete="address-level2"
                                    class="h-11"
                                    :aria-invalid="!!error('city') || undefined"
                                />
                                <InputError :message="error('city')" />
                            </div>
                            <div class="space-y-2">
                                <Label for="state"
                                    >Estado
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opcional)</span
                                    ></Label
                                >
                                <Input
                                    id="state"
                                    v-model="form.state"
                                    autocomplete="address-level1"
                                    class="h-11"
                                    :aria-invalid="
                                        !!error('state') || undefined
                                    "
                                />
                                <InputError :message="error('state')" />
                            </div>
                        </div>
                    </div>

                    <!-- 3. Presupuesto -->
                    <div v-show="current === 2" class="step-panel space-y-7">
                        <div class="space-y-3">
                            <p id="budget-label" class="text-sm font-medium">
                                Presupuesto aproximado
                            </p>
                            <ChoiceCards
                                v-model="form.budget_range"
                                name="budget_range"
                                label="Presupuesto aproximado"
                                :options="options.budgetRanges"
                                :columns="2"
                            />
                            <InputError :message="error('budget_range')" />
                        </div>
                        <div class="space-y-3">
                            <p class="text-sm font-medium">
                                ¿Cómo te gustaría pagar?
                            </p>
                            <ChoiceCards
                                v-model="form.payment_preference"
                                name="payment_preference"
                                label="Forma de pago preferida"
                                :options="options.paymentPreferences"
                                :columns="3"
                            />
                            <p class="text-xs text-muted-foreground">
                                Si te interesa pagar a plazos, tu asesor te
                                explicará cómo funciona la precalificación.
                            </p>
                            <InputError
                                :message="error('payment_preference')"
                            />
                        </div>
                        <div class="space-y-3">
                            <p class="text-sm font-medium">
                                ¿Para cuándo lo necesitas?
                            </p>
                            <ChoiceCards
                                v-model="form.timeframe"
                                name="timeframe"
                                label="Plazo del proyecto"
                                :options="options.timeframes"
                                :columns="2"
                            />
                            <InputError :message="error('timeframe')" />
                        </div>
                    </div>

                    <!-- 4. Contacto -->
                    <div v-show="current === 3" class="step-panel space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="first_name">Nombre</Label>
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    autocomplete="given-name"
                                    class="h-11"
                                    :aria-invalid="
                                        !!error('first_name') || undefined
                                    "
                                    aria-describedby="first_name-error"
                                />
                                <InputError
                                    id="first_name-error"
                                    :message="error('first_name')"
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
                                    :aria-invalid="
                                        !!error('last_name') || undefined
                                    "
                                />
                                <InputError :message="error('last_name')" />
                            </div>
                            <div class="space-y-2">
                                <Label for="phone">Teléfono celular</Label>
                                <Input
                                    id="phone"
                                    v-model="form.phone"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel-national"
                                    placeholder="10 dígitos"
                                    class="h-11 tabular-nums"
                                    :aria-invalid="
                                        !!error('phone') || undefined
                                    "
                                    aria-describedby="phone-help phone-error"
                                />
                                <p
                                    id="phone-help"
                                    class="text-xs text-muted-foreground"
                                >
                                    Sin lada internacional, por ejemplo 55 1234
                                    5678.
                                </p>
                                <InputError
                                    id="phone-error"
                                    :message="error('phone')"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="email">
                                    Correo
                                    <span
                                        v-if="
                                            form.preferred_contact !== 'email'
                                        "
                                        class="font-normal text-muted-foreground"
                                        >(recomendado)</span
                                    >
                                </Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    class="h-11"
                                    :aria-invalid="
                                        !!error('email') || undefined
                                    "
                                    aria-describedby="email-help email-error"
                                />
                                <p
                                    id="email-help"
                                    class="text-xs text-muted-foreground"
                                >
                                    Te enviamos tu folio y la confirmación de
                                    tus citas.
                                </p>
                                <InputError
                                    id="email-error"
                                    :message="error('email')"
                                />
                            </div>
                        </div>
                        <div class="space-y-3">
                            <p class="text-sm font-medium">
                                ¿Cómo prefieres que te contactemos?
                            </p>
                            <ChoiceCards
                                v-model="form.preferred_contact"
                                name="preferred_contact"
                                label="Medio de contacto preferido"
                                :options="contactOptions"
                                :columns="3"
                                :invalid="!!error('preferred_contact')"
                                described-by="preferred_contact-error"
                            />
                            <InputError
                                id="preferred_contact-error"
                                :message="error('preferred_contact')"
                            />
                        </div>
                    </div>

                    <!-- 5. Detalles -->
                    <div v-show="current === 4" class="step-panel space-y-6">
                        <div class="space-y-2">
                            <p class="text-sm font-medium">
                                Fotos del espacio
                                <span class="font-normal text-muted-foreground"
                                    >(opcional)</span
                                >
                            </p>
                            <FilePicker
                                id="photos"
                                v-model="form.photos"
                                :accept="limits.photoTypes"
                                :max-files="limits.maxPhotos"
                                :max-size-mb="limits.maxPhotoMb"
                                label="Agrega fotos de tu espacio"
                                :invalid="!!error('photos')"
                                described-by="photos-error"
                            />
                            <p class="text-xs text-muted-foreground">
                                Solo las ve el equipo de ML Habitat que atiende
                                tu solicitud.
                            </p>
                            <InputError
                                id="photos-error"
                                :message="error('photos')"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="measurements_note"
                                >Medidas aproximadas
                                <span class="font-normal text-muted-foreground"
                                    >(opcional)</span
                                ></Label
                            >
                            <Textarea
                                id="measurements_note"
                                v-model="form.measurements_note"
                                rows="3"
                                maxlength="1000"
                                placeholder="Ej. pared de 3.20 m de largo y 2.40 m de alto"
                                :aria-invalid="
                                    !!error('measurements_note') || undefined
                                "
                            />
                            <InputError :message="error('measurements_note')" />
                        </div>
                        <div class="space-y-2">
                            <Label for="comments"
                                >¿Algo más que debamos saber?
                                <span class="font-normal text-muted-foreground"
                                    >(opcional)</span
                                ></Label
                            >
                            <Textarea
                                id="comments"
                                v-model="form.comments"
                                rows="4"
                                maxlength="2000"
                                placeholder="Estilo que te gusta, materiales, horarios para contactarte…"
                                :aria-invalid="!!error('comments') || undefined"
                            />
                            <InputError :message="error('comments')" />
                        </div>
                        <div class="rounded-lg border bg-muted/50 p-4">
                            <div class="flex items-start gap-3">
                                <Checkbox
                                    id="privacy"
                                    v-model="form.privacy"
                                    class="mt-0.5"
                                    :aria-invalid="
                                        !!error('privacy') || undefined
                                    "
                                    aria-describedby="privacy-error"
                                    @update:model-value="
                                        delete clientErrors.privacy
                                    "
                                />
                                <Label
                                    for="privacy"
                                    class="block text-sm leading-relaxed font-normal"
                                >
                                    Acepto el
                                    <a
                                        :href="privacyUrl"
                                        target="_blank"
                                        rel="noopener"
                                        class="font-medium text-primary underline underline-offset-2"
                                        >aviso de privacidad</a
                                    >
                                    y que ML Habitat me contacte sobre esta
                                    solicitud.
                                </Label>
                            </div>
                            <InputError
                                id="privacy-error"
                                class="mt-2"
                                :message="error('privacy')"
                            />
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div
                        class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 border-t bg-card/95 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur md:static md:mt-8 md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none"
                    >
                        <Button
                            v-if="current > 0"
                            type="button"
                            variant="outline"
                            class="h-11"
                            :disabled="form.processing"
                            @click="back"
                        >
                            <ArrowLeft aria-hidden="true" /> Atrás
                        </Button>
                        <Button
                            v-if="!isLast"
                            type="submit"
                            class="btn-nudge ml-auto h-11 flex-1 md:flex-none md:px-8"
                        >
                            Continuar
                            <ArrowRight class="nudge" aria-hidden="true" />
                        </Button>
                        <Button
                            v-else
                            type="submit"
                            class="btn-nudge ml-auto h-11 flex-1 md:flex-none md:px-8"
                            :disabled="form.processing"
                        >
                            <Loader2
                                v-if="form.processing"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            {{
                                form.processing
                                    ? 'Enviando…'
                                    : 'Enviar solicitud'
                            }}
                        </Button>
                    </div>
                </form>

                <aside
                    class="hidden space-y-4 lg:block"
                    aria-label="Resumen de tu solicitud"
                >
                    <div class="rounded-xl border bg-card p-5">
                        <h2 class="text-sm font-medium">Tu solicitud</h2>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Espacios
                                </dt>
                                <dd>
                                    {{
                                        selectedNames.length
                                            ? selectedNames.join(', ')
                                            : '—'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Código postal
                                </dt>
                                <dd class="tabular-nums">
                                    {{ form.postal_code || '—' }}
                                </dd>
                            </div>
                        </dl>
                        <ol class="mt-4 space-y-1">
                            <li v-for="(step, index) in steps" :key="step.key">
                                <button
                                    type="button"
                                    class="text-xs text-muted-foreground enabled:hover:text-primary enabled:hover:underline disabled:cursor-default"
                                    :disabled="index >= current"
                                    @click="goTo(index)"
                                >
                                    {{ index + 1 }}. {{ step.title }}
                                </button>
                            </li>
                        </ol>
                    </div>
                    <ul class="space-y-3 rounded-xl border bg-card p-5 text-sm">
                        <li class="flex gap-3">
                            <Sparkles
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span
                                >Asesoría remota
                                <strong class="font-medium"
                                    >sin compromiso</strong
                                >
                                para aterrizar tu idea.</span
                            >
                        </li>
                        <li class="flex gap-3">
                            <Clock
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span
                                >Te contactamos en horario laboral, normalmente
                                el mismo día.</span
                            >
                        </li>
                        <li class="flex gap-3">
                            <ShieldCheck
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span
                                >Tus datos y fotos solo los usa el equipo que
                                atiende tu proyecto.</span
                            >
                        </li>
                    </ul>
                </aside>
            </div>
        </div>
    </section>
</template>
