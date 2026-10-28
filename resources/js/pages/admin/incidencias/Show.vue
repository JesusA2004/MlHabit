<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    ConfirmDialog,
    DatePicker,
    DateText,
    FileDropzone,
    FormSheet,
    KeyValue,
    Money,
    PageContainer,
    PageHeader,
    SearchSelect,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import type { KeyValueItem, SearchSelectOption } from '@/components/ds';
import InputError from '@/components/InputError.vue';
import MediaGrid from '@/components/operations/MediaGrid.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import fabricacion from '@/routes/admin/fabricacion';
import incidencias from '@/routes/admin/incidencias';
import instalaciones from '@/routes/admin/instalaciones';
import type { BreadcrumbItem } from '@/types';
import type {
    EnumOption,
    IncidentDetail,
    MediaItem,
    SelectOption,
} from '@/types/operations';

const props = defineProps<{
    incident: IncidentDetail;
    activity: {
        id: number;
        action: string;
        actor: string | null;
        date: string;
    }[];
    options: {
        categories: EnumOption[];
        severities: EnumOption[];
        statuses: EnumOption[];
        origins: EnumOption[];
        parties: EnumOption[];
        suppliers?: SelectOption[];
        assignees?: SelectOption[];
    };
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Incidencias', href: '/admin/incidencias' },
            { title: 'Detalle', href: '#' },
        ] as BreadcrumbItem[],
    },
});

const NONE = '__ninguno__';

// ------------------------------------------------------------ Estado
const STATUS_ACTION: Record<string, string> = {
    in_progress: 'Empezar a atender',
    resolved: 'Marcar como resuelta',
    closed: 'Cerrar',
    open: 'Reabrir',
    cancelled: 'Cancelar incidencia',
};

const statusSheet = ref(false);
const target = ref<EnumOption | null>(null);
const statusForm = useForm({
    status: '',
    resolution: props.incident.resolution ?? '',
});

function openStatus(status: EnumOption) {
    target.value = status;
    statusForm.status = status.value;
    statusForm.clearErrors();
    statusSheet.value = true;
}

function submitStatus() {
    statusForm.post(incidencias.estado(props.incident.id).url, {
        preserveScroll: true,
        onSuccess: () => (statusSheet.value = false),
    });
}

const primaryNext = computed(() =>
    props.incident.nextStatuses.filter((s) => s.value !== 'cancelled'),
);
const canCancel = computed(() =>
    props.incident.nextStatuses.some((s) => s.value === 'cancelled'),
);
const cancelOpen = ref(false);

function cancelIncident() {
    return new Promise<void>((resolve, reject) => {
        router.post(
            incidencias.estado(props.incident.id).url,
            { status: 'cancelled' },
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () =>
                    reject(new Error('No se pudo cancelar la incidencia.')),
            },
        );
    });
}

// ------------------------------------------------------------ Edición
const editOpen = ref(false);
const form = useForm({
    category: props.incident.category.value,
    severity: props.incident.severity.value,
    title: props.incident.title,
    description: props.incident.description,
    responsible_party: props.incident.responsibleParty?.value ?? null,
    responsible_supplier_id: props.incident.responsibleSupplier?.id ?? null,
    assigned_to: props.incident.assignee?.id ?? null,
    action_plan: props.incident.actionPlan ?? '',
    cost: props.incident.cost ?? '',
    due_on: props.incident.dueOn,
    visible_to_client: props.incident.visibleToClient,
});

