<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    Copy,
    Info,
    Plus,
    Printer,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/format';
import cotizaciones from '@/routes/admin/cotizaciones';
import proyectos from '@/routes/admin/proyectos';
import type { BreadcrumbItem } from '@/types';
import type { QuoteData } from '@/types/projects';

type Line = {
    key: number;
    code: string;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount_amount: string;
};

const props = defineProps<{
    project: {
        uuid: string;
        folio: string;
        name: string;
        customer: string | null;
    };
    quote: QuoteData | null;
    designs: { id: number; label: string; status: string }[];
    defaults: {
        tax_rate: string;
        prices_include_tax: boolean;
        validity_days: number;
        currency: string;
    };
    units: string[];
    can: { manage: boolean; authorize_discount: boolean };
}>();

defineOptions({
    layout: (p: { project?: { uuid: string; folio: string } }) => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Proyectos', href: '/admin/proyectos' },
            {
                title: p.project?.folio ?? 'Proyecto',
                href: `/admin/proyectos/${p.project?.uuid ?? ''}`,
            },
            { title: 'Cotización', href: '#' },
        ] as BreadcrumbItem[],
    }),
});

let seq = 0;
const blankLine = (): Line => ({
    key: ++seq,
    code: '',
    description: '',
    quantity: '1',
    unit: 'pza',
    unit_price: '',
    discount_amount: '',
});

const status = computed(() => props.quote?.status.value ?? 'draft');
const locked = computed(
    () =>
        !props.can.manage || ['accepted', 'superseded'].includes(status.value),
);
const createsVersion = computed(
    () =>
        props.quote !== null &&
        ['sent', 'rejected', 'expired'].includes(status.value),
);

const form = useForm({
    title: props.quote?.title ?? props.project.name,
    design_version_id:
        props.quote?.design_version_id ?? props.designs[0]?.id ?? null,
    inclusions: props.quote?.inclusions ?? '',
    exclusions: props.quote?.exclusions ?? '',
    notes: props.quote?.notes ?? '',
    terms: props.quote?.terms ?? '',
    items: (props.quote?.items.length
        ? props.quote.items.map((i) => ({
              key: ++seq,
              code: i.code ?? '',
              description: i.description,
              quantity: String(Number(i.quantity)),
              unit: i.unit,
              unit_price: i.unit_price,
              discount_amount:
                  Number(i.discount_amount) > 0 ? i.discount_amount : '',
          }))
        : [blankLine()]) as Line[],
});

function addLine(after?: number) {
    const line = blankLine();
    if (after === undefined) {
        form.items.push(line);
    } else {
        form.items.splice(after + 1, 0, line);
    }
}

function duplicate(index: number) {
    form.items.splice(index + 1, 0, { ...form.items[index], key: ++seq });
}

function move(index: number, delta: -1 | 1) {
    const target = index + delta;
    if (target < 0 || target >= form.items.length) return;
    const list = form.items;
    [list[index], list[target]] = [list[target], list[index]];
}

function removeLine(index: number) {
    form.items.splice(index, 1);
    if (!form.items.length) addLine();
}

// ---- Vista previa (solo informativa: el servidor recalcula con bcmath)
const toCents = (value: string) => {
    const clean = String(value ?? '').replace(/[,$\s]/g, '');
    const n = Number(clean);

    return Number.isFinite(n) ? Math.round(n * 100) : 0;
};
const qtyThousandths = (value: string) => {
    const n = Number(String(value ?? '').replace(/,/g, ''));

    return Number.isFinite(n) ? Math.round(n * 1000) : 0;
};
const lineGross = (l: Line) =>
    Math.round((toCents(l.unit_price) * qtyThousandths(l.quantity)) / 1000);
const lineTotal = (l: Line) => lineGross(l) - toCents(l.discount_amount);

