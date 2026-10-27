<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    Check,
    ClipboardCheck,
    HandCoins,
    Lock,
    MessageSquareQuote,
    Pencil,
    Plus,
    Search,
    ShieldAlert,
    Sparkles,
    Trash2,
    UserRound,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import ProgressSteps from '@/components/ds/ProgressSteps.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import MediaGrid from '@/components/operations/MediaGrid.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import calidad from '@/routes/admin/calidad';
import instalaciones from '@/routes/admin/instalaciones';
import type {
    EnumOption,
    InstallationDetail,
    InstallationItem,
    RateCardItemRow,
} from '@/types/operations';

const props = defineProps<{
    order: InstallationDetail;
    incidents: {
        id: number;
        folio: string;
        title: string;
        status: EnumOption;
        severity: EnumOption;
        origin: EnumOption;
    }[];
    rateItems: RateCardItemRow[];
    installers: { value: number; label: string }[];
    can: {
        manage: boolean;
        decideExtras: boolean;
        requestPayout: boolean;
        review: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Instalaciones', href: '/admin/instalaciones' },
            { title: 'Orden', href: '#' },
        ],
    },
});

const TZ = 'America/Mexico_City';

function send(
    method: 'post' | 'put' | 'delete',
    url: string,
    data: Record<string, unknown> = {},
): Promise<void> {
    return new Promise((resolve, reject) => {
        router.visit(url, {
            method,
            data: data as never,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) =>
                reject(
                    new Error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                ),
        });
    });
}

const status = computed(() => props.order.status.value);
const editable = computed(
    () => props.can.manage && ['draft', 'scheduled'].includes(status.value),
);
const closed = computed(() =>
    ['approved', 'payment_authorized', 'paid', 'cancelled'].includes(
        status.value,
    ),
);

const regularItems = computed(() =>
    props.order.items.filter((i) => !i.isExtra),
);
const extras = computed(() => props.order.items.filter((i) => i.isExtra));
const pendingExtras = computed(() =>
    extras.value.filter((i) => i.pendingExtra),
);
const doneCount = computed(
    () =>
        props.order.items.filter(
            (i) => i.countsTowardsTotal && i.status.value === 'done',
        ).length,
);
const countable = computed(
    () => props.order.items.filter((i) => i.countsTowardsTotal).length,
);

const steps = [
    { key: 'scheduled', title: 'Programada' },
    { key: 'in_progress', title: 'Instalación' },
    { key: 'client_review', title: 'Revisión del cliente' },
    { key: 'quality_review', title: 'Calidad' },
    { key: 'approved', title: 'Aprobada' },
];

// ---------------------------------------------------------------- Programar

function partsIn(iso: string | null): { date: string | null; time: string } {
    if (!iso) {
        return { date: null, time: '' };
    }

    const d = new Date(iso);

    return {
        date: d.toLocaleDateString('en-CA', { timeZone: TZ }),
        time: d.toLocaleTimeString('en-GB', {
            timeZone: TZ,
            hour: '2-digit',
            minute: '2-digit',
        }),
    };
}

const scheduleOpen = ref(false);
const scheduleForm = useForm({
    date: null as string | null,
    start_time: '09:00',
    end_date: null as string | null,
    end_time: '14:00',
});
const today = new Date().toLocaleDateString('en-CA', { timeZone: TZ });

function openSchedule() {
    const start = partsIn(props.order.scheduledStart);
    const end = partsIn(props.order.scheduledEnd);
    scheduleForm.clearErrors();
    scheduleForm.date = start.date;
    scheduleForm.start_time = start.time || '09:00';
    scheduleForm.end_date =
        end.date && end.date !== start.date ? end.date : null;
    scheduleForm.end_time = end.time || '14:00';
    scheduleOpen.value = true;
}

function submitSchedule() {
    scheduleForm.post(instalaciones.programar(props.order.id).url, {
        preserveScroll: true,
        onSuccess: () => (scheduleOpen.value = false),
    });
}

const scheduleBlocked = computed(() => {
    if (!props.order.leadInstaller) {
        return 'Asigna un instalador líder antes de programar.';
    }

    return props.order.items.length === 0
        ? 'Agrega al menos una partida antes de programar.'
        : null;
});

