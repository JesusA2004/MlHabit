<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheck, Printer } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    ConfirmDialog,
    DateText,
    FormSheet,
    Money,
    PageContainer,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { aceptar, index, rechazar } from '@/routes/portal/cotizaciones';
import type { ContractData, QuoteData } from '@/types/projects';

const props = defineProps<{
    quote: QuoteData & {
        project: { folio: string; name: string };
        design: { number: number; title: string } | null;
        print_url: string;
    };
    contract: ContractData | null;
    can: { respond: boolean };
}>();

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

const hasDiscount = computed(() => Number(props.quote.discount_total) > 0);

const texts = computed(() =>
    [
        { key: 'inclusions', title: 'Incluye', body: props.quote.inclusions },
        {
            key: 'exclusions',
            title: 'No incluye',
            body: props.quote.exclusions,
        },
        { key: 'notes', title: 'Notas', body: props.quote.notes },
        { key: 'terms', title: 'Condiciones', body: props.quote.terms },
    ].filter((t) => t.body && t.body.trim() !== ''),
);

// Aceptar
const acceptOpen = ref(false);
const confirmed = ref(false);

function accept(): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(
            aceptar(props.quote.uuid).url,
            { confirm: confirmed.value },
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (e) =>
                    reject(
                        new Error(
                            Object.values(e)[0] ??
                                'No pudimos registrar tu aceptación.',
                        ),
                    ),
            },
        );
    });
}

function confirmAccept(): Promise<void> {
    return confirmed.value
        ? accept()
        : Promise.reject(new Error('Marca la casilla para confirmar.'));
}

// Rechazar
const rejectOpen = ref(false);
const rejectForm = useForm({ reason: '' });

function reject() {
    rejectForm.post(rechazar(props.quote.uuid).url, {
        preserveScroll: true,
        onSuccess: () => {
            rejectOpen.value = false;
            rejectForm.reset();
        },
    });
}
</script>

