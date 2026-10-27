<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlarmClock,
    BadgeCheck,
    FileText,
    HandCoins,
    Pencil,
    ShieldAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import FileDropzone from '@/components/ds/FileDropzone.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import KeyValue from '@/components/ds/KeyValue.vue';
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
import type { UploadedMediaResult } from '@/composables/useChunkedUpload';
import { usePermissions } from '@/lib/permissions';
import fabricacion from '@/routes/admin/fabricacion';
import type {
    EnumOption,
    FabricationRow,
    MediaItem,
    PayoutRow,
} from '@/types/operations';

type Order = FabricationRow & {
    createdBy: string | null;
    design: {
        id: number;
        number: number;
        title: string;
        technicalApprovedAt: string | null;
        technicalNotes: string | null;
    } | null;
    documents: MediaItem[];
    incidents: {
        id: number;
        folio: string;
        title: string;
        status: EnumOption;
        severity: EnumOption;
    }[];
    payout: PayoutRow | null;
};

const props = defineProps<{
    order: Order;
    options: {
        statuses: EnumOption[];
        suppliers: {
            value: number;
            label: string;
            leadTimeDays: number | null;
        }[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Fabricación', href: '/admin/fabricacion' },
            { title: 'Orden', href: '#' },
        ],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('fabrication.manage'));

/** Promesa sobre una visita de Inertia (para ConfirmDialog). */
function send(url: string, data: Record<string, unknown> = {}): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(url, data as never, {
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

// ---------------------------------------------------------------- Estado

const VERBS: Record<string, { label: string; description: string }> = {
    issued: {
        label: 'Emitir al proveedor',
        description:
            'La orden se envía al fabricante. Si el proyecto está autorizado, pasa a «En fabricación».',
    },
    in_production: {
        label: 'Marcar en producción',
        description: 'El fabricante confirmó que ya está produciendo.',
    },
    ready: {
        label: 'Marcar lista para entrega',
        description:
            'Cuando todas las órdenes del proyecto estén listas, el proyecto pasa a «Listo para instalar».',
    },
    delivered: {
        label: 'Registrar entrega',
        description: 'El mueble se recibió completo.',
    },
    cancelled: {
        label: 'Cancelar orden',
        description:
            'La orden deja de contar para el proyecto. No se puede deshacer.',
    },
};

const steps = [
    { key: 'draft', title: 'Borrador' },
    { key: 'issued', title: 'Emitida' },
    { key: 'in_production', title: 'En producción' },
    { key: 'ready', title: 'Lista' },
    { key: 'delivered', title: 'Entregada' },
];

const forward = computed(() =>
    props.order.nextStatuses.filter((s) => s.value !== 'cancelled'),
);
const canCancel = computed(() =>
    props.order.nextStatuses.some((s) => s.value === 'cancelled'),
);

const transitionOpen = ref(false);
const pending = ref<string | null>(null);
const note = ref('');

function askTransition(status: string) {
    pending.value = status;
    note.value = '';
    transitionOpen.value = true;
}

const confirmTransition = () =>
    send(fabricacion.estado(props.order.id).url, {
        status: pending.value,
        note: note.value || null,
    });

// ---------------------------------------------------------------- Edición

const editOpen = ref(false);
const form = useForm({
    supplier_id: props.order.supplier.id as number | null,
    cost: props.order.cost ?? '',
    due_on: props.order.dueOn,
    notes: props.order.notes ?? '',
});

function openEdit() {
    form.defaults({
        supplier_id: props.order.supplier.id,
        cost: props.order.cost ?? '',
        due_on: props.order.dueOn,
        notes: props.order.notes ?? '',
    });
    form.reset();
    form.clearErrors();
    editOpen.value = true;
}

function submitEdit() {
    form.put(fabricacion.update(props.order.id).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
        onError: (errors) => errors.status && toast.error(errors.status),
    });
}

const closed = computed(() =>
    ['delivered', 'cancelled'].includes(props.order.status.value),
);

// ---------------------------------------------------------------- Documentos

function onUploaded(media: UploadedMediaResult[]) {
    if (media.length === 0) {
        return;
    }

    router.post(
        fabricacion.documentos.store(props.order.id).url,
        { media_ids: media.map((m) => m.id) },
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudieron agregar los documentos.',
                ),
        },
    );
}

const removing = ref<MediaItem | null>(null);
const removeOpen = ref(false);
function askRemove(media: MediaItem) {
    removing.value = media;
    removeOpen.value = true;
}
const confirmRemove = () =>
    new Promise<void>((resolve, reject) => {
        router.delete(
            fabricacion.documentos.destroy([props.order.id, removing.value!.id])
                .url,
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () =>
                    reject(new Error('No se pudo quitar el documento.')),
            },
        );
    });

// ---------------------------------------------------------------- Pago

const payoutOpen = ref(false);
const canRequestPayout = computed(
    () =>
        canManage.value &&
        !props.order.payout &&
        !['draft', 'cancelled'].includes(props.order.status.value),
);
const confirmPayout = () => send(fabricacion.pago(props.order.id).url);