// ---------------------------------------------------------------- Asignación

const assignOpen = ref(false);
const assignForm = useForm({
    lead_installer_id: null as number | null,
    notes: '',
});

function openAssign() {
    assignForm.clearErrors();
    assignForm.lead_installer_id = props.order.leadInstaller?.id ?? null;
    assignForm.notes = props.order.notes ?? '';
    assignOpen.value = true;
}

function submitAssign() {
    assignForm.put(instalaciones.update(props.order.id).url, {
        preserveScroll: true,
        onSuccess: () => (assignOpen.value = false),
        onError: (errors) => errors.status && toast.error(errors.status),
    });
}

// ---------------------------------------------------------------- Partidas

const qtyDraft = ref<Record<number, string>>({});

function saveQuantity(item: InstallationItem) {
    const value = (qtyDraft.value[item.id] ?? '').trim();

    if (value === '' || value === item.quantity) {
        delete qtyDraft.value[item.id];

        return;
    }

    router.put(
        instalaciones.partidas.update([props.order.id, item.id]).url,
        { quantity: value },
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo actualizar la cantidad.',
                ),
            onFinish: () => delete qtyDraft.value[item.id],
        },
    );
}

const removeOpen = ref(false);
const removing = ref<InstallationItem | null>(null);
function askRemove(item: InstallationItem) {
    removing.value = item;
    removeOpen.value = true;
}
const confirmRemove = () =>
    send(
        'delete',
        instalaciones.partidas.destroy([props.order.id, removing.value!.id])
            .url,
    );

const addOpen = ref(false);
const addForm = useForm({
    items: [] as { rate_card_item_id: number; quantity: string }[],
});
const addQty = ref<Record<number, string>>({});
const addSearch = ref('');
const addVisible = computed(() => {
    const term = addSearch.value.trim().toLowerCase();

    return term
        ? props.rateItems.filter((i) =>
              `${i.code} ${i.description}`.toLowerCase().includes(term),
          )
        : props.rateItems;
});
const addCount = computed(
    () => Object.values(addQty.value).filter((q) => Number(q) > 0).length,
);

function openAdd() {
    addQty.value = {};
    addSearch.value = '';
    addForm.clearErrors();
    addOpen.value = true;
}

function submitAdd() {
    addForm
        .transform(() => ({
            items: Object.entries(addQty.value)
                .filter(([, q]) => Number(q) > 0)
                .map(([id, quantity]) => ({
                    rate_card_item_id: Number(id),
                    quantity: quantity.trim(),
                })),
        }))
        .post(instalaciones.partidas.store(props.order.id).url, {
            preserveScroll: true,
            onSuccess: () => (addOpen.value = false),
        });
}

// ---------------------------------------------------------------- Extras

const extraOpen = ref(false);
const extraForm = useForm({
    rate_card_item_id: null as number | null,
    description: '',
    unit: 'pieza',
    quantity: '1',
    unit_price: '',
});
const rateItemOptions = computed(() =>
    props.rateItems.map((i) => ({
        value: i.id,
        label: `${i.code} · ${i.description}`,
        description: `$${i.unitPrice} por ${i.unit}`,
    })),
);

function openExtra() {
    extraForm.reset();
    extraForm.clearErrors();
    extraOpen.value = true;
}

function onExtraConcept(id: number | null) {
    extraForm.rate_card_item_id = id;
    const item = props.rateItems.find((i) => i.id === id);

    if (item) {
        extraForm.description = item.description;
        extraForm.unit = item.unit;
        extraForm.unit_price = '';
    }
}

function submitExtra() {
    extraForm.post(instalaciones.extras.store(props.order.id).url, {
        preserveScroll: true,
        onSuccess: () => (extraOpen.value = false),
    });
}

const decideOpen = ref(false);
const deciding = ref<{ item: InstallationItem; approve: boolean } | null>(null);
function askDecide(item: InstallationItem, approve: boolean) {
    deciding.value = { item, approve };
    decideOpen.value = true;
}
const confirmDecide = () => {
    const d = deciding.value!;
    const route = d.approve
        ? instalaciones.extras.aprobar
        : instalaciones.extras.rechazar;

    return send('post', route([props.order.id, d.item.id]).url);
};