const includeTax = computed(
    () => props.quote?.prices_include_tax ?? props.defaults.prices_include_tax,
);
const taxRate = computed(() =>
    Number(props.quote?.tax_rate ?? props.defaults.tax_rate),
);
const preview = computed(() => {
    const subtotal = form.items.reduce((s, l) => s + lineGross(l), 0);
    const discount = form.items.reduce(
        (s, l) => s + toCents(l.discount_amount),
        0,
    );
    const net = subtotal - discount;
    let tax: number;
    let total: number;
    if (includeTax.value) {
        total = net;
        tax = net - Math.round(net / (1 + taxRate.value));
    } else {
        tax = Math.round(net * taxRate.value);
        total = net + tax;
    }

    return {
        subtotal: subtotal / 100,
        discount: discount / 100,
        tax: tax / 100,
        total: total / 100,
    };
});
const fmt = (v: number) => formatMoney(v.toFixed(2));
const hasDiscount = computed(() =>
    form.items.some((l) => toCents(l.discount_amount) > 0),
);
const lineError = (i: number, field: string) =>
    (form.errors as Record<string, string>)[`items.${i}.${field}`];

function save() {
    const payload = (
        data: typeof form.data extends () => infer R ? R : never,
    ) => ({
        ...data,
        items: data.items.map(({ key: _key, ...rest }) => rest),
    });
    form.transform(payload);
    if (props.quote) {
        form.put(cotizaciones.update(props.quote.uuid).url, {
            preserveScroll: true,
        });
    } else {
        form.post(cotizaciones.store(props.project.uuid).url, {
            preserveScroll: true,
        });
    }
}

// ---- Enviar / rechazar
const sendOpen = ref(false);
function send() {
    if (!props.quote) return;
    const uuid = props.quote.uuid;

    return new Promise<void>((resolve, reject) =>
        router.post(
            cotizaciones.enviar(uuid).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (e) =>
                    reject(
                        new Error(Object.values(e)[0] ?? 'No se pudo enviar.'),
                    ),
            },
        ),
    );
}

const rejectOpen = ref(false);
const rejectForm = useForm({ reason: '' });
function submitReject() {
    if (!props.quote) return;
    rejectForm.post(cotizaciones.rechazar(props.quote.uuid).url, {
        preserveScroll: true,
        onSuccess: () => (rejectOpen.value = false),
    });
}

const title = computed(() =>
    props.quote ? `Cotización v${props.quote.version}` : 'Nueva cotización',
);
</script>