const details = computed(() => [
    {
        key: 'project',
        label: 'Proyecto',
        value: `${props.order.project.folio} · ${props.order.project.name}`,
    },
    { key: 'customer', label: 'Cliente', value: props.order.project.customer },
    { key: 'supplier', label: 'Fabricante', value: props.order.supplier.name },
    { key: 'cost', label: 'Costo acordado' },
    { key: 'dueOn', label: 'Fecha compromiso' },
    { key: 'createdBy', label: 'Creada por', value: props.order.createdBy },
    { key: 'notes', label: 'Notas', value: props.order.notes, full: true },
]);
</script>

<template>
    <Head :title="`Fabricación ${order.folio}`" />

    <PageContainer>
        <PageHeader
            :title="order.folio"
            :description="`${order.project.name} · ${order.supplier.name}`"
        >
            <template #meta>
                <StatusBadge
                    :tone="order.status.tone"
                    :label="order.status.label"
                />
                <StatusBadge
                    v-if="order.overdue"
                    tone="danger"
                    :icon="AlarmClock"
                    label="Compromiso vencido"
                />
            </template>
            <template v-if="canManage && !closed" #actions>
                <Button variant="outline" @click="openEdit"
                    ><Pencil class="size-4" /> Editar</Button
                >
                <Button
                    v-if="canCancel"
                    variant="ghost"
                    class="text-destructive"
                    @click="askTransition('cancelled')"
                    >Cancelar orden</Button
                >
                <Button
                    v-for="next in forward"
                    :key="next.value"
                    @click="askTransition(next.value)"
                >
                    {{ VERBS[next.value]?.label ?? next.label }}
                </Button>
            </template>
        </PageHeader>

        <ProgressSteps
            v-if="order.status.value !== 'cancelled'"
            :steps="steps"
            :current="order.status.value"
            :completed="order.status.value === 'delivered'"
            label="Avance de la orden"
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="flex flex-col gap-6">
                <SectionCard title="Detalle">
                    <KeyValue :items="details" :columns="2">
                        <template #value-cost
                            ><Money :value="order.cost" fallback="Por definir"
                        /></template>
                        <template #value-dueOn
                            ><DateText
                                :value="order.dueOn"
                                format="long"
                                fallback="Sin fecha"
                        /></template>
                    </KeyValue>
                </SectionCard>

                <SectionCard
                    title="Documentos"
                    description="Planos de taller, despiece, cotización del proveedor. Se guardan de forma privada."
                    :icon="FileText"
                >
                    <div class="flex flex-col gap-4">
                        <MediaGrid
                            :items="order.documents"
                            :removable="canManage"
                            empty-text="Aún no hay documentos en esta orden."
                            @remove="askRemove"
                        />
                        <FileDropzone
                            v-if="canManage"
                            visibility="private"
                            collection="privado/fabricacion"
                            :accept="[
                                '.pdf',
                                'image/*',
                                '.dwg',
                                '.dxf',
                                '.xlsx',
                                '.zip',
                            ]"
                            :max-size="100 * 1024 * 1024"
                            compact
                            label="Arrastra documentos o elige desde tu equipo"
                            @all-completed="onUploaded"
                        />
                    </div>
                </SectionCard>

                <SectionCard title="Incidencias" :icon="ShieldAlert">
                    <p
                        v-if="order.incidents.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sin incidencias ligadas a esta orden.
                    </p>
                    <ul v-else class="-my-2 divide-y">
                        <li
                            v-for="incident in order.incidents"
                            :key="incident.id"
                        >
                            <Link
                                :href="`/admin/incidencias/${incident.id}`"
                                class="flex items-center justify-between gap-3 py-2.5 hover:text-primary"
                            >
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-sm font-medium"
                                        >{{ incident.title }}</span
                                    >
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ incident.folio }}</span
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
                <SectionCard title="Diseño a fabricar" :icon="BadgeCheck">
                    <div
                        v-if="order.design"
                        class="flex flex-col gap-2 text-sm"
                    >
                        <p class="font-medium">
                            Versión {{ order.design.number }} ·
                            {{ order.design.title }}
                        </p>
                        <p class="text-muted-foreground">
                            Aprobación técnica:
                            <DateText
                                :value="order.design.technicalApprovedAt"
                                format="medium"
                            />
                        </p>
                        <p
                            v-if="order.design.technicalNotes"
                            class="rounded-lg bg-muted/60 p-3 whitespace-pre-line"
                        >
                            {{ order.design.technicalNotes }}
                        </p>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No se encontró la versión de diseño.
                    </p>
                </SectionCard>

                <SectionCard title="Fechas">
                    <dl class="grid gap-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Creada</dt>
                            <dd><DateText :value="order.createdAt" /></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Emitida</dt>
                            <dd><DateText :value="order.issuedAt" /></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Lista</dt>
                            <dd><DateText :value="order.readyAt" /></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Entregada</dt>
                            <dd><DateText :value="order.deliveredAt" /></dd>
                        </div>
                    </dl>
                </SectionCard>

                <SectionCard title="Pago al proveedor" :icon="HandCoins">
                    <div
                        v-if="order.payout"
                        class="flex flex-col gap-2 text-sm"
                    >
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
                        <p
                            v-if="order.payout.rejectionReason"
                            class="text-danger-ink"
                        >
                            Motivo del rechazo:
                            {{ order.payout.rejectionReason }}
                        </p>
                        <Link
                            href="/admin/pagos-proveedores"
                            class="text-primary underline-offset-4 hover:underline"
                            >Ver en pagos a proveedores</Link
                        >
                    </div>
                    <div v-else class="flex flex-col gap-3 text-sm">
                        <p class="text-muted-foreground">
                            {{
                                order.status.value === 'draft'
                                    ? 'Emite la orden para poder solicitar su pago.'
                                    : 'Sin pago solicitado. Finanzas lo autoriza y lo registra.'
                            }}
                        </p>
                        <Button
                            v-if="canRequestPayout"
                            variant="outline"
                            :disabled="!order.cost"
                            @click="payoutOpen = true"
                            >Solicitar pago</Button
                        >
                        <p
                            v-if="canRequestPayout && !order.cost"
                            class="text-xs text-muted-foreground"
                        >
                            Captura el costo de la orden primero.
                        </p>
                    </div>
                </SectionCard>
            </aside>
        </div>
    </PageContainer>

    <ConfirmDialog
        v-model:open="transitionOpen"
        :title="
            pending
                ? (VERBS[pending]?.label ?? 'Cambiar estado')
                : 'Cambiar estado'
        "
        :confirm-label="
            pending ? (VERBS[pending]?.label ?? 'Confirmar') : 'Confirmar'
        "
        loading-label="Guardando…"
        :tone="pending === 'cancelled' ? 'destructive' : 'default'"
        :confirm="confirmTransition"
    >
        <div class="flex flex-col gap-3">
            <p>{{ pending ? VERBS[pending]?.description : '' }}</p>
            <div class="grid gap-1.5">
                <Label for="fab-note">Nota (opcional)</Label>
                <Textarea
                    id="fab-note"
                    v-model="note"
                    rows="2"
                    maxlength="500"
                />
            </div>
        </div>
    </ConfirmDialog>

    <ConfirmDialog
        v-model:open="payoutOpen"
        title="Solicitar pago al proveedor"
        :description="`Se pedirá autorizar el pago de la orden ${order.folio} por su costo acordado. Lo autoriza alguien distinto a quien lo solicita.`"
        confirm-label="Solicitar pago"
        loading-label="Enviando…"
        :confirm="confirmPayout"
    />

    <ConfirmDialog
        v-model:open="removeOpen"
        title="Quitar documento"
        :description="
            removing ? `Se quitará «${removing.name}» de esta orden.` : ''
        "
        confirm-label="Quitar"
        tone="destructive"
        :confirm="confirmRemove"
    />

    <FormSheet
        v-model:open="editOpen"
        title="Editar orden"
        :description="order.folio"
    >
        <form
            id="fabrication-edit"
            class="flex flex-col gap-5"
            @submit.prevent="submitEdit"
        >
            <div class="grid gap-2">
                <Label for="edit-supplier">Fabricante</Label>
                <SearchSelect
                    id="edit-supplier"
                    v-model="form.supplier_id"
                    :options="options.suppliers"
                    :disabled="order.status.value !== 'draft'"
                />
                <p
                    v-if="order.status.value !== 'draft'"
                    class="text-xs text-muted-foreground"
                >
                    El proveedor solo cambia mientras la orden es borrador.
                </p>
                <InputError :message="form.errors.supplier_id" />
            </div>
            <div class="grid gap-2">
                <Label for="edit-cost">Costo acordado</Label>
                <Input
                    id="edit-cost"
                    v-model="form.cost"
                    inputmode="decimal"
                    placeholder="0.00"
                    :disabled="!!order.payout"
                />
                <p v-if="order.payout" class="text-xs text-muted-foreground">
                    Ya hay un pago solicitado; el costo queda fijo.
                </p>
                <InputError :message="form.errors.cost" />
            </div>
            <div class="grid gap-2">
                <Label for="edit-due">Fecha compromiso</Label>
                <DatePicker id="edit-due" v-model="form.due_on" clearable />
                <InputError :message="form.errors.due_on" />
            </div>
            <div class="grid gap-2">
                <Label for="edit-notes">Notas</Label>
                <Textarea id="edit-notes" v-model="form.notes" rows="4" />
                <InputError :message="form.errors.notes" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="editOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="fabrication-edit"
                :disabled="form.processing"
                ><Spinner v-if="form.processing" /> Guardar cambios</Button
            >
        </template>
    </FormSheet>
</template>
