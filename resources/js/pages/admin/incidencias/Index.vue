<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, ShieldAlert, ShieldCheck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    DataView,
    DatePicker,
    DateText,
    FileDropzone,
    FilterBar,
    FormSheet,
    PageContainer,
    PageHeader,
    SearchSelect,
    StatusBadge,
} from '@/components/ds';
import type { DataColumn, SearchSelectOption } from '@/components/ds';
import InputError from '@/components/InputError.vue';
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
import { apiFetch } from '@/lib/http';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import incidencias from '@/routes/admin/incidencias';
import operaciones from '@/routes/admin/operaciones';
import type { BreadcrumbItem, LengthAwarePaginator } from '@/types';
import type {
    EnumOption,
    IncidentRow,
    ProjectRef,
    SelectOption,
} from '@/types/operations';

type Options = {
    categories: EnumOption[];
    severities: EnumOption[];
    statuses: EnumOption[];
    origins: EnumOption[];
    parties: EnumOption[];
    suppliers?: SelectOption[];
    assignees?: SelectOption[];
};

type LookupProject = ProjectRef & {
    installationOrders: SelectOption[];
    fabricationOrders: SelectOption[];
};

const props = defineProps<{
    incidents: LengthAwarePaginator<IncidentRow>;
    filters: {
        q?: string;
        estado: string;
        severidad?: string;
        categoria?: string;
        proyecto?: number;
        mias?: boolean | string;
    };
    openBySeverity: Record<string, number>;
    options: Options;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Incidencias', href: '/admin/incidencias' },
        ] as BreadcrumbItem[],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('incidents.manage'));
const ALL = '__todas__';

// ------------------------------------------------------------ Filtros
const search = ref(props.filters.q ?? '');
const estado = ref(props.filters.estado || 'abiertas');
const severidad = ref(props.filters.severidad || ALL);
const categoria = ref(props.filters.categoria || ALL);
const mias = ref(props.filters.mias === true || props.filters.mias === '1');
const loading = ref(false);

const activeCount = computed(
    () =>
        [
            estado.value !== 'abiertas',
            severidad.value !== ALL,
            categoria.value !== ALL,
            mias.value,
        ].filter(Boolean).length,
);

