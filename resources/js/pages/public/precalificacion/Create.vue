<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    FileCheck2,
    Info,
    Loader2,
    Lock,
    MessageSquareWarning,
} from '@lucide/vue';
import { computed } from 'vue';
import FilePicker from '@/components/crm/FilePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Option = { value: string; label: string };

const props = defineProps<{
    lead: { folio: string; firstName: string };
    prequalification: {
        folio: string;
        status: { value: string; label: string; tone: Tone };
        acceptsInput: boolean;
        infoRequestedNote: string | null;
        documentsCount: number;
        submittedAt: string | null;
    } | null;
    options: {
        incomeRanges: Option[];
        employmentTypes: Option[];
        terms: Option[];
    };
    submitUrl: string;
}>();

const showForm = computed(
    () => !props.prequalification || props.prequalification.acceptsInput,
);

const form = useForm({
    requested_amount: '',
    preferred_term_months: null as string | null,
    income_range: null as string | null,
    employment_type: null as string | null,
    applicant_notes: '',
    documents: [] as File[],
    consent: false,
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function documentsError(): string | undefined {
    return (
        errors.value.documents ??
        Object.entries(errors.value).find(([k]) =>
            k.startsWith('documents.'),
        )?.[1]
    );
}

function submit() {
    form.post(props.submitUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Precalificación para pago a plazos" />

    <section class="bg-background pb-16">
        <div class="container-site max-w-3xl pt-10 md:pt-16">
            <p class="text-sm font-medium text-oak-ink">
                Solicitud {{ lead.folio }}
            </p>
            <h1 class="mt-2 text-display-md font-display tracking-tight">
                Precalificación para pago a plazos
            </h1>
            <p class="mt-3 text-lg text-muted-foreground">
                {{ lead.firstName }}, con esta información revisamos si es
                posible ofrecerte pagar tu proyecto a plazos.
            </p>

            <div
                class="mt-6 flex gap-3 rounded-xl border border-info/25 bg-info-soft p-4 text-sm text-info-ink"
                role="note"
            >
                <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <p>
                    Esto es una <strong>precalificación</strong>, no una
                    aprobación de crédito. Un especialista revisa cada solicitud
                    de forma personal y cualquier resultado queda sujeto a
                    evaluación final al formalizar tu proyecto.
                </p>
            </div>

            <!-- Estado actual -->
            <article
                v-if="prequalification"
                class="mt-8 rounded-xl border bg-card p-6 shadow-xs"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Folio {{ prequalification.folio }}
                        </p>
                        <h2
                            class="mt-1 font-display text-lg font-semibold tracking-tight"
                        >
                            {{ prequalification.status.label }}
                        </h2>
                    </div>
                    <StatusBadge
                        :label="prequalification.status.label"
                        :tone="prequalification.status.tone"
                    />
                </div>
                <p
                    v-if="prequalification.submittedAt"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    Recibimos tu información el
                    <DateText
                        :value="prequalification.submittedAt"
                        format="long"
                    />
                    <template v-if="prequalification.documentsCount">
                        con
                        {{ prequalification.documentsCount }}
                        documento(s)</template
                    >.
                </p>
                <div
                    v-if="prequalification.infoRequestedNote"
                    class="mt-4 flex gap-3 rounded-lg border border-warning/25 bg-warning-soft p-4 text-sm text-warning-ink"
                >
                    <MessageSquareWarning
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="font-medium">Lo que necesitamos de ti</p>
                        <p class="mt-1 whitespace-pre-line">
                            {{ prequalification.infoRequestedNote }}
                        </p>
                    </div>
                </div>
                <p
                    v-if="!prequalification.acceptsInput"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    No necesitas hacer nada más por ahora. Te contactaremos
                    cuando terminemos la revisión.
                </p>
            </article>

            <!-- Formulario -->
            <form
                v-if="showForm"
                class="mt-8 space-y-6 rounded-xl border bg-card p-5 shadow-xs sm:p-8"
                novalidate
                @submit.prevent="submit"
            >
                <p
                    v-if="errors.form"
                    class="rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-ink"
                    role="alert"
                >
                    {{ errors.form }}
                </p>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="requested_amount"
                            >Monto que te gustaría financiar
                            <span class="font-normal text-muted-foreground"
                                >(opcional)</span
                            ></Label
                        >
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                aria-hidden="true"
                                >$</span
                            >
                            <Input
                                id="requested_amount"
                                v-model="form.requested_amount"
                                inputmode="decimal"
                                placeholder="85,000"
                                class="h-11 pl-7 tabular-nums"
                                :aria-invalid="
                                    !!errors.requested_amount || undefined
                                "
                                aria-describedby="requested_amount-help requested_amount-error"
                            />
                        </div>
                        <p
                            id="requested_amount-help"
                            class="text-xs text-muted-foreground"
                        >
                            Un aproximado en pesos; tu asesor lo ajusta con la
                            cotización.
                        </p>
                        <InputError
                            id="requested_amount-error"
                            :message="errors.requested_amount"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="preferred_term_months"
                            >Plazo preferido
                            <span class="font-normal text-muted-foreground"
                                >(opcional)</span
                            ></Label
                        >
                        <Select v-model="form.preferred_term_months">
                            <SelectTrigger
                                id="preferred_term_months"
                                class="h-11 w-full"
                                :aria-invalid="
                                    !!errors.preferred_term_months || undefined
                                "
                            >
                                <SelectValue placeholder="Elige un plazo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="term in options.terms"
                                    :key="term.value"
                                    :value="term.value"
                                    >{{ term.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.preferred_term_months" />
                    </div>
                    <div class="space-y-2">
                        <Label for="income_range"
                            >Ingreso mensual aproximado</Label
                        >
                        <Select v-model="form.income_range">
                            <SelectTrigger
                                id="income_range"
                                class="h-11 w-full"
                                :aria-invalid="
                                    !!errors.income_range || undefined
                                "
                                aria-describedby="income_range-error"
                            >
                                <SelectValue placeholder="Elige un rango" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="o in options.incomeRanges"
                                    :key="o.value"
                                    :value="o.value"
                                    >{{ o.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            id="income_range-error"
                            :message="errors.income_range"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="employment_type">Ocupación</Label>
                        <Select v-model="form.employment_type">
                            <SelectTrigger
                                id="employment_type"
                                class="h-11 w-full"
                                :aria-invalid="
                                    !!errors.employment_type || undefined
                                "
                                aria-describedby="employment_type-error"
                            >
                                <SelectValue placeholder="Elige una opción" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="o in options.employmentTypes"
                                    :key="o.value"
                                    :value="o.value"
                                    >{{ o.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            id="employment_type-error"
                            :message="errors.employment_type"
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="applicant_notes"
                        >Comentarios
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Textarea
                        id="applicant_notes"
                        v-model="form.applicant_notes"
                        rows="3"
                        maxlength="2000"
                        placeholder="Cualquier dato que nos ayude a entender tu situación"
                    />
                    <InputError :message="errors.applicant_notes" />
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-medium">
                        Documentos
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Por ejemplo, identificación oficial o comprobantes de
                        ingresos. Si prefieres, tu asesor te los pide después.
                    </p>
                    <FilePicker
                        id="documents"
                        v-model="form.documents"
                        :accept="['application/pdf', 'image/jpeg', 'image/png']"
                        :max-files="8"
                        :max-size-mb="10"
                        label="Agrega tus documentos"
                        :invalid="!!documentsError()"
                        described-by="documents-error"
                    />
                    <p
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <Lock class="size-3.5" aria-hidden="true" /> Se guardan
                        de forma privada y solo los ve el equipo que revisa tu
                        solicitud.
                    </p>
                    <InputError
                        id="documents-error"
                        :message="documentsError()"
                    />
                </div>

                <div class="rounded-lg border bg-muted/50 p-4">
                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="consent"
                            v-model="form.consent"
                            class="mt-0.5"
                            :aria-invalid="!!errors.consent || undefined"
                            aria-describedby="consent-error"
                        />
                        <Label
                            for="consent"
                            class="block text-sm leading-relaxed font-normal"
                        >
                            Entiendo que esta es una precalificación sujeta a
                            evaluación final y no una aprobación de crédito, y
                            autorizo a ML Habitat a revisar la información que
                            comparto.
                        </Label>
                    </div>
                    <InputError
                        id="consent-error"
                        class="mt-2"
                        :message="errors.consent"
                    />
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full sm:w-auto sm:px-8"
                    :disabled="form.processing"
                >
                    <Loader2
                        v-if="form.processing"
                        class="animate-spin"
                        aria-hidden="true"
                    />
                    <FileCheck2 v-else aria-hidden="true" />
                    {{ form.processing ? 'Enviando…' : 'Enviar para revisión' }}
                </Button>
            </form>
        </div>
    </section>
</template>