function submitEdit() {
    form.put(incidencias.update(props.incident.id).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

const supplierOptions = computed<SearchSelectOption<number>[]>(() =>
    (props.options.suppliers ?? []).map((s) => ({
        value: s.value,
        label: s.label,
    })),
);
const assigneeOptions = computed<SearchSelectOption<number>[]>(() =>
    (props.options.assignees ?? []).map((a) => ({
        value: a.value,
        label: a.label,
        description: a.hint,
    })),
);

// ------------------------------------------------------------ Evidencia
const uploading = ref(false);
function attachEvidence(media: { id: number }[]) {
    if (media.length === 0) return;
    uploading.value = true;
    router.post(
        incidencias.evidencia.store(props.incident.id).url,
        { media_ids: media.map((m) => m.id) },
        {
            preserveScroll: true,
            onFinish: () => (uploading.value = false),
            onError: (e) =>
                toast.error(
                    Object.values(e)[0] ?? 'No se pudo agregar la evidencia.',
                ),
        },
    );
}

function removeEvidence(media: MediaItem) {
    router.delete(
        incidencias.evidencia.destroy({
            incident: props.incident.id,
            media: media.id,
        }).url,
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(
                    Object.values(e)[0] ?? 'No se pudo quitar la evidencia.',
                ),
        },
    );
}

// ------------------------------------------------------------ Ficha
const details = computed<KeyValueItem[]>(() => [
    { key: 'project', label: 'Proyecto' },
    {
        key: 'category',
        label: 'Categoría',
        value: props.incident.category.label,
    },
    {
        key: 'origin',
        label: 'Detectada por',
        value: props.incident.origin.label,
    },
    {
        key: 'responsible',
        label: 'Responsable',
        value: props.incident.responsibleParty
            ? props.incident.responsibleParty.label +
              (props.incident.responsibleSupplier
                  ? ` · ${props.incident.responsibleSupplier.name}`
                  : '')
            : 'Por definir',
    },
    {
        key: 'assignee',
        label: 'Asignada a',
        value: props.incident.assignee?.name ?? 'Sin asignar',
    },
    { key: 'due', label: 'Fecha compromiso' },
    { key: 'cost', label: 'Costo' },
    { key: 'orders', label: 'Órdenes vinculadas' },
    {
        key: 'reporter',
        label: 'Reportó',
        value: props.incident.reporter?.name ?? '—',
    },
    { key: 'created', label: 'Registrada' },
]);

const ACTIONS: Record<string, string> = {
    'incidencia.creada': 'Registró la incidencia',
    'incidencia.actualizada': 'Actualizó los datos',
    'incidencia.estado': 'Cambió el estado',
    'operaciones.archivos_vinculados': 'Agregó evidencia',
    'operaciones.archivo_desvinculado': 'Quitó evidencia',
};
</script>

<template>
    <Head :title="`${incident.folio} · Incidencia`" />

    <PageContainer>
        <PageHeader :title="incident.title">
            <template #meta>
                <div
                    class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span class="tabular-nums">{{ incident.folio }}</span>
                    <StatusBadge
                        :tone="incident.status.tone"
                        :label="incident.status.label"
                    />
                    <StatusBadge
                        :tone="incident.severity.tone"
                        :label="`Severidad ${incident.severity.label.toLowerCase()}`"
                        variant="outline"
                    />
                    <span class="inline-flex items-center gap-1">
                        <component
                            :is="incident.visibleToClient ? Eye : EyeOff"
                            class="size-4"
                            aria-hidden="true"
                        />
                        {{
                            incident.visibleToClient
                                ? 'Visible para el cliente'
                                : 'Solo equipo interno'
                        }}
                    </span>
                </div>
            </template>
            <template v-if="canManage" #actions>
                <Button variant="outline" @click="editOpen = true"
                    ><Pencil class="size-4" /> Editar</Button
                >
                <Button
                    v-for="s in primaryNext"
                    :key="s.value"
                    :variant="s.value === 'resolved' ? 'default' : 'outline'"
                    @click="openStatus(s)"
                >
                    {{ STATUS_ACTION[s.value] ?? s.label }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <SectionCard title="Qué pasó">
                    <p class="text-sm leading-relaxed whitespace-pre-line">
                        {{ incident.description }}
                    </p>
                </SectionCard>

                <SectionCard title="Plan de acción">
                    <p
                        v-if="incident.actionPlan"
                        class="text-sm leading-relaxed whitespace-pre-line"
                    >
                        {{ incident.actionPlan }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Aún no hay plan.
                        <button
                            v-if="canManage"
                            type="button"
                            class="font-medium text-primary hover:underline"
                            @click="editOpen = true"
                        >
                            Agregar plan
                        </button>
                    </p>
                </SectionCard>

                <SectionCard v-if="incident.resolution" title="Resolución">
                    <p class="text-sm leading-relaxed whitespace-pre-line">
                        {{ incident.resolution }}
                    </p>
                    <p
                        v-if="incident.resolvedAt"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Resuelta
                        <DateText
                            :value="incident.resolvedAt"
                            format="datetime"
                        />
                    </p>
                </SectionCard>

                <SectionCard
                    title="Evidencia"
                    description="Archivos privados: solo el equipo y, si es visible, el cliente."
                >
                    <div class="flex flex-col gap-4">
                        <MediaGrid
                            :items="incident.evidence"
                            :removable="canManage"
                            empty-text="Sin evidencia todavía."
                            @remove="removeEvidence"
                        />
                        <FileDropzone
                            v-if="canManage"
                            visibility="private"
                            collection="privado/incidencias"
                            compact
                            :disabled="uploading"
                            :accept="['image/*', 'video/*', '.pdf']"
                            label="Agregar fotos, videos o PDF"
                            @all-completed="attachEvidence"
                        />
                    </div>
                </SectionCard>
            </div>

            <div class="flex flex-col gap-6">
                <SectionCard title="Ficha">
                    <KeyValue :items="details" layout="stacked">
                        <template #value-project>
                            <span class="flex flex-col">
                                <span>{{ incident.project.name }}</span>
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                    >{{ incident.project.folio
                                    }}<template
                                        v-if="incident.project.customer"
                                    >
                                        ·
                                        {{
                                            incident.project.customer
                                        }}</template
                                    ></span
                                >
                            </span>
                        </template>
                        <template #value-due>
                            <span
                                :class="
                                    incident.overdue
                                        ? 'font-medium text-danger'
                                        : ''
                                "
                            >
                                <DateText
                                    :value="incident.dueOn"
                                    fallback="Sin fecha"
                                />
                                <template v-if="incident.overdue">
                                    · vencida</template
                                >
                            </span>
                        </template>
                        <template #value-cost
                            ><Money :value="incident.cost" fallback="Sin costo"
                        /></template>
                        <template #value-orders>
                            <span class="flex flex-wrap gap-2">
                                <Link
                                    v-if="incident.installationOrder"
                                    :href="
                                        instalaciones.show(
                                            incident.installationOrder.id,
                                        ).url
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Instalación
                                    {{ incident.installationOrder.folio }}
                                </Link>
                                <Link
                                    v-if="incident.fabricationOrder"
                                    :href="
                                        fabricacion.show(
                                            incident.fabricationOrder.id,
                                        ).url
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Fabricación
                                    {{ incident.fabricationOrder.folio }}
                                </Link>
                                <span
                                    v-if="
                                        !incident.installationOrder &&
                                        !incident.fabricationOrder
                                    "
                                    class="text-muted-foreground"
                                    >Ninguna</span
                                >
                            </span>
                        </template>
                        <template #value-created
                            ><DateText
                                :value="incident.createdAt"
                                format="datetime"
                        /></template>
                    </KeyValue>
                </SectionCard>

                <SectionCard title="Actividad">
                    <ol v-if="activity.length" class="flex flex-col gap-3">
                        <li
                            v-for="row in activity"
                            :key="row.id"
                            class="flex flex-col text-sm"
                        >
                            <span>{{ ACTIONS[row.action] ?? row.action }}</span>
                            <span class="text-xs text-muted-foreground"
                                >{{ row.actor ?? 'Sistema' }} ·
                                <DateText :value="row.date" relative
                            /></span>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-muted-foreground">
                        Sin movimientos registrados.
                    </p>
                </SectionCard>

                <Button
                    v-if="canManage && canCancel"
                    variant="ghost"
                    class="text-destructive"
                    @click="cancelOpen = true"
                    >Cancelar incidencia</Button
                >
            </div>
        </div>
    </PageContainer>

    <FormSheet
        v-model:open="statusSheet"
        :title="
            target
                ? (STATUS_ACTION[target.value] ?? target.label)
                : 'Cambiar estado'
        "
        :description="
            target?.value === 'resolved'
                ? 'Describe qué se hizo para resolverla. El cliente la verá si la incidencia es visible.'
                : undefined
        "
    >
        <form
            id="status-form"
            class="flex flex-col gap-4"
            @submit.prevent="submitStatus"
        >
            <div v-if="target?.value === 'resolved'" class="grid gap-1.5">
                <Label for="resolution">Resolución</Label>
                <Textarea
                    id="resolution"
                    v-model="statusForm.resolution"
                    rows="5"
                />
                <InputError :message="statusForm.errors.resolution" />
            </div>
            <p v-else class="text-sm text-muted-foreground">
                La incidencia pasará a «{{ target?.label }}».
            </p>
            <InputError :message="statusForm.errors.status" />
        </form>
        <template #footer>
            <Button variant="outline" @click="statusSheet = false"
                >Volver</Button
            >
            <Button
                type="submit"
                form="status-form"
                :disabled="
                    statusForm.processing ||
                    (target?.value === 'resolved' &&
                        !statusForm.resolution.trim())
                "
            >
                {{ statusForm.processing ? 'Guardando…' : 'Confirmar' }}
            </Button>
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="cancelOpen"
        title="¿Cancelar esta incidencia?"
        description="Úsalo si se registró por error o ya no aplica. Queda en el historial."
        confirm-label="Cancelar incidencia"
        cancel-label="Volver"
        loading-label="Cancelando…"
        tone="destructive"
        :confirm="cancelIncident"
    />

    <FormSheet v-model:open="editOpen" title="Editar incidencia" size="lg">
        <form
            id="edit-form"
            class="flex flex-col gap-5"
            @submit.prevent="submitEdit"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label>Categoría</Label>
                    <Select v-model="form.category">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="c in options.categories"
                                :key="c.value"
                                :value="c.value"
                                >{{ c.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Severidad</Label>
                    <Select v-model="form.severity">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="s in options.severities"
                                :key="s.value"
                                :value="s.value"
                                >{{ s.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label for="e-title">Título</Label>
                <Input id="e-title" v-model="form.title" maxlength="200" />
                <InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-1.5">
                <Label for="e-desc">Qué pasó</Label>
                <Textarea id="e-desc" v-model="form.description" rows="4" />
                <InputError :message="form.errors.description" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label>Responsable</Label>
                    <Select
                        :model-value="form.responsible_party ?? NONE"
                        @update:model-value="
                            form.responsible_party =
                                $event === NONE ? null : String($event)
                        "
                    >
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">Por definir</SelectItem>
                            <SelectItem
                                v-for="p in options.parties"
                                :key="p.value"
                                :value="p.value"
                                >{{ p.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div
                    v-if="form.responsible_party === 'supplier'"
                    class="grid gap-1.5"
                >
                    <Label>Proveedor</Label>
                    <SearchSelect
                        v-model="form.responsible_supplier_id"
                        :options="supplierOptions"
                        placeholder="Elige proveedor"
                    />
                    <InputError
                        :message="form.errors.responsible_supplier_id"
                    />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label>Asignada a</Label>
                    <SearchSelect
                        v-model="form.assigned_to"
                        :options="assigneeOptions"
                        clearable
                        placeholder="Sin asignar"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>Fecha compromiso</Label>
                    <DatePicker v-model="form.due_on" clearable />
                    <InputError :message="form.errors.due_on" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label for="e-plan">Plan de acción</Label>
                <Textarea id="e-plan" v-model="form.action_plan" rows="3" />
            </div>
            <div class="grid gap-1.5 sm:max-w-56">
                <Label for="e-cost">Costo</Label>
                <Input
                    id="e-cost"
                    v-model="form.cost"
                    inputmode="decimal"
                    placeholder="0.00"
                />
                <InputError :message="form.errors.cost" />
            </div>
            <label class="flex items-start gap-3 rounded-xl border p-3">
                <Switch v-model="form.visible_to_client" class="mt-0.5" />
                <span class="flex flex-col">
                    <span class="text-sm font-medium"
                        >Visible para el cliente</span
                    >
                    <span class="text-xs text-muted-foreground"
                        >La verá en su portal con su estado.</span
                    >
                </span>
            </label>
        </form>
        <template #footer>
            <Button variant="outline" @click="editOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="edit-form"
                :disabled="form.processing"
                >{{
                    form.processing ? 'Guardando…' : 'Guardar cambios'
                }}</Button
            >
        </template>
    </FormSheet>
</template>