function visit() {
    router.get(
        incidencias.index().url,
        {
            q: search.value || undefined,
            estado: estado.value === 'abiertas' ? undefined : estado.value,
            severidad: severidad.value === ALL ? undefined : severidad.value,
            categoria: categoria.value === ALL ? undefined : categoria.value,
            proyecto: props.filters.proyecto || undefined,
            mias: mias.value ? 1 : undefined,
        },
        {
            preserveState: true,
            replace: true,
            only: ['incidents', 'filters', 'openBySeverity'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

watch([search, estado, severidad, categoria, mias], visit);

function clearFilters() {
    search.value = '';
    estado.value = 'abiertas';
    severidad.value = ALL;
    categoria.value = ALL;
    mias.value = false;
}

function filterSeverity(value: string) {
    severidad.value = severidad.value === value ? ALL : value;
    estado.value = 'abiertas';
}

const columns: DataColumn<IncidentRow>[] = [
    { key: 'title', label: 'Incidencia', primary: true },
    { key: 'severity', label: 'Severidad' },
    { key: 'project', label: 'Proyecto' },
    { key: 'status', label: 'Estado' },
    { key: 'assignee', label: 'Responsable', hideOnMobile: true },
    { key: 'dueOn', label: 'Compromiso', align: 'right' },
];

const openTotal = computed(() =>
    Object.values(props.openBySeverity).reduce((a, b) => a + b, 0),
);

// ------------------------------------------------------------ Alta
const sheetOpen = ref(false);
const projectOptions = ref<SearchSelectOption<number>[]>([]);
const projectCache = ref<Record<number, LookupProject>>({});
const searchingProjects = ref(false);

const form = useForm({
    project_id: null as number | null,
    installation_order_id: null as number | null,
    fabrication_order_id: null as number | null,
    category: props.options.categories[0]?.value ?? '',
    severity: 'medium',
    title: '',
    description: '',
    responsible_party: null as string | null,
    responsible_supplier_id: null as number | null,
    assigned_to: null as number | null,
    action_plan: '',
    cost: '',
    due_on: null as string | null,
    visible_to_client: true,
    media_ids: [] as number[],
});

let searchTimer: ReturnType<typeof setTimeout> | undefined;
async function searchProjects(term: string) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        searchingProjects.value = true;
        try {
            const response = await apiFetch(
                operaciones.proyectos({ query: { q: term } }).url,
            );
            const json = (await response.json()) as { data: LookupProject[] };
            json.data.forEach((p) => (projectCache.value[p.id] = p));
            projectOptions.value = json.data.map((p) => ({
                value: p.id,
                label: `${p.folio} · ${p.name}`,
                description: p.customer ?? undefined,
            }));
        } finally {
            searchingProjects.value = false;
        }
    }, 250);
}

const selectedProject = computed(() =>
    form.project_id ? projectCache.value[form.project_id] : null,
);

watch(
    () => form.project_id,
    () => {
        form.installation_order_id = null;
        form.fabrication_order_id = null;
    },
);

watch(
    () => form.responsible_party,
    (party) => {
        if (party !== 'supplier') form.responsible_supplier_id = null;
    },
);

function openCreate() {
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
    if (projectOptions.value.length === 0) void searchProjects('');
}

function submit() {
    form.post(incidencias.store().url, {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
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
const NONE = '__ninguno__';
</script>

<template>
    <Head title="Incidencias" />

    <PageContainer>
        <PageHeader
            title="Incidencias"
            description="Faltantes, daños y errores por resolver. Lo crítico va primero."
        >
            <template #actions>
                <Button v-if="canManage" @click="openCreate"
                    ><Plus class="size-4" /> Nueva incidencia</Button
                >
            </template>
        </PageHeader>

        <section
            aria-label="Incidencias abiertas por severidad"
            class="grid grid-cols-2 gap-2 sm:grid-cols-4"
        >
            <button
                v-for="sev in [...options.severities].reverse()"
                :key="sev.value"
                type="button"
                :aria-pressed="severidad === sev.value"
                :class="
                    cn(
                        'flex flex-col items-start gap-1 rounded-xl border bg-card px-4 py-3 text-left transition-colors hover:border-primary/40',
                        severidad === sev.value &&
                            'border-primary ring-2 ring-primary/20',
                    )
                "
                @click="filterSeverity(sev.value)"
            >
                <StatusBadge :tone="sev.tone" :label="sev.label" size="sm" />
                <span
                    class="font-display text-2xl font-semibold tabular-nums"
                    >{{ openBySeverity[sev.value] ?? 0 }}</span
                >
                <span class="text-xs text-muted-foreground">abiertas</span>
            </button>
        </section>

        <FilterBar
            v-model:search="search"
            placeholder="Folio, título o proyecto"
            :active-count="activeCount"
            @clear="clearFilters"
        >
            <template #filters>
                <div class="grid gap-1.5">
                    <Label class="text-xs text-muted-foreground">Estado</Label>
                    <Select v-model="estado">
                        <SelectTrigger class="w-full md:w-44"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="abiertas"
                                >Abiertas y en atención</SelectItem
                            >
                            <SelectItem
                                v-for="s in options.statuses"
                                :key="s.value"
                                :value="s.value"
                                >{{ s.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs text-muted-foreground"
                        >Severidad</Label
                    >
                    <Select v-model="severidad">
                        <SelectTrigger class="w-full md:w-36"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Todas</SelectItem>
                            <SelectItem
                                v-for="s in options.severities"
                                :key="s.value"
                                :value="s.value"
                                >{{ s.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label class="text-xs text-muted-foreground"
                        >Categoría</Label
                    >
                    <Select v-model="categoria">
                        <SelectTrigger class="w-full md:w-48"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Todas</SelectItem>
                            <SelectItem
                                v-for="c in options.categories"
                                :key="c.value"
                                :value="c.value"
                                >{{ c.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <label class="flex h-9 items-center gap-2 self-end text-sm">
                    <Switch v-model="mias" />
                    Solo asignadas a mí
                </label>
            </template>
        </FilterBar>

        <p
            v-if="openTotal === 0 && estado === 'abiertas' && !search"
            class="rounded-xl border border-success/25 bg-success-soft px-4 py-3 text-sm text-success-ink"
        >
            <ShieldCheck class="mr-1 inline size-4" aria-hidden="true" />
            No hay incidencias abiertas. Buen trabajo.
        </p>

        <DataView
            :columns="columns"
            :paginator="incidents"
            :loading="loading"
            :row-href="(row) => incidencias.show(row.id).url"
            item-label="incidencias"
            empty-title="Sin incidencias con estos filtros"
            empty-description="Cambia los filtros o registra una nueva incidencia cuando detectes un problema."
        >
            <template #cell-title="{ row }">
                <a
                    :href="incidencias.show(row.id).url"
                    class="flex flex-col gap-0.5"
                >
                    <span class="font-medium text-foreground hover:underline">{{
                        row.title
                    }}</span>
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{{ row.folio }} · {{ row.category.label }} ·
                        {{ row.origin.label }}</span
                    >
                </a>
            </template>
            <template #cell-severity="{ row }">
                <StatusBadge
                    :tone="row.severity.tone"
                    :label="row.severity.label"
                    size="sm"
                />
            </template>
            <template #cell-project="{ row }">
                <span class="flex flex-col">
                    <span class="text-sm">{{ row.project.name }}</span>
                    <span class="text-xs text-muted-foreground tabular-nums">{{
                        row.project.folio
                    }}</span>
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="row.status.tone"
                    :label="row.status.label"
                    size="sm"
                />
            </template>
            <template #cell-assignee="{ row }">
                <span :class="!row.assignee && 'text-muted-foreground'">{{
                    row.assignee?.name ?? 'Sin asignar'
                }}</span>
            </template>
            <template #cell-dueOn="{ row }">
                <span
                    :class="
                        cn('text-sm', row.overdue && 'font-medium text-danger')
                    "
                >
                    <DateText
                        :value="row.dueOn"
                        format="medium"
                        fallback="Sin fecha"
                    />
                    <span v-if="row.overdue" class="block text-xs"
                        >Vencida</span
                    >
                </span>
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-12 text-center"
                >
                    <ShieldAlert
                        class="size-8 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p class="font-medium">Sin incidencias con estos filtros</p>
                    <p class="max-w-sm text-sm text-muted-foreground">
                        Cambia los filtros o registra una incidencia cuando
                        detectes un problema.
                    </p>
                    <Button
                        v-if="canManage"
                        variant="outline"
                        size="sm"
                        class="mt-2"
                        @click="openCreate"
                        >Registrar incidencia</Button
                    >
                </div>
            </template>
        </DataView>
    </PageContainer>

    <FormSheet
        v-model:open="sheetOpen"
        title="Nueva incidencia"
        description="Registra el problema, quién lo atiende y para cuándo."
        size="lg"
    >
        <form
            id="incident-form"
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5">
                <Label for="inc-project">Proyecto</Label>
                <SearchSelect
                    id="inc-project"
                    v-model="form.project_id"
                    :options="projectOptions"
                    remote
                    placeholder="Busca por folio, nombre o cliente"
                    :empty-text="
                        searchingProjects
                            ? 'Buscando…'
                            : 'Sin proyectos que coincidan.'
                    "
                    :invalid="!!form.errors.project_id"
                    @search="searchProjects"
                />
                <InputError :message="form.errors.project_id" />
            </div>

            <div
                v-if="
                    selectedProject &&
                    (selectedProject.installationOrders.length ||
                        selectedProject.fabricationOrders.length)
                "
                class="grid gap-4 sm:grid-cols-2"
            >
                <div
                    v-if="selectedProject.installationOrders.length"
                    class="grid gap-1.5"
                >
                    <Label>Orden de instalación (opcional)</Label>
                    <SearchSelect
                        v-model="form.installation_order_id"
                        :options="selectedProject.installationOrders"
                        clearable
                        placeholder="Sin vincular"
                    />
                    <InputError :message="form.errors.installation_order_id" />
                </div>
                <div
                    v-if="selectedProject.fabricationOrders.length"
                    class="grid gap-1.5"
                >
                    <Label>Orden de fabricación (opcional)</Label>
                    <SearchSelect
                        v-model="form.fabrication_order_id"
                        :options="selectedProject.fabricationOrders"
                        clearable
                        placeholder="Sin vincular"
                    />
                    <InputError :message="form.errors.fabrication_order_id" />
                </div>
            </div>

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
                    <InputError :message="form.errors.category" />
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
                    <InputError :message="form.errors.severity" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="inc-title">Título</Label>
                <Input
                    id="inc-title"
                    v-model="form.title"
                    maxlength="200"
                    placeholder="Ej. Falta la puerta del módulo alto 3"
                />
                <InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-1.5">
                <Label for="inc-desc">Qué pasó</Label>
                <Textarea id="inc-desc" v-model="form.description" rows="4" />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label>Responsable de atenderla</Label>
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
                        :invalid="!!form.errors.responsible_supplier_id"
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
                    <InputError :message="form.errors.assigned_to" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Fecha compromiso</Label>
                    <DatePicker
                        v-model="form.due_on"
                        clearable
                        :invalid="!!form.errors.due_on"
                    />
                    <InputError :message="form.errors.due_on" />
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="inc-plan">Plan de acción (opcional)</Label>
                <Textarea id="inc-plan" v-model="form.action_plan" rows="3" />
            </div>
            <div class="grid gap-1.5 sm:max-w-56">
                <Label for="inc-cost">Costo estimado (opcional)</Label>
                <Input
                    id="inc-cost"
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
                        >La verá en su portal y recibirá avisos de cambios de
                        estado.</span
                    >
                </span>
            </label>

            <div class="grid gap-1.5">
                <Label>Evidencia (opcional)</Label>
                <FileDropzone
                    visibility="private"
                    collection="privado/incidencias"
                    compact
                    :accept="['image/*', 'video/*', '.pdf']"
                    label="Arrastra fotos, videos o PDF"
                    @uploaded="form.media_ids.push($event.id)"
                />
                <InputError :message="form.errors.media_ids" />
            </div>
        </form>

        <template #footer>
            <Button variant="outline" @click="sheetOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="incident-form"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Registrando…' : 'Registrar incidencia' }}
            </Button>
        </template>
    </FormSheet>
</template>
