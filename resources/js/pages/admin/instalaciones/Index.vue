<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Lock, Plus, Search, Sparkles, Wrench } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
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
import instalaciones from '@/routes/admin/instalaciones';
import type {
    EnumOption,
    InstallationRow,
    ProjectRef,
    RateCardItemRow,
} from '@/types/operations';
import type { LengthAwarePaginator } from '@/types/ui';

type RateCardOption = {
    id: number;
    label: string;
    supplierId: number | null;
    mobilizationFee: string;
    minimumCharge: string;
    items: RateCardItemRow[];
};

const props = defineProps<{
    orders: LengthAwarePaginator<InstallationRow>;
    filters: { q?: string; estado?: string; instalador?: number };
    counts: Record<string, number>;
    pendingExtras: number;
    options: {
        statuses: EnumOption[];
        installers: { value: number; label: string }[];
        projects?: ProjectRef[];
        suppliers?: { value: number; label: string }[];
        rateCards?: RateCardOption[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Instalaciones', href: '/admin/instalaciones' },
        ],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('installations.manage'));

// ---------------------------------------------------------------- Colas

const CLOSED = ['approved', 'payment_authorized', 'paid', 'cancelled'];
const TABS = [
    { key: 'in_progress', label: 'En curso', statuses: ['in_progress'] },
    { key: 'scheduled', label: 'Programadas', statuses: ['scheduled'] },
    {
        key: 'client_review',
        label: 'Esperando al cliente',
        statuses: ['client_review', 'evidence_submitted'],
    },
    {
        key: 'quality_review',
        label: 'En calidad',
        statuses: ['quality_review'],
    },
    { key: 'draft', label: 'Borradores', statuses: ['draft'] },
    { key: 'closed', label: 'Cerradas', statuses: CLOSED },
];

const search = ref(props.filters.q ?? '');
const status = ref<string | null>(props.filters.estado ?? null);
const installer = ref<number | null>(
    props.filters.instalador ? Number(props.filters.instalador) : null,
);
const loading = ref(false);

const activeTab = computed(() => {
    if (!status.value) {
        return null;
    }

    return TABS.find((t) => t.statuses.includes(status.value!))?.key ?? null;
});

const countFor = (statuses: string[]) =>
    statuses.reduce((sum, s) => sum + (props.counts[s] ?? 0), 0);
const total = computed(() =>
    Object.values(props.counts).reduce((a, b) => a + b, 0),
);
const closedStatuses = computed(() =>
    props.options.statuses.filter((s) => CLOSED.includes(s.value)),
);

function selectTab(key: string | null) {
    status.value = key
        ? (TABS.find((t) => t.key === key)?.statuses[0] ?? null)
        : null;
}

function reload() {
    router.get(
        instalaciones.index().url,
        {
            q: search.value || undefined,
            estado: status.value || undefined,
            instalador: installer.value || undefined,
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

watch([search, status, installer], reload);

const columns: DataColumn<InstallationRow>[] = [
    { key: 'folio', label: 'Orden', primary: true },
    { key: 'project', label: 'Proyecto' },
    { key: 'scheduledStart', label: 'Fecha' },
    { key: 'leadInstaller', label: 'Instalador líder' },
    { key: 'progress', label: 'Avance' },
    { key: 'status', label: 'Estado' },
    { key: 'total', label: 'Total', align: 'right', hideOnMobile: true },
];

// ---------------------------------------------------------------- Nueva orden

const createOpen = ref(false);
const form = useForm({
    project_id: null as number | null,
    supplier_id: null as number | null,
    rate_card_id: null as number | null,
    lead_installer_id: null as number | null,
    notes: '',
    items: [] as { rate_card_item_id: number; quantity: string }[],
});
const quantities = ref<Record<number, string>>({});
const itemSearch = ref('');

const projectOptions = computed(() =>
    (props.options.projects ?? []).map((p) => ({
        value: p.id,
        label: `${p.folio} · ${p.name}`,
        description: [p.customer, p.status.label].filter(Boolean).join(' — '),
    })),
);
const rateCardOptions = computed(() =>
    (props.options.rateCards ?? [])
        .filter(
            (c) => c.supplierId === null || c.supplierId === form.supplier_id,
        )
        .map((c) => ({
            value: c.id,
            label: c.label,
            description: c.supplierId
                ? 'Exclusivo de este proveedor'
                : 'General',
        })),
);
const rateCard = computed(
    () =>
        (props.options.rateCards ?? []).find(
            (c) => c.id === form.rate_card_id,
        ) ?? null,
);
const visibleItems = computed(() => {
    const term = itemSearch.value.trim().toLowerCase();
    const items = rateCard.value?.items ?? [];

    return term
        ? items.filter((i) =>
              `${i.code} ${i.description}`.toLowerCase().includes(term),
          )
        : items;
});
const selectedCount = computed(
    () => Object.values(quantities.value).filter((q) => Number(q) > 0).length,
);

watch(
    () => form.supplier_id,
    () => {
        if (
            form.rate_card_id &&
            !rateCardOptions.value.some((o) => o.value === form.rate_card_id)
        ) {
            form.rate_card_id = null;
        }
    },
);
watch(
    () => form.rate_card_id,
    () => {
        quantities.value = {};
    },
);

function openCreate() {
    form.reset();
    form.clearErrors();
    quantities.value = {};
    itemSearch.value = '';
    createOpen.value = true;
}

function submit() {
    form.transform((data) => ({
        ...data,
        items: Object.entries(quantities.value)
            .filter(([, q]) => q.trim() !== '' && Number(q) > 0)
            .map(([id, quantity]) => ({
                rate_card_item_id: Number(id),
                quantity: quantity.trim(),
            })),
    })).post(instalaciones.store().url, {
        preserveScroll: true,
        onSuccess: () => (createOpen.value = false),
    });
}

const itemErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('items'))
        .map(([, message]) => message),
);
</script>

<template>
    <Head title="Instalaciones" />

    <PageContainer>
        <PageHeader
            title="Instalaciones"
            description="Del tarifario congelado a la evidencia del instalador, la conformidad del cliente y la revisión de calidad."
        >
            <template #meta>
                <span
                    ><strong
                        class="font-semibold text-foreground tabular-nums"
                        >{{ total }}</strong
                    >
                    órdenes</span
                >
            </template>
            <template v-if="canManage" #actions>
                <Button @click="openCreate"
                    ><Plus class="size-4" /> Nueva orden</Button
                >
            </template>
        </PageHeader>

        <div
            v-if="pendingExtras > 0"
            class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning-ink"
            role="status"
        >
            <Sparkles class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
                <strong class="font-semibold"
                    >{{ pendingExtras }}
                    {{
                        pendingExtras === 1 ? 'extra espera' : 'extras esperan'
                    }}
                    aprobación.</strong
                >
                No suman al total hasta que alguien distinto al instalador líder
                los apruebe dentro de cada orden.
            </p>
        </div>

        <nav
            aria-label="Colas de instalación"
            class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
        >
            <ul class="flex w-max gap-1.5">
                <li>
                    <button
                        type="button"
                        :class="
                            cn(
                                'flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm transition-colors',
                                activeTab === null
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'bg-card hover:bg-muted',
                            )
                        "
                        :aria-pressed="activeTab === null"
                        @click="selectTab(null)"
                    >
                        Todas
                        <span class="tabular-nums opacity-70">{{ total }}</span>
                    </button>
                </li>
                <li v-for="tab in TABS" :key="tab.key">
                    <button
                        type="button"
                        :class="
                            cn(
                                'flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm transition-colors',
                                activeTab === tab.key
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'bg-card hover:bg-muted',
                            )
                        "
                        :aria-pressed="activeTab === tab.key"
                        @click="selectTab(tab.key)"
                    >
                        {{ tab.label }}
                        <span class="tabular-nums opacity-70">{{
                            countFor(tab.statuses)
                        }}</span>
                    </button>
                </li>
            </ul>
            <ul v-if="activeTab === 'closed'" class="mt-2 flex w-max gap-1.5">
                <li v-for="option in closedStatuses" :key="option.value">
                    <button
                        type="button"
                        :class="
                            cn(
                                'h-8 rounded-full border px-3 text-xs transition-colors',
                                status === option.value
                                    ? 'border-primary bg-primary-soft text-primary'
                                    : 'bg-card hover:bg-muted',
                            )
                        "
                        :aria-pressed="status === option.value"
                        @click="status = option.value"
                    >
                        {{ option.label }} · {{ counts[option.value] ?? 0 }}
                    </button>
                </li>
            </ul>
        </nav>

        <FilterBar
            v-model:search="search"
            placeholder="Folio de orden o proyecto"
            :active-count="installer ? 1 : 0"
            @clear="installer = null"
        >
            <template #filters>
                <SearchSelect
                    v-model="installer"
                    :options="options.installers"
                    placeholder="Todos los instaladores"
                    clearable
                    class="md:w-64"
                />
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="orders"
            :loading="loading"
            :row-href="(row) => instalaciones.show(row.id).url"
            item-label="órdenes"
        >
            <template #cell-folio="{ row }">
                <Link
                    :href="instalaciones.show(row.id).url"
                    class="font-medium underline-offset-4 hover:underline"
                    >{{ row.folio }}</Link
                >
                <StatusBadge
                    v-if="row.pendingExtras"
                    size="sm"
                    tone="warning"
                    :label="`${row.pendingExtras} extra(s)`"
                    class="ml-2"
                />
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
            <template #cell-scheduledStart="{ row }"
                ><DateText
                    :value="row.scheduledStart"
                    format="datetime"
                    fallback="Sin programar"
            /></template>
            <template #cell-leadInstaller="{ row }">
                <span :class="!row.leadInstaller && 'text-muted-foreground'">{{
                    row.leadInstaller?.name ?? 'Sin asignar'
                }}</span>
            </template>
            <template #cell-progress="{ row }">
                <div
                    v-if="row.itemsCount"
                    class="flex min-w-24 items-center gap-2"
                >
                    <div
                        class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        :aria-valuenow="row.doneCount ?? 0"
                        :aria-valuemax="row.itemsCount"
                    >
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="{
                                width: `${Math.round(((row.doneCount ?? 0) / row.itemsCount) * 100)}%`,
                            }"
                        />
                    </div>
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{{ row.doneCount ?? 0 }}/{{ row.itemsCount }}</span
                    >
                </div>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-status="{ row }"
                ><StatusBadge :tone="row.status.tone" :label="row.status.label"
            /></template>
            <template #cell-total="{ row }"
                ><Money :value="row.total"
            /></template>
            <template #empty>
                <EmptyState
                    :icon="Wrench"
                    :title="
                        status || search || installer
                            ? 'Ninguna instalación en esta cola'
                            : 'Aún no hay órdenes de instalación'
                    "
                    :description="
                        status || search || installer
                            ? 'Cambia de cola o quita los filtros.'
                            : 'Crea una orden con el tarifario vigente cuando el proyecto esté en fabricación o listo para instalar.'
                    "
                >
                    <Button
                        v-if="canManage && !status && !search && !installer"
                        @click="openCreate"
                        ><Plus class="size-4" /> Nueva orden</Button
                    >
                </EmptyState>
            </template>
        </DataView>
    </PageContainer>

    <FormSheet
        v-model:open="createOpen"
        title="Nueva orden de instalación"
        description="Elige el tarifario vigente y las partidas a instalar."
        size="xl"
    >
        <form
            id="installation-create"
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="ins-project">Proyecto</Label>
                <SearchSelect
                    id="ins-project"
                    v-model="form.project_id"
                    :options="projectOptions"
                    placeholder="Elige un proyecto"
                    empty-text="No hay proyectos en fabricación o listos para instalar."
                    :invalid="!!form.errors.project_id"
                />
                <InputError :message="form.errors.project_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="ins-supplier">Proveedor instalador</Label>
                    <SearchSelect
                        id="ins-supplier"
                        v-model="form.supplier_id"
                        :options="options.suppliers ?? []"
                        placeholder="Elige un proveedor"
                        :invalid="!!form.errors.supplier_id"
                    />
                    <InputError :message="form.errors.supplier_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="ins-lead">Instalador líder</Label>
                    <SearchSelect
                        id="ins-lead"
                        v-model="form.lead_installer_id"
                        :options="options.installers"
                        placeholder="Puedes asignarlo después"
                        clearable
                        :invalid="!!form.errors.lead_installer_id"
                    />
                    <InputError :message="form.errors.lead_installer_id" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="ins-card">Tarifario vigente</Label>
                <SearchSelect
                    id="ins-card"
                    v-model="form.rate_card_id"
                    :options="rateCardOptions"
                    :disabled="!form.supplier_id"
                    :placeholder="
                        form.supplier_id
                            ? 'Elige un tarifario'
                            : 'Primero elige el proveedor'
                    "
                    empty-text="No hay tarifarios vigentes para este proveedor."
                    :invalid="!!form.errors.rate_card_id"
                />
                <InputError :message="form.errors.rate_card_id" />
            </div>

            <section
                v-if="rateCard"
                class="flex flex-col gap-3"
                aria-labelledby="ins-items-title"
            >
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h3 id="ins-items-title" class="text-sm font-medium">
                            Partidas
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Movilización
                            <Money :value="rateCard.mobilizationFee" /> · Cargo
                            mínimo <Money :value="rateCard.minimumCharge" />
                        </p>
                    </div>
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{{ selectedCount }} seleccionadas</span
                    >
                </div>
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="itemSearch"
                        type="search"
                        class="pl-8"
                        placeholder="Buscar por clave o descripción"
                    />
                </div>
                <ul
                    class="max-h-[42vh] divide-y overflow-y-auto rounded-xl border"
                >
                    <li
                        v-for="item in visibleItems"
                        :key="item.id"
                        :class="
                            cn(
                                'flex items-center gap-3 px-3 py-2.5',
                                Number(quantities[item.id]) > 0 &&
                                    'bg-primary-soft/40',
                            )
                        "
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">
                                <span class="font-medium">{{ item.code }}</span>
                                · {{ item.description }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                <Money :value="item.unitPrice" /> por
                                {{ item.unit }}
                            </p>
                        </div>
                        <label class="sr-only" :for="`qty-${item.id}`"
                            >Cantidad de {{ item.code }}</label
                        >
                        <Input
                            :id="`qty-${item.id}`"
                            v-model="quantities[item.id]"
                            inputmode="decimal"
                            placeholder="0"
                            class="h-10 w-24 text-right tabular-nums"
                        />
                    </li>
                    <li
                        v-if="visibleItems.length === 0"
                        class="px-3 py-6 text-center text-sm text-muted-foreground"
                    >
                        Ningún concepto coincide.
                    </li>
                </ul>
                <p
                    v-for="(message, i) in itemErrors"
                    :key="i"
                    class="text-sm text-destructive"
                >
                    {{ message }}
                </p>
                <p
                    class="flex items-start gap-2 rounded-lg bg-muted/60 px-3 py-2.5 text-xs text-muted-foreground"
                >
                    <Lock class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                    Los precios se congelan al crear la orden: cambios futuros
                    al tarifario no la afectan. El total lo calcula el sistema.
                </p>
            </section>

            <div class="grid gap-2">
                <Label for="ins-notes">Notas internas</Label>
                <Textarea
                    id="ins-notes"
                    v-model="form.notes"
                    rows="3"
                    placeholder="Accesos, horarios del edificio, material a llevar…"
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
                form="installation-create"
                :disabled="form.processing || selectedCount === 0"
            >
                <Spinner v-if="form.processing" /> Crear orden
            </Button>
        </template>
    </FormSheet>
</template>