<template>
    <Head :title="`Cotización ${quote.folio}`" />

    <PageContainer width="narrow" class="flex flex-col gap-6">
        <Link
            :href="index().url"
            class="inline-flex items-center gap-1.5 self-start rounded-md text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" aria-hidden="true" /> Cotizaciones
        </Link>

        <PageHeader
            :title="quote.title"
            :description="`${quote.project.name} · ${quote.folio} · versión ${quote.version}`"
        >
            <template #meta>
                <StatusBadge
                    :tone="quote.status.tone"
                    :label="quote.status.client_label"
                />
                <span v-if="quote.valid_until && can.respond">
                    Vigente hasta el
                    <DateText :value="quote.valid_until" format="long" />
                </span>
                <span v-if="quote.design"
                    >Diseño v{{ quote.design.number }}:
                    {{ quote.design.title }}</span
                >
            </template>
            <template #actions>
                <Button as-child variant="outline">
                    <a :href="quote.print_url" target="_blank" rel="noopener"
                        ><Printer class="size-4" aria-hidden="true" /> Imprimir
                        o guardar PDF</a
                    >
                </Button>
            </template>
        </PageHeader>

        <!-- Contrato si ya se aceptó -->
        <div
            v-if="contract"
            class="flex flex-col gap-3 rounded-xl border border-success/30 bg-success-soft p-4 text-success-ink sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <CircleCheck
                    class="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div>
                    <p class="font-medium">
                        Aceptaste esta cotización. Tu contrato es el
                        {{ contract.folio }}.
                    </p>
                    <p v-if="contract.pay_url" class="text-sm">
                        Anticipo por pagar:
                        <Money
                            :value="contract.deposit_due_amount"
                            class="font-semibold"
                        />
                    </p>
                </div>
            </div>
            <Button v-if="contract.pay_url" as-child
                ><Link :href="contract.pay_url">Pagar anticipo</Link></Button
            >
        </div>

        <!-- Partidas -->
        <SectionCard title="Detalle" flush>
            <ul class="divide-y">
                <li
                    v-for="item in quote.items"
                    :key="item.id ?? item.description"
                    class="flex flex-col gap-1 px-4 py-3.5 sm:flex-row sm:items-start sm:justify-between sm:gap-6 sm:px-5"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-foreground">
                            {{ item.description }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="item.code"
                                >{{ item.code }} ·
                            </template>
                            {{
                                Number(item.quantity).toLocaleString('es-MX', {
                                    maximumFractionDigits: 3,
                                })
                            }}
                            {{ item.unit }} ×
                            <Money :value="item.unit_price" />
                            <template v-if="Number(item.discount_amount) > 0">
                                · descuento
                                <Money :value="item.discount_amount"
                            /></template>
                        </p>
                    </div>
                    <Money
                        :value="item.line_total"
                        class="shrink-0 text-sm font-semibold text-foreground tabular-nums"
                    />
                </li>
            </ul>
            <dl
                class="flex flex-col gap-1.5 border-t bg-muted/30 px-4 py-4 text-sm sm:px-5"
            >
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Subtotal</dt>
                    <dd class="tabular-nums">
                        <Money :value="quote.subtotal" />
                    </dd>
                </div>
                <div v-if="hasDiscount" class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Descuento</dt>
                    <dd class="tabular-nums">
                        −<Money :value="quote.discount_total" />
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">
                        {{
                            quote.prices_include_tax
                                ? `IVA incluido (${quote.tax_percent}%)`
                                : `IVA (${quote.tax_percent}%)`
                        }}
                    </dt>
                    <dd class="tabular-nums">
                        <Money :value="quote.tax_total" />
                    </dd>
                </div>
                <div
                    class="mt-1 flex items-baseline justify-between gap-4 border-t pt-2.5"
                >
                    <dt class="font-semibold text-foreground">Total</dt>
                    <dd
                        class="text-xl font-semibold text-foreground tabular-nums"
                    >
                        <Money
                            :value="quote.total"
                            :currency="quote.currency"
                            show-currency
                        />
                    </dd>
                </div>
            </dl>
        </SectionCard>

        <SectionCard v-for="t in texts" :key="t.key" :title="t.title">
            <p class="text-sm whitespace-pre-line text-foreground">
                {{ t.body }}
            </p>
        </SectionCard>

        <!-- Respuesta -->
        <SectionCard
            v-if="can.respond"
            title="¿Qué te parece?"
            description="Al aceptar generamos tu contrato y te mostramos el anticipo."
        >
            <div class="flex flex-col gap-2 sm:flex-row">
                <Button class="sm:flex-1" @click="acceptOpen = true"
                    >Aceptar cotización</Button
                >
                <Button
                    variant="outline"
                    class="sm:flex-1"
                    @click="rejectOpen = true"
                    >No me convence</Button
                >
            </div>
        </SectionCard>

        <p
            v-else-if="
                quote.status.value === 'rejected' && quote.rejection_reason
            "
            class="text-sm text-muted-foreground"
        >
            Nos dijiste: «{{ quote.rejection_reason }}». Tu asesor te contactará
            con una nueva propuesta.
        </p>
    </PageContainer>

    <ConfirmDialog
        v-model:open="acceptOpen"
        title="Aceptar cotización"
        confirm-label="Aceptar y continuar"
        loading-label="Generando contrato…"
        :confirm="confirmAccept"
    >
        <div class="flex flex-col gap-3 text-left">
            <p>
                Total:
                <Money
                    :value="quote.total"
                    class="font-semibold text-foreground"
                />. Después te pediremos el anticipo para iniciar la
                fabricación.
            </p>
            <div class="flex items-start gap-2.5">
                <Checkbox
                    id="confirm-accept"
                    :model-value="confirmed"
                    @update:model-value="(v) => (confirmed = v === true)"
                />
                <Label
                    for="confirm-accept"
                    class="text-sm leading-snug font-normal text-foreground"
                >
                    Leí el detalle y las condiciones, y acepto esta cotización.
                </Label>
            </div>
            <InputError :message="errors.confirm ?? errors.quote" />
        </div>
    </ConfirmDialog>

    <FormSheet
        v-model:open="rejectOpen"
        title="Cuéntanos qué no te convenció"
        description="Con tu respuesta preparamos una mejor propuesta."
    >
        <form
            id="reject-form"
            class="flex flex-col gap-2"
            @submit.prevent="reject"
        >
            <Label for="reject-reason">Motivo</Label>
            <Textarea
                id="reject-reason"
                v-model="rejectForm.reason"
                rows="5"
                placeholder="Ej.: el presupuesto es mayor a lo que planeaba, quisiera otro acabado…"
                :aria-invalid="!!rejectForm.errors.reason"
                aria-describedby="reject-reason-error"
            />
            <InputError
                id="reject-reason-error"
                :message="rejectForm.errors.reason"
            />
        </form>
        <template #footer>
            <Button variant="outline" @click="rejectOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="reject-form"
                :disabled="rejectForm.processing"
            >
                {{ rejectForm.processing ? 'Enviando…' : 'Enviar respuesta' }}
            </Button>
        </template>
    </FormSheet>
</template>