// ---------------------------------------------------------------- Acciones de estado

const cancelOpen = ref(false);
const cancelReason = ref('');
const confirmCancel = () => {
    if (!cancelReason.value.trim()) {
        return Promise.reject(
            new Error('Escribe el motivo de la cancelación.'),
        );
    }

    return send('post', instalaciones.cancelar(props.order.id).url, {
        reason: cancelReason.value,
    });
};

const skipOpen = ref(false);
const skipReason = ref('');
const confirmSkip = () => {
    if (!skipReason.value.trim()) {
        return Promise.reject(
            new Error('Explica por qué se revisa sin respuesta del cliente.'),
        );
    }

    return send('post', instalaciones.enviarACalidad(props.order.id).url, {
        reason: skipReason.value,
    });
};

const payoutOpen = ref(false);
const confirmPayout = () =>
    send('post', instalaciones.pago(props.order.id).url);
</script>

<template>
    <Head :title="`Instalación ${order.folio}`" />

    <PageContainer>
        <PageHeader
            :title="order.folio"
            :description="`${order.project.name}${order.project.customer ? ' · ' + order.project.customer : ''}`"
        >
            <template #meta>
                <StatusBadge
                    :tone="order.status.tone"
                    :label="order.status.label"
                />
                <span>{{ order.project.folio }}</span>
                <span v-if="order.rateCard"
                    >Tarifario {{ order.rateCard.name }} v{{
                        order.rateCard.version
                    }}</span
                >
            </template>
            <template #actions>
                <Button
                    v-if="
                        can.manage &&
                        [
                            'draft',
                            'scheduled',
                            'in_progress',
                            'evidence_submitted',
                            'client_review',
                            'quality_review',
                        ].includes(status)
                    "
                    variant="ghost"
                    class="text-destructive"
                    @click="cancelOpen = true"
                >
                    Cancelar orden
                </Button>
                <Button
                    v-if="can.manage && status === 'client_review'"
                    variant="outline"
                    @click="skipOpen = true"
                    >Pasar a calidad sin respuesta</Button
                >
                <Button
                    v-if="can.review && status === 'quality_review'"
                    as-child
                >
                    <Link :href="calidad.show(order.id).url"
                        ><ClipboardCheck class="size-4" /> Revisar calidad</Link
                    >
                </Button>
                <Button v-if="can.requestPayout" @click="payoutOpen = true"
                    ><HandCoins class="size-4" /> Solicitar pago</Button
                >
                <Button v-if="editable" @click="openSchedule">
                    <CalendarClock class="size-4" />
                    {{ status === 'scheduled' ? 'Reprogramar' : 'Programar' }}
                </Button>
            </template>
        </PageHeader>

        <ProgressSteps
            v-if="status !== 'cancelled' && status !== 'draft'"
            :steps="steps"
            :current="order.step"
            :completed="order.step === 'approved'"
            label="Avance de la instalación"
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <!-- Partidas -->
                <SectionCard
                    title="Partidas"
                    :description="`${doneCount} de ${countable} terminadas con evidencia`"
                    flush
                >
                    <template v-if="editable" #actions>
                        <Button size="sm" variant="outline" @click="openAdd"
                            ><Plus class="size-4" /> Agregar partidas</Button
                        >
                    </template>
                    <EmptyState
                        v-if="regularItems.length === 0"
                        compact
                        :bordered="false"
                        title="Sin partidas"
                        description="Agrega los módulos a instalar desde el tarifario de la orden."
                    />
                    <ul v-else class="divide-y">
                        <li
                            v-for="item in regularItems"
                            :key="item.id"
                            class="flex flex-col gap-3 px-4 py-3.5 sm:px-6"
                        >
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm">
                                        <span class="font-semibold">{{
                                            item.code
                                        }}</span>
                                        · {{ item.description }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        <Money :value="item.unitPrice" /> por
                                        {{ item.unit }}
                                        <template v-if="item.installerCheck">
                                            · Autoevaluación
                                            <DateText
                                                :value="
                                                    item.installerCheck
                                                        .inspectedAt
                                                "
                                                format="datetime"
                                        /></template>
                                    </p>
                                </div>
                                <StatusBadge
                                    size="sm"
                                    :tone="item.status.tone"
                                    :label="item.status.label"
                                />
                            </div>
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-2">
                                    <template v-if="editable">
                                        <label
                                            :for="`q-${item.id}`"
                                            class="text-xs text-muted-foreground"
                                            >Cantidad</label
                                        >
                                        <Input
                                            :id="`q-${item.id}`"
                                            :model-value="
                                                qtyDraft[item.id] ??
                                                item.quantity
                                            "
                                            inputmode="decimal"
                                            class="h-9 w-24 text-right tabular-nums"
                                            @update:model-value="
                                                qtyDraft[item.id] =
                                                    String($event)
                                            "
                                            @blur="saveQuantity(item)"
                                            @keydown.enter.prevent="
                                                saveQuantity(item)
                                            "
                                        />
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{ item.unit }}</span
                                        >
                                    </template>
                                    <span v-else class="text-sm tabular-nums"
                                        >{{ Number(item.quantity) }}
                                        {{ item.unit }}</span
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <Money
                                        :value="item.lineTotal"
                                        class="font-medium tabular-nums"
                                    />
                                    <Button
                                        v-if="editable"
                                        size="icon"
                                        variant="ghost"
                                        :aria-label="`Quitar ${item.code}`"
                                        @click="askRemove(item)"
                                    >
                                        <Trash2
                                            class="size-4 text-destructive"
                                        />
                                    </Button>
                                </div>
                            </div>
                            <MediaGrid
                                v-if="item.evidence.length"
                                :items="item.evidence"
                                size="sm"
                            />
                        </li>
                    </ul>
                </SectionCard>

                <!-- Extras -->
                <SectionCard title="Trabajos extra" :icon="Sparkles" flush>
                    <template v-if="can.manage && !closed" #actions>
                        <Button size="sm" variant="outline" @click="openExtra"
                            ><Plus class="size-4" /> Registrar extra</Button
                        >
                    </template>
                    <p
                        class="border-b px-4 pb-3 text-xs text-muted-foreground sm:px-6"
                    >
                        Un extra solo suma al total cuando lo aprueba alguien
                        distinto al instalador líder.
                    </p>
                    <p
                        v-if="extras.length === 0"
                        class="px-4 py-5 text-sm text-muted-foreground sm:px-6"
                    >
                        Sin trabajos extra en esta orden.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="item in extras"
                            :key="item.id"
                            :class="
                                cn(
                                    'flex flex-col gap-2 px-4 py-3.5 sm:px-6',
                                    item.pendingExtra && 'bg-warning-soft/40',
                                )
                            "
                        >
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm">
                                        <span class="font-semibold">{{
                                            item.code
                                        }}</span>
                                        · {{ item.description }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ Number(item.quantity) }}
                                        {{ item.unit }} ×
                                        <Money :value="item.unitPrice" /> =
                                        <Money
                                            :value="item.lineTotal"
                                            class="font-medium text-foreground"
                                        />
                                    </p>
                                </div>
                                <StatusBadge
                                    v-if="item.pendingExtra"
                                    size="sm"
                                    tone="warning"
                                    label="Por aprobar"
                                />
                                <StatusBadge
                                    v-else-if="item.extraApproved"
                                    size="sm"
                                    tone="success"
                                    label="Aprobado"
                                />
                                <StatusBadge
                                    v-else
                                    size="sm"
                                    :tone="item.status.tone"
                                    :label="item.status.label"
                                />
                            </div>
                            <div
                                v-if="item.pendingExtra && can.decideExtras"
                                class="flex gap-2"
                            >
                                <Button size="sm" @click="askDecide(item, true)"
                                    ><Check class="size-4" /> Aprobar</Button
                                >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="askDecide(item, false)"
                                    ><X class="size-4" /> Rechazar</Button
                                >
                            </div>
                            <p
                                v-else-if="item.pendingExtra && can.manage"
                                class="text-xs text-muted-foreground"
                            >
                                Eres el instalador líder de esta orden: otra
                                persona debe aprobar este extra.
                            </p>
                            <MediaGrid
                                v-if="item.evidence.length"
                                :items="item.evidence"
                                size="sm"
                            />
                        </li>
                    </ul>
                </SectionCard>

                <!-- Cliente -->
                <SectionCard
                    v-if="order.clientConformity || order.clientPhotos.length"
                    title="Respuesta del cliente"
                    :icon="MessageSquareQuote"
                >
                    <div class="flex flex-col gap-3 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                v-if="order.clientConformity"
                                :tone="order.clientConformity.tone"
                                :label="order.clientConformity.label"
                            />
                            <span class="text-muted-foreground"
                                ><DateText
                                    :value="order.clientSignedAt"
                                    format="datetime"
                            /></span>
                        </div>
                        <blockquote
                            v-if="order.clientComment"
                            class="border-l-2 border-primary/40 pl-3 whitespace-pre-line"
                        >
                            {{ order.clientComment }}
                        </blockquote>
                        <MediaGrid
                            v-if="order.clientPhotos.length"
                            :items="order.clientPhotos"
                            size="sm"
                        />
                    </div>
                </SectionCard>

                <SectionCard
                    v-if="order.qualityReview"
                    title="Dictamen de calidad"
                    :icon="ClipboardCheck"
                >
                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                :tone="order.qualityReview.result.tone"
                                :label="order.qualityReview.result.label"
                            />
                            <span class="text-muted-foreground"
                                >{{ order.qualityReview.inspector?.name }} ·
                                <DateText
                                    :value="order.qualityReview.inspectedAt"
                                    format="datetime"
                            /></span>
                        </div>
                        <p
                            v-if="order.qualityReview.observations"
                            class="whitespace-pre-line"
                        >
                            {{ order.qualityReview.observations }}
                        </p>
                    </div>
                </SectionCard>

                <SectionCard title="Incidencias" :icon="ShieldAlert">
                    <p
                        v-if="incidents.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sin incidencias en esta orden.
                    </p>
                    <ul v-else class="-my-2 divide-y">
                        <li v-for="incident in incidents" :key="incident.id">
                            <Link
                                :href="`/admin/incidencias/${incident.id}`"
                                class="flex items-center justify-between gap-3 py-2.5 hover:text-primary"
                            >
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-sm font-medium"
                                        >{{ incident.title }}</span
                                    >
                                    <span class="text-xs text-muted-foreground"
                                        >{{ incident.folio }} · Origen:
                                        {{ incident.origin.label }}</span
                                    >
                                </span>
                                <span class="flex shrink-0 gap-1.5">
                                    <StatusBadge
                                        size="sm"
                                        :tone="incident.severity.tone"
                                        :label="incident.severity.label"
                                        :dot="false"
                                    />
                                    <StatusBadge
                                        size="sm"
                                        :tone="incident.status.tone"
                                        :label="incident.status.label"
                                    />
                                </span>
                            </Link>
                        </li>
                    </ul>
                </SectionCard>
            </div>

            <aside class="flex flex-col gap-6">
                <SectionCard title="Total de la orden">
                    <dl class="grid gap-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">
                                Partidas y extras aprobados
                            </dt>
                            <dd><Money :value="order.subtotal" /></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Movilización</dt>
                            <dd><Money :value="order.mobilizationFee" /></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Cargo mínimo</dt>
                            <dd><Money :value="order.minimumCharge" /></dd>
                        </div>
                        <div
                            class="mt-1 flex items-baseline justify-between gap-3 border-t pt-3"
                        >
                            <dt class="font-medium">Total</dt>
                            <dd>
                                <Money
                                    :value="order.total"
                                    class="font-display text-2xl font-semibold tracking-tight"
                                />
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="order.minimumApplied"
                        class="mt-3 rounded-lg bg-info-soft px-3 py-2 text-xs text-info-ink"
                    >
                        Se cobra el cargo mínimo: las partidas más la
                        movilización no lo alcanzan.
                    </p>
                    <p
                        class="mt-3 flex items-start gap-1.5 text-xs text-muted-foreground"
                    >
                        <Lock
                            class="mt-0.5 size-3 shrink-0"
                            aria-hidden="true"
                        />
                        Precios congelados del tarifario al crear la orden.
                    </p>
                </SectionCard>

                <SectionCard title="Equipo y fecha" :icon="UserRound">
                    <template v-if="can.manage && !closed" #actions>
                        <Button size="sm" variant="ghost" @click="openAssign"
                            ><Pencil class="size-4" /> Editar</Button
                        >
                    </template>
                    <dl class="grid gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Instalador líder
                            </dt>
                            <dd
                                :class="
                                    !order.leadInstaller && 'text-warning-ink'
                                "
                            >
                                {{ order.leadInstaller?.name ?? 'Sin asignar' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Proveedor
                            </dt>
                            <dd>{{ order.supplier.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Programada
                            </dt>
                            <dd>
                                <template v-if="order.scheduledStart">
                                    <DateText
                                        :value="order.scheduledStart"
                                        format="full"
                                    /><br />
                                    <span class="text-muted-foreground"
                                        ><DateText
                                            :value="order.scheduledStart"
                                            format="time" />
                                        a
                                        <DateText
                                            :value="order.scheduledEnd"
                                            format="time"
                                    /></span>
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >Sin fecha</span
                                >
                            </dd>
                        </div>
                        <div v-if="order.address">
                            <dt class="text-xs text-muted-foreground">
                                Domicilio
                            </dt>
                            <dd>{{ order.address }}</dd>
                            <dd
                                v-if="order.addressReferences"
                                class="text-xs text-muted-foreground"
                            >
                                {{ order.addressReferences }}
                            </dd>
                        </div>
                        <div v-if="order.startedAt">
                            <dt class="text-xs text-muted-foreground">
                                Inició
                            </dt>
                            <dd>
                                <DateText
                                    :value="order.startedAt"
                                    format="datetime"
                                />
                            </dd>
                        </div>
                        <div v-if="order.notes">
                            <dt class="text-xs text-muted-foreground">
                                Notas internas
                            </dt>
                            <dd class="whitespace-pre-line">
                                {{ order.notes }}
                            </dd>
                        </div>
                    </dl>
                </SectionCard>

                <SectionCard
                    v-if="order.payout"
                    title="Pago al proveedor"
                    :icon="HandCoins"
                >
                    <div class="flex flex-col gap-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium">{{
                                order.payout.folio
                            }}</span>
                            <StatusBadge
                                :tone="order.payout.status.tone"
                                :label="order.payout.status.label"
                            />
                        </div>
                        <Money
                            :value="order.payout.amount"
                            class="text-lg font-semibold"
                        />
                        <Link
                            href="/admin/pagos-proveedores"
                            class="text-primary underline-offset-4 hover:underline"
                            >Ver en pagos a proveedores</Link
                        >
                    </div>
                </SectionCard>
            </aside>
        </div>
    </PageContainer>

    <!-- Programar -->
    <FormSheet
        v-model:open="scheduleOpen"
        title="Programar instalación"
        description="Avisaremos al cliente con la fecha y el horario."
    >
        <form
            id="schedule-form"
            class="flex flex-col gap-5"
            @submit.prevent="submitSchedule"
        >
            <p
                v-if="scheduleBlocked"
                class="rounded-lg border border-warning/30 bg-warning-soft px-3 py-2.5 text-sm text-warning-ink"
            >
                {{ scheduleBlocked }}
            </p>
            <div class="grid gap-2">
                <Label for="sch-date">Día</Label>
                <DatePicker
                    id="sch-date"
                    v-model="scheduleForm.date"
                    :min="today"
                    :invalid="!!scheduleForm.errors.date"
                />
                <InputError
                    :message="
                        scheduleForm.errors.date ??
                        (
                            scheduleForm.errors as Record<
                                string,
                                string | undefined
                            >
                        ).scheduled_start
                    "
                />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="sch-start">Llegada</Label>
                    <Input
                        id="sch-start"
                        v-model="scheduleForm.start_time"
                        type="time"
                        step="900"
                        class="h-11"
                    />
                    <InputError :message="scheduleForm.errors.start_time" />
                </div>
                <div class="grid gap-2">
                    <Label for="sch-end">Término estimado</Label>
                    <Input
                        id="sch-end"
                        v-model="scheduleForm.end_time"
                        type="time"
                        step="900"
                        class="h-11"
                    />
                    <InputError :message="scheduleForm.errors.end_time" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="sch-end-date">Termina otro día (opcional)</Label>
                <DatePicker
                    id="sch-end-date"
                    v-model="scheduleForm.end_date"
                    :min="scheduleForm.date ?? today"
                    clearable
                    placeholder="Mismo día"
                />
                <InputError :message="scheduleForm.errors.end_date" />
            </div>
            <InputError
                :message="
                    (scheduleForm.errors as Record<string, string | undefined>)
                        .status ??
                    (scheduleForm.errors as Record<string, string | undefined>)
                        .lead_installer_id ??
                    (scheduleForm.errors as Record<string, string | undefined>)
                        .items
                "
            />
        </form>
        <template #footer>
            <Button
                variant="outline"
                type="button"
                @click="scheduleOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="schedule-form"
                :disabled="
                    scheduleForm.processing ||
                    !scheduleForm.date ||
                    !!scheduleBlocked
                "
            >
                <Spinner v-if="scheduleForm.processing" /> Guardar fecha
            </Button>
        </template>
    </FormSheet>

    <!-- Asignación -->
    <FormSheet
        v-model:open="assignOpen"
        title="Equipo y notas"
        :description="order.folio"
    >
        <form
            id="assign-form"
            class="flex flex-col gap-5"
            @submit.prevent="submitAssign"
        >
            <div class="grid gap-2">
                <Label for="as-lead">Instalador líder</Label>
                <SearchSelect
                    id="as-lead"
                    v-model="assignForm.lead_installer_id"
                    :options="installers"
                    clearable
                    :disabled="!['draft', 'scheduled'].includes(status)"
                    placeholder="Elige un técnico"
                />
                <p
                    v-if="!['draft', 'scheduled'].includes(status)"
                    class="text-xs text-muted-foreground"
                >
                    El líder solo cambia antes de iniciar la instalación.
                </p>
                <InputError :message="assignForm.errors.lead_installer_id" />
            </div>
            <div class="grid gap-2">
                <Label for="as-notes">Notas internas</Label>
                <Textarea id="as-notes" v-model="assignForm.notes" rows="4" />
                <InputError :message="assignForm.errors.notes" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="assignOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="assign-form"
                :disabled="assignForm.processing"
                ><Spinner v-if="assignForm.processing" /> Guardar</Button
            >
        </template>
    </FormSheet>

    <!-- Agregar partidas -->
    <FormSheet
        v-model:open="addOpen"
        title="Agregar partidas"
        description="Del mismo tarifario de la orden, con sus precios congelados."
        size="lg"
    >
        <form
            id="add-items"
            class="flex flex-col gap-3"
            @submit.prevent="submitAdd"
        >
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="addSearch"
                    type="search"
                    class="pl-8"
                    placeholder="Buscar por clave o descripción"
                />
            </div>
            <ul class="divide-y rounded-xl border">
                <li
                    v-for="item in addVisible"
                    :key="item.id"
                    class="flex items-center gap-3 px-3 py-2.5"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm">
                            <span class="font-medium">{{ item.code }}</span> ·
                            {{ item.description }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <Money :value="item.unitPrice" /> por
                            {{ item.unit }}
                        </p>
                    </div>
                    <label class="sr-only" :for="`add-${item.id}`"
                        >Cantidad de {{ item.code }}</label
                    >
                    <Input
                        :id="`add-${item.id}`"
                        v-model="addQty[item.id]"
                        inputmode="decimal"
                        placeholder="0"
                        class="h-10 w-24 text-right tabular-nums"
                    />
                </li>
                <li
                    v-if="addVisible.length === 0"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    Ningún concepto coincide.
                </li>
            </ul>
            <InputError
                v-for="(message, key) in addForm.errors"
                :key="key"
                :message="message"
            />
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="addOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="add-items"
                :disabled="addForm.processing || addCount === 0"
                ><Spinner v-if="addForm.processing" /> Agregar
                {{ addCount || '' }}</Button
            >
        </template>
    </FormSheet>

    <!-- Extra -->
    <FormSheet
        v-model:open="extraOpen"
        title="Registrar trabajo extra"
        description="Queda pendiente hasta que alguien distinto al instalador líder lo apruebe."
    >
        <form
            id="extra-form"
            class="flex flex-col gap-5"
            @submit.prevent="submitExtra"
        >
            <div class="grid gap-2">
                <Label for="ex-concept"
                    >Concepto del tarifario (opcional)</Label
                >
                <SearchSelect
                    id="ex-concept"
                    :model-value="extraForm.rate_card_item_id"
                    :options="rateItemOptions"
                    clearable
                    placeholder="Usar precio del tarifario"
                    @update:model-value="
                        onExtraConcept($event as number | null)
                    "
                />
                <InputError :message="extraForm.errors.rate_card_item_id" />
            </div>
            <div class="grid gap-2">
                <Label for="ex-desc">Descripción</Label>
                <Input
                    id="ex-desc"
                    v-model="extraForm.description"
                    placeholder="Ej. Recorte de zoclo existente"
                />
                <InputError :message="extraForm.errors.description" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="ex-qty">Cantidad</Label>
                    <Input
                        id="ex-qty"
                        v-model="extraForm.quantity"
                        inputmode="decimal"
                    />
                    <InputError :message="extraForm.errors.quantity" />
                </div>
                <div class="grid gap-2">
                    <Label for="ex-unit">Unidad</Label>
                    <Input id="ex-unit" v-model="extraForm.unit" />
                    <InputError :message="extraForm.errors.unit" />
                </div>
            </div>
            <div v-if="!extraForm.rate_card_item_id" class="grid gap-2">
                <Label for="ex-price">Precio unitario</Label>
                <Input
                    id="ex-price"
                    v-model="extraForm.unit_price"
                    inputmode="decimal"
                    placeholder="0.00"
                />
                <InputError :message="extraForm.errors.unit_price" />
            </div>
            <InputError
                :message="
                    (extraForm.errors as Record<string, string | undefined>)
                        .status
                "
            />
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="extraOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="extra-form"
                :disabled="extraForm.processing"
                ><Spinner v-if="extraForm.processing" /> Registrar extra</Button
            >
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="decideOpen"
        :title="deciding?.approve ? 'Aprobar extra' : 'Rechazar extra'"
        :description="
            deciding
                ? `${deciding.item.code} · ${deciding.item.description}. ${deciding.approve ? 'Sumará al total de la orden.' : 'No se cobrará.'}`
                : ''
        "
        :confirm-label="deciding?.approve ? 'Aprobar extra' : 'Rechazar extra'"
        :tone="deciding?.approve ? 'default' : 'destructive'"
        :confirm="confirmDecide"
    />

    <ConfirmDialog
        v-model:open="removeOpen"
        title="Quitar partida"
        :description="
            removing
                ? `Se quitará ${removing.code} de la orden y se recalculará el total.`
                : ''
        "
        confirm-label="Quitar partida"
        tone="destructive"
        :confirm="confirmRemove"
    />

    <ConfirmDialog
        v-model:open="cancelOpen"
        title="Cancelar orden"
        confirm-label="Cancelar orden"
        cancel-label="Volver"
        tone="destructive"
        :confirm="confirmCancel"
    >
        <div class="flex flex-col gap-2">
            <p>
                La orden dejará de contar para el proyecto. No se puede
                deshacer.
            </p>
            <Label for="cancel-reason">Motivo</Label>
            <Textarea
                id="cancel-reason"
                v-model="cancelReason"
                rows="3"
                maxlength="500"
            />
        </div>
    </ConfirmDialog>

    <ConfirmDialog
        v-model:open="skipOpen"
        title="Pasar a calidad sin respuesta del cliente"
        confirm-label="Enviar a calidad"
        :confirm="confirmSkip"
    >
        <div class="flex flex-col gap-2">
            <p>
                Úsalo solo si intentaste contactar al cliente y no respondió. El
                motivo queda en la bitácora.
            </p>
            <Label for="skip-reason">Motivo</Label>
            <Textarea
                id="skip-reason"
                v-model="skipReason"
                rows="3"
                maxlength="500"
                placeholder="Ej. Tres llamadas y dos mensajes sin respuesta en 5 días."
            />
        </div>
    </ConfirmDialog>

    <ConfirmDialog
        v-model:open="payoutOpen"
        title="Solicitar pago al proveedor"
        :description="`Se enviará a autorización el pago de ${order.folio} por el total de la orden. Lo autoriza alguien distinto a quien lo solicita y al instalador líder.`"
        confirm-label="Solicitar pago"
        :confirm="confirmPayout"
    />
</template>