<template>
    <Head :title="`${title} · ${project.folio}`" />

    <PageContainer>
        <PageHeader :title="title">
            <template #breadcrumbs>
                <Link
                    :href="`${proyectos.show(project.uuid).url}?tab=cotizaciones`"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" />
                    {{ project.folio }} · {{ project.name }}
                </Link>
            </template>
            <template #meta>
                <span v-if="quote" class="text-xs tabular-nums">{{
                    quote.folio
                }}</span>
                <StatusBadge
                    v-if="quote"
                    :tone="quote.status.tone"
                    :label="quote.status.label"
                />
                <span v-if="project.customer">Para {{ project.customer }}</span>
                <span v-if="quote?.valid_until"
                    >· Vigente hasta <DateText :value="quote.valid_until"
                /></span>
            </template>
            <template #actions>
                <Button v-if="quote" variant="outline" as-child>
                    <a
                        :href="`/cotizaciones/${quote.uuid}/imprimir`"
                        target="_blank"
                        rel="noopener"
                        ><Printer class="size-4" aria-hidden="true" />
                        Imprimir</a
                    >
                </Button>
                <Button
                    v-if="quote && status === 'sent' && can.manage"
                    variant="outline"
                    @click="rejectOpen = true"
                    >Marcar rechazada</Button
                >
                <Button
                    v-if="quote && status === 'draft' && can.manage"
                    :disabled="form.isDirty"
                    @click="sendOpen = true"
                >
                    <Send class="size-4" aria-hidden="true" /> Enviar al cliente
                </Button>
            </template>
        </PageHeader>

        <p
            v-if="createsVersion && !locked"
            class="flex gap-2 rounded-lg border border-info/25 bg-info-soft p-3 text-sm text-info-ink"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Esta cotización ya se envió. Al guardar cambios se crea la versión
            {{ (quote?.version ?? 0) + 1 }} y esta queda como historial.
        </p>
        <p
            v-else-if="locked && quote"
            class="flex gap-2 rounded-lg bg-muted p-3 text-sm"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{
                status === 'accepted'
                    ? `Aceptada${quote.accepted_by ? ' (registrada por ' + quote.accepted_by + ')' : ''}: es la base del contrato y ya no cambia.`
                    : 'Esta versión fue reemplazada por una más reciente.'
            }}
        </p>
        <p
            v-if="quote?.rejection_reason"
            class="rounded-lg bg-danger-soft p-3 text-sm text-danger-ink"
        >
            Motivo del rechazo: {{ quote.rejection_reason }}
        </p>

        <form class="flex flex-col gap-6" @submit.prevent="save">
            <SectionCard title="Encabezado">
                <div
                    class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
                >
                    <div class="grid gap-1.5">
                        <Label for="q-title">Título</Label>
                        <Input
                            id="q-title"
                            v-model="form.title"
                            maxlength="160"
                            :disabled="locked"
                        />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="q-design">Diseño que cotiza</Label>
                        <SearchSelect
                            id="q-design"
                            v-model="form.design_version_id"
                            :options="
                                designs.map((d) => ({
                                    value: d.id,
                                    label: d.label,
                                    description: d.status,
                                }))
                            "
                            placeholder="Sin diseño ligado"
                            clearable
                            :disabled="locked"
                        />
                        <InputError :message="form.errors.design_version_id" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    IVA {{ (taxRate * 100).toFixed(0) }}% ·
                    {{
                        includeTax
                            ? 'precios con IVA incluido'
                            : 'IVA se suma al final'
                    }}
                    · vigencia {{ defaults.validity_days }} días desde el envío.
                    Estos valores vienen de la configuración comercial y se
                    congelan en la cotización.
                </p>
            </SectionCard>

            <SectionCard title="Partidas" flush>
                <template #actions>
                    <span
                        v-if="!can.authorize_discount"
                        class="text-xs text-muted-foreground"
                        >Los descuentos requieren autorización.</span
                    >
                </template>
                <InputError
                    v-if="form.errors.items"
                    :message="form.errors.items"
                    class="px-4 pt-3"
                />

                <div
                    class="hidden grid-cols-[2rem_6rem_minmax(0,1fr)_6rem_6rem_8rem_7rem_8rem_5.5rem] gap-2 border-b bg-muted/50 px-4 py-2 text-xs font-medium text-muted-foreground lg:grid"
                >
                    <span>#</span><span>Clave</span><span>Descripción</span
                    ><span>Cantidad</span><span>Unidad</span
                    ><span class="text-right">Precio unit.</span
                    ><span class="text-right">Descuento</span
                    ><span class="text-right">Importe</span
                    ><span class="sr-only">Acciones</span>
                </div>

                <ol class="flex flex-col divide-y">
                    <li
                        v-for="(line, i) in form.items"
                        :key="line.key"
                        class="grid grid-cols-2 gap-2 px-4 py-3 lg:grid-cols-[2rem_6rem_minmax(0,1fr)_6rem_6rem_8rem_7rem_8rem_5.5rem] lg:items-start"
                    >
                        <span
                            class="col-span-2 text-xs font-medium text-muted-foreground lg:col-span-1 lg:pt-2.5"
                            >{{ i + 1 }}</span
                        >
                        <div class="lg:col-span-1">
                            <Label
                                :for="`l-code-${line.key}`"
                                class="text-xs lg:sr-only"
                                >Clave</Label
                            >
                            <Input
                                :id="`l-code-${line.key}`"
                                v-model="line.code"
                                maxlength="40"
                                :disabled="locked"
                                placeholder="Opcional"
                            />
                        </div>
                        <div
                            class="col-span-2 lg:col-span-1 lg:col-start-3 lg:row-start-1"
                        >
                            <Label
                                :for="`l-desc-${line.key}`"
                                class="text-xs lg:sr-only"
                                >Descripción</Label
                            >
                            <Textarea
                                :id="`l-desc-${line.key}`"
                                v-model="line.description"
                                rows="1"
                                maxlength="500"
                                class="min-h-9"
                                :disabled="locked"
                                placeholder="Ej. Gabinete inferior 60 cm en MDF con laca"
                            />
                            <InputError
                                :message="lineError(i, 'description')"
                            />
                        </div>
                        <div>
                            <Label
                                :for="`l-qty-${line.key}`"
                                class="text-xs lg:sr-only"
                                >Cantidad</Label
                            >
                            <Input
                                :id="`l-qty-${line.key}`"
                                v-model="line.quantity"
                                inputmode="decimal"
                                :disabled="locked"
                            />
                            <InputError :message="lineError(i, 'quantity')" />
                        </div>
                        <div>
                            <Label
                                :for="`l-unit-${line.key}`"
                                class="text-xs lg:sr-only"
                                >Unidad</Label
                            >
                            <SearchSelect
                                :id="`l-unit-${line.key}`"
                                v-model="line.unit"
                                :options="
                                    units.map((u) => ({ value: u, label: u }))
                                "
                                :disabled="locked"
                            />
                            <InputError :message="lineError(i, 'unit')" />
                        </div>
                        <div>
                            <Label
                                :for="`l-price-${line.key}`"
                                class="text-xs lg:sr-only"
                                >Precio unitario</Label
                            >
                            <Input
                                :id="`l-price-${line.key}`"
                                v-model="line.unit_price"
                                inputmode="decimal"
                                class="text-right tabular-nums"
                                :disabled="locked"
                                placeholder="0.00"
                            />
                            <InputError :message="lineError(i, 'unit_price')" />
                        </div>
                        <div>
                            <Label
                                :for="`l-disc-${line.key}`"
                                class="text-xs lg:sr-only"
                                >Descuento</Label
                            >
                            <Input
                                :id="`l-disc-${line.key}`"
                                v-model="line.discount_amount"
                                inputmode="decimal"
                                class="text-right tabular-nums"
                                :disabled="locked || !can.authorize_discount"
                                :placeholder="
                                    can.authorize_discount ? '0.00' : '—'
                                "
                            />
                            <InputError
                                :message="lineError(i, 'discount_amount')"
                            />
                        </div>
                        <div
                            class="flex items-center justify-between lg:block lg:pt-2 lg:text-right"
                        >
                            <span
                                class="text-xs text-muted-foreground lg:hidden"
                                >Importe</span
                            >
                            <span class="font-medium tabular-nums">{{
                                fmt(lineTotal(line) / 100)
                            }}</span>
                        </div>
                        <div
                            v-if="!locked"
                            class="col-span-2 flex justify-end gap-0.5 lg:col-span-1"
                        >
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                :aria-label="`Subir partida ${i + 1}`"
                                :disabled="i === 0"
                                @click="move(i, -1)"
                                ><ArrowUp class="size-4"
                            /></Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                :aria-label="`Bajar partida ${i + 1}`"
                                :disabled="i === form.items.length - 1"
                                @click="move(i, 1)"
                                ><ArrowDown class="size-4"
                            /></Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                :aria-label="`Duplicar partida ${i + 1}`"
                                @click="duplicate(i)"
                                ><Copy class="size-4"
                            /></Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                :aria-label="`Eliminar partida ${i + 1}`"
                                @click="removeLine(i)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                    </li>
                </ol>
                <div v-if="!locked" class="border-t px-4 py-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addLine()"
                        ><Plus class="size-4" aria-hidden="true" /> Agregar
                        partida</Button
                    >
                </div>
            </SectionCard>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <SectionCard title="Condiciones">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="q-inc">Incluye</Label>
                            <Textarea
                                id="q-inc"
                                v-model="form.inclusions"
                                rows="5"
                                :disabled="locked"
                                placeholder="Fabricación, flete e instalación…"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="q-exc">No incluye</Label>
                            <Textarea
                                id="q-exc"
                                v-model="form.exclusions"
                                rows="5"
                                :disabled="locked"
                                placeholder="Obra civil, plomería…"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="q-notes">Notas para el cliente</Label>
                            <Textarea
                                id="q-notes"
                                v-model="form.notes"
                                rows="4"
                                :disabled="locked"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="q-terms">Términos y condiciones</Label>
                            <Textarea
                                id="q-terms"
                                v-model="form.terms"
                                rows="4"
                                :disabled="locked"
                                placeholder="Forma de pago, tiempos de entrega, garantía…"
                            />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard title="Totales">
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="tabular-nums">
                                {{ fmt(preview.subtotal) }}
                            </dd>
                        </div>
                        <div v-if="hasDiscount" class="flex justify-between">
                            <dt class="text-muted-foreground">Descuentos</dt>
                            <dd class="tabular-nums">
                                −{{ fmt(preview.discount) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">
                                IVA {{ includeTax ? '(incluido)' : '' }}
                            </dt>
                            <dd class="tabular-nums">{{ fmt(preview.tax) }}</dd>
                        </div>
                        <div
                            class="mt-1 flex items-baseline justify-between border-t pt-3"
                        >
                            <dt class="font-semibold">Total</dt>
                            <dd
                                class="font-display text-2xl font-semibold tabular-nums"
                            >
                                {{ fmt(preview.total) }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Vista previa. Al guardar, el servidor recalcula con
                        precisión exacta y esos son los importes oficiales.
                    </p>
                    <dl
                        v-if="quote"
                        class="mt-4 flex flex-col gap-1 rounded-lg bg-muted/60 p-3 text-xs"
                    >
                        <div class="flex justify-between">
                            <dt>Total guardado</dt>
                            <dd>
                                <Money
                                    :value="quote.total"
                                    class="font-semibold"
                                />
                            </dd>
                        </div>
                        <div
                            v-if="quote.discount_authorized_by"
                            class="flex justify-between"
                        >
                            <dt>Descuento autorizado por</dt>
                            <dd>{{ quote.discount_authorized_by }}</dd>
                        </div>
                    </dl>
                </SectionCard>
            </div>

            <div
                v-if="!locked"
                class="sticky bottom-3 z-20 rounded-xl border bg-card/95 shadow-lg backdrop-blur md:bottom-4"
            >
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <p class="text-sm text-muted-foreground">
                        <span v-if="form.isDirty">Cambios sin guardar</span>
                        <span v-else-if="quote">Todo guardado</span>
                        <span v-else>Borrador nuevo</span>
                    </p>
                    <Button
                        type="submit"
                        :disabled="
                            form.processing || (quote !== null && !form.isDirty)
                        "
                    >
                        {{
                            form.processing
                                ? 'Guardando…'
                                : createsVersion
                                  ? 'Guardar como nueva versión'
                                  : 'Guardar borrador'
                        }}
                    </Button>
                </div>
            </div>
        </form>

        <ConfirmDialog
            v-model:open="sendOpen"
            title="¿Enviar la cotización al cliente?"
            :description="`La verá en su portal con el total de ${quote ? formatMoney(quote.total) : ''} y podrá aceptarla. Para cambiarla después se creará una nueva versión.`"
            confirm-label="Enviar"
            loading-label="Enviando…"
            :confirm="send"
        />

        <FormSheet
            v-model:open="rejectOpen"
            title="Marcar como rechazada"
            description="Úsalo cuando el cliente la rechazó por teléfono o en persona."
        >
            <form
                id="reject-form"
                class="flex flex-col gap-4"
                @submit.prevent="submitReject"
            >
                <div class="grid gap-1.5">
                    <Label for="rej-reason">Motivo</Label>
                    <Textarea
                        id="rej-reason"
                        v-model="rejectForm.reason"
                        rows="4"
                        maxlength="500"
                    />
                    <InputError
                        :message="
                            rejectForm.errors.reason ||
                            (rejectForm.errors as Record<string, string>).status
                        "
                    />
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="rejectOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="reject-form"
                    variant="destructive"
                    :disabled="
                        !rejectForm.reason.trim() || rejectForm.processing
                    "
                    >Marcar rechazada</Button
                >
            </template>
        </FormSheet>
    </PageContainer>
</template>
