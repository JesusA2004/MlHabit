<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlarmClock, Factory, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import fabricacion from '@/routes/admin/fabricacion';
import type {
    EnumOption,
    FabricationRow,
    ProjectRef,
} from '@/types/operations';
import type { LengthAwarePaginator } from '@/types/ui';

type EligibleProject = ProjectRef & {
    designs: { id: number; label: string; approvedAt: string | null }[];
};

const props = defineProps<{
    orders: LengthAwarePaginator<FabricationRow>;
    filters: { q?: string; estado?: string; proveedor?: number };
    counts: Record<string, number>;
    overdue: number;
    options: {
        statuses: EnumOption[];
        suppliers: {
            value: number;
            label: string;
            leadTimeDays: number | null;
        }[];
    };
    eligibleProjects: EligibleProject[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Fabricación', href: '/admin/fabricacion' },
        ],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('fabrication.manage'));

// ---------------------------------------------------------------- Filtros

const search = ref(props.filters.q ?? '');
const status = ref<string | null>(props.filters.estado ?? null);
const supplier = ref<number | null>(
    props.filters.proveedor ? Number(props.filters.proveedor) : null,
);
const loading = ref(false);

const total = computed(() =>
    Object.values(props.counts).reduce((a, b) => a + b, 0),
);

function reload() {
    router.get(
        fabricacion.index().url,
        {
            q: search.value || undefined,
            estado: status.value || undefined,
            proveedor: supplier.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['orders', 'filters', 'counts'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

watch([search, status, supplier], reload);

const columns: DataColumn<FabricationRow>[] = [
    { key: 'folio', label: 'Orden', primary: true },
    { key: 'project', label: 'Proyecto' },
    { key: 'supplier', label: 'Proveedor' },
    { key: 'status', label: 'Estado' },
    { key: 'dueOn', label: 'Compromiso' },
    { key: 'cost', label: 'Costo', align: 'right', hideOnMobile: true },
];

// ---------------------------------------------------------------- Nueva orden

const createOpen = ref(false);
const form = useForm({
    project_id: null as number | null,
    design_version_id: null as number | null,
    supplier_id: null as number | null,
    cost: '',
    due_on: null as string | null,
    notes: '',
});

const projectOptions = computed(() =>
    props.eligibleProjects.map((p) => ({
        value: p.id,
        label: `${p.folio} · ${p.name}`,
        description: [
            p.customer,
            p.designs.length
                ? `${p.designs.length} diseño(s) aprobado(s)`
                : 'Sin diseño con aprobación técnica',
        ]
            .filter(Boolean)
            .join(' — '),
    })),
);
const selectedProject = computed(
    () => props.eligibleProjects.find((p) => p.id === form.project_id) ?? null,
);
const designOptions = computed(() =>
    (selectedProject.value?.designs ?? []).map((d) => ({
        value: d.id,
        label: d.label,
    })),
);
const supplierOptions = computed(() =>
    props.options.suppliers.map((s) => ({
        value: s.value,
        label: s.label,
        description: s.leadTimeDays
            ? `Entrega habitual en ${s.leadTimeDays} días`
            : undefined,
    })),
);

watch(
    () => form.project_id,
    () => {
        form.design_version_id = selectedProject.value?.designs[0]?.id ?? null;
    },
);

const today = new Date().toLocaleDateString('en-CA');

function openCreate() {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function submit() {
    form.post(fabricacion.store().url, {
        preserveScroll: true,
        onSuccess: () => (createOpen.value = false),
    });
}
</script>

<template>
    <Head title="Fabricación" />

    <PageContainer>
        <PageHeader
            title="Fabricación"
            description="Órdenes a proveedores fabricantes. Solo se fabrica con un diseño con aprobación técnica y el proyecto autorizado."
        >
            <template #meta>
                <span
                    ><strong
                        class="font-semibold text-foreground tabular-nums"
                        >{{ total }}</strong
                    >
                    órdenes</span
                >
                <StatusBadge
                    v-if="overdue > 0"
                    tone="danger"
                    :icon="AlarmClock"
                    :label="`${overdue} con compromiso vencido`"
                />
            </template>
            <template v-if="canManage" #actions>
                <Button @click="openCreate"
                    ><Plus class="size-4" /> Nueva orden</Button
                >
            </template>
        </PageHeader>

        <!-- Estados -->
        <nav
            aria-label="Filtrar por estado"
            class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
        >
            <ul class="flex w-max gap-1.5">
                <li>
                    <button
                        type="button"
                        :class="
                            cn(
                                'flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm transition-colors',
                                status === null
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'bg-card hover:bg-muted',
                            )
                        "
                        :aria-pressed="status === null"
                        @click="status = null"
                    >
                        Todas
                        <span class="tabular-nums opacity-70">{{ total }}</span>
                    </button>
                </li>
                <li v-for="option in options.statuses" :key="option.value">
                    <button
                        type="button"
                        :class="
                            cn(
                                'flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm transition-colors',
                                status === option.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'bg-card hover:bg-muted',
                            )
                        "
                        :aria-pressed="status === option.value"
                        @click="status = option.value"
                    >
                        {{ option.label }}
                        <span class="tabular-nums opacity-70">{{
                            counts[option.value] ?? 0
                        }}</span>
                    </button>
                </li>
            </ul>
        </nav>

        <FilterBar
            v-model:search="search"
            placeholder="Folio de orden o proyecto"
            :active-count="supplier ? 1 : 0"
            @clear="supplier = null"
        >
            <template #filters>
                <SearchSelect
                    v-model="supplier"
                    :options="options.suppliers"
                    placeholder="Todos los proveedores"
                    clearable
                    class="md:w-64"
                />
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="orders"
            :loading="loading"
            :row-href="(row) => fabricacion.show(row.id).url"
            item-label="órdenes"
        >
            <template #cell-folio="{ row }">
                <Link
                    :href="fabricacion.show(row.id).url"
                    class="font-medium underline-offset-4 hover:underline"
                    >{{ row.folio }}</Link
                >
            </template>
            <template #cell-project="{ row }">
                <div class="min-w-0">
                    <p class="truncate">{{ row.project.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ row.project.folio
                        }}<template v-if="row.project.customer">
                            · {{ row.project.customer }}</template
                        >
                    </p>
                </div>
            </template>
            <template #cell-supplier="{ row }">{{
                row.supplier.name
            }}</template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="row.status.tone"
                    :label="row.status.label"
                />
            </template>
            <template #cell-dueOn="{ row }">
                <span
                    :class="
                        cn(
                            'inline-flex items-center gap-1.5',
                            row.overdue && 'font-medium text-danger-ink',
                        )
                    "
                >
                    <AlarmClock
                        v-if="row.overdue"
                        class="size-3.5"
                        aria-label="Vencida"
                    />
                    <DateText :value="row.dueOn" fallback="Sin fecha" />
                </span>
            </template>
            <template #cell-cost="{ row }"
                ><Money :value="row.cost" fallback="Por definir"
            /></template>
            <template #empty>
                <EmptyState
                    :icon="Factory"
                    :title="
                        status || search || supplier
                            ? 'Ninguna orden coincide con los filtros'
                            : 'Aún no hay órdenes de fabricación'
                    "
                    :description="
                        status || search || supplier
                            ? 'Prueba con otro estado o quita los filtros.'
                            : 'Cuando un proyecto esté autorizado y su diseño tenga aprobación técnica, crea aquí su orden para el fabricante.'
                    "
                >
                    <Button
                        v-if="canManage && !status && !search && !supplier"
                        @click="openCreate"
                        ><Plus class="size-4" /> Nueva orden</Button
                    >
                </EmptyState>
            </template>
        </DataView>
    </PageContainer>

    <FormSheet
        v-model:open="createOpen"
        title="Nueva orden de fabricación"
        description="Se crea como borrador; la emites al proveedor cuando esté lista."
        size="lg"
    >
        <form
            id="fabrication-create"
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="fab-project">Proyecto</Label>
                <SearchSelect
                    id="fab-project"
                    v-model="form.project_id"
                    :options="projectOptions"
                    placeholder="Elige un proyecto autorizado"
                    empty-text="No hay proyectos autorizados para fabricar."
                    :invalid="!!form.errors.project_id"
                />
                <InputError :message="form.errors.project_id" />
            </div>

            <div class="grid gap-2">
                <Label for="fab-design">Versión de diseño</Label>
                <p
                    v-if="selectedProject && designOptions.length === 0"
                    class="rounded-lg border border-warning/30 bg-warning-soft px-3 py-2.5 text-sm text-warning-ink"
                >
                    Sin diseño con aprobación técnica. Pide al equipo de diseño
                    que apruebe técnicamente una versión antes de fabricar.
                </p>
                <SearchSelect
                    v-else
                    id="fab-design"
                    v-model="form.design_version_id"
                    :options="designOptions"
                    :disabled="!selectedProject"
                    :placeholder="
                        selectedProject
                            ? 'Elige la versión aprobada'
                            : 'Primero elige un proyecto'
                    "
                    :invalid="!!form.errors.design_version_id"
                />
                <InputError :message="form.errors.design_version_id" />
            </div>

            <div class="grid gap-2">
                <Label for="fab-supplier">Fabricante</Label>
                <SearchSelect
                    id="fab-supplier"
                    v-model="form.supplier_id"
                    :options="supplierOptions"
                    placeholder="Elige un proveedor activo"
                    empty-text="No hay fabricantes activos."
                    :invalid="!!form.errors.supplier_id"
                />
                <InputError :message="form.errors.supplier_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="fab-cost">Costo acordado</Label>
                    <Input
                        id="fab-cost"
                        v-model="form.cost"
                        inputmode="decimal"
                        placeholder="0.00"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.cost" />
                </div>
                <div class="grid gap-2">
                    <Label for="fab-due">Fecha compromiso</Label>
                    <DatePicker
                        id="fab-due"
                        v-model="form.due_on"
                        :min="today"
                        clearable
                        :invalid="!!form.errors.due_on"
                    />
                    <InputError :message="form.errors.due_on" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="fab-notes">Notas para el proveedor</Label>
                <Textarea
                    id="fab-notes"
                    v-model="form.notes"
                    rows="3"
                    placeholder="Acabados, herrajes, entrega en taller…"
                />
                <InputError :message="form.errors.notes" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="createOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="fabrication-create"
                :disabled="form.processing || !form.design_version_id"
            >
                <Spinner v-if="form.processing" /> Crear borrador
            </Button>
        </template>
    </FormSheet>
</template>
