<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    Columns3,
    List,
    MapPin,
    MoreHorizontal,
    Plus,
    UserRound,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { toast } from 'vue-sonner';
import DataView from '@/components/ds/DataView.vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import type { DataColumn } from '@/components/ds/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { LengthAwarePaginator } from '@/types/ui';

type Option = { value: string; label: string; tone?: string };
type NumOption = { value: number; label: string };

type LeadRow = {
    uuid: string;
    folio: string;
    status: Option;
    source: Option;
    customer: {
        uuid: string;
        name: string;
        phone: string | null;
        email: string | null;
    };
    assignee: { id: number; name: string } | null;
    postalCode: string | null;
    city: string | null;
    covered: boolean;
    budget: Option | null;
    nextFollowUpAt: string | null;
    firstContactedAt: string | null;
    createdAt: string | null;
    categories: string[];
};
type BoardLead = LeadRow & { transitions: string[] };
type BoardColumn = { status: Option; total: number; leads: BoardLead[] };

const props = defineProps<{
    leads: LengthAwarePaginator<LeadRow> | null;
    board: BoardColumn[] | null;
    filters: Record<string, string | undefined> & {
        modo: string;
        orden: string;
    };
    counts: {
        nuevos: number;
        sin_contactar: number;
        mios: number;
        seguimiento_hoy: number;
    };
    followUpHours: number;
    options: {
        statuses: Option[];
        sources: Option[];
        categories: NumOption[];
        assignees: NumOption[];
        budgetRanges: Option[];
        paymentPreferences: Option[];
        timeframes: Option[];
        preferredContacts: Option[];
        currentUserId: number;
    };
    can: { create: boolean; assign: boolean; update: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Prospectos', href: '/admin/prospectos' },
        ],
    },
});

const ALL = '__all';
const tone = (o: { tone?: string } | null | undefined) =>
    (o?.tone ?? 'neutral') as Tone;

// ---------------------------------------------------------------- Filtros

const search = ref(props.filters.q ?? '');
const vista = ref<string | null>(props.filters.vista ?? null);
const estado = ref(props.filters.estado ?? ALL);
const origen = ref(props.filters.origen ?? ALL);
const responsable = ref(props.filters.responsable ?? ALL);
const categoria = ref(
    props.filters.categoria ? String(props.filters.categoria) : ALL,
);
const desde = ref<string | null>(props.filters.desde ?? null);
const hasta = ref<string | null>(props.filters.hasta ?? null);
const orden = ref(props.filters.orden ?? 'recientes');
const modo = ref(props.filters.modo ?? 'lista');
const loading = ref(false);

const activeCount = computed(
    () =>
        [estado.value, origen.value, responsable.value, categoria.value].filter(
            (v) => v !== ALL,
        ).length +
        (desde.value ? 1 : 0) +
        (hasta.value ? 1 : 0),
);
const hasAnyFilter = computed(
    () => activeCount.value > 0 || !!search.value || !!vista.value,
);

function query(): Record<string, string | undefined> {
    const pick = (v: string) => (v === ALL ? undefined : v);

    return {
        q: search.value || undefined,
        vista: vista.value || undefined,
        estado: pick(estado.value),
        origen: pick(origen.value),
        responsable: pick(responsable.value),
        categoria: pick(categoria.value),
        desde: desde.value || undefined,
        hasta: hasta.value || undefined,
        orden: orden.value === 'recientes' ? undefined : orden.value,
        modo: modo.value === 'lista' ? undefined : modo.value,
    };
}

function reload() {
    router.get('/admin/prospectos', query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

watch(
    [
        search,
        vista,
        estado,
        origen,
        responsable,
        categoria,
        desde,
        hasta,
        orden,
        modo,
    ],
    reload,
);

function clearFilters() {
    estado.value = ALL;
    origen.value = ALL;
    responsable.value = ALL;
    categoria.value = ALL;
    desde.value = null;
    hasta.value = null;
}

const views = computed(() => [
    { value: null, label: 'Todos', count: null as number | null },
    { value: 'nuevos', label: 'Nuevos', count: props.counts.nuevos },
    {
        value: 'sin_contactar',
        label: `Sin contactar > ${props.followUpHours} h`,
        count: props.counts.sin_contactar,
    },
    { value: 'mios', label: 'Mis prospectos', count: props.counts.mios },
    {
        value: 'seguimiento_hoy',
        label: 'Seguimiento hoy',
        count: props.counts.seguimiento_hoy,
    },
]);

const sorts = [
    { value: 'recientes', label: 'Más recientes' },
    { value: 'antiguos', label: 'Más antiguos' },
    { value: 'seguimiento', label: 'Próximo seguimiento' },
    { value: 'folio', label: 'Folio' },
];

// ---------------------------------------------------------------- Lista

const columns: DataColumn<LeadRow>[] = [
    { key: 'folio', label: 'Prospecto', primary: true },
    { key: 'status', label: 'Etapa' },
    { key: 'categories', label: 'Interés', hideOnMobile: true },
    { key: 'postalCode', label: 'C.P.' },
    { key: 'assignee', label: 'Responsable' },
    { key: 'createdAt', label: 'Recibido' },
    { key: 'nextFollowUpAt', label: 'Seguimiento', hideOnMobile: true },
];

const showUrl = (uuid: string) => `/admin/prospectos/${uuid}`;
const isOverdue = (iso: string | null) =>
    !!iso && new Date(iso).getTime() < Date.now();

// ---------------------------------------------------------------- Tablero

const columnsState = ref<BoardColumn[]>([]);

function resetBoard() {
    columnsState.value = (props.board ?? []).map((c) => ({
        ...c,
        leads: [...c.leads],
    }));
}

watch(() => props.board, resetBoard, { immediate: true });

const pending = ref<string | null>(null);

function moveLead(lead: BoardLead, to: string) {
    if (to === lead.status.value) {
        return;
    }

    if (to === 'lost') {
        toast.info(
            'Para marcar un prospecto como perdido abre su detalle: ahí se registra el motivo.',
        );
        resetBoard();

        return;
    }

    if (!lead.transitions.includes(to)) {
        const label =
            props.options.statuses.find((s) => s.value === to)?.label ?? to;
        toast.error(
            `No se puede mover ${lead.folio} de «${lead.status.label}» a «${label}».`,
        );
        resetBoard();

        return;
    }

    pending.value = lead.uuid;
    router.patch(
        `/admin/prospectos/${lead.uuid}/etapa`,
        { status: to },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                toast.error(
                    Object.values(errors)[0] ?? 'No se pudo cambiar la etapa.',
                );
                resetBoard();
            },
            onFinish: () => (pending.value = null),
        },
    );
}

function onAdd(column: BoardColumn, event: { newIndex?: number }) {
    const lead = column.leads[event.newIndex ?? 0];

    if (lead) {
        moveLead(lead, column.status.value);
    }
}

function canDrop(event: { dragged: HTMLElement; to: HTMLElement }): boolean {
    const to = event.to.dataset.status ?? '';
    const allowed = (event.dragged.dataset.transitions ?? '').split(',');

    return (
        to !== 'lost' &&
        (to === event.dragged.dataset.status || allowed.includes(to))
    );
}

function transitionsFor(lead: BoardLead): Option[] {
    return props.options.statuses.filter(
        (s) => s.value !== 'lost' && lead.transitions.includes(s.value),
    );
}

// ---------------------------------------------------------------- Alta manual

const createOpen = ref(false);
const form = useForm({
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    preferred_contact: 'whatsapp' as string,
    source: 'phone' as string,
    category_ids: [] as number[],
    postal_code: '',
    city: '',
    state: '',
    budget_range: null as string | null,
    payment_preference: null as string | null,
    timeframe: null as string | null,
    comments: '',
    assigned_to: null as number | null,
});

function toggleCategory(id: number) {
    form.category_ids = form.category_ids.includes(id)
        ? form.category_ids.filter((c) => c !== id)
        : [...form.category_ids, id];
}

function openCreate() {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function submit() {
    form.post('/admin/prospectos', {
        onSuccess: () => (createOpen.value = false),
    });
}

const optionalSelects = computed(() => [
    {
        key: 'budget_range' as const,
        label: 'Presupuesto aproximado',
        options: props.options.budgetRanges,
    },
    {
        key: 'payment_preference' as const,
        label: 'Forma de pago preferida',
        options: props.options.paymentPreferences,
    },
    {
        key: 'timeframe' as const,
        label: '¿Para cuándo?',
        options: props.options.timeframes,
    },
]);
</script>

<template>
    <Head title="Prospectos" />

    <PageContainer>
        <PageHeader
            title="Prospectos"
            description="Solicitudes de clientes potenciales: quién pidió información, en qué etapa va y cuándo darle seguimiento."
        >
            <template #actions>
                <ToggleGroup
                    :model-value="modo"
                    type="single"
                    variant="outline"
                    aria-label="Forma de ver los prospectos"
                    @update:model-value="
                        (v) => (modo = (v as string) || 'lista')
                    "
                >
                    <ToggleGroupItem value="lista" aria-label="Lista"
                        ><List class="size-4" />
                        <span class="hidden sm:inline"
                            >Lista</span
                        ></ToggleGroupItem
                    >
                    <ToggleGroupItem
                        value="tablero"
                        aria-label="Tablero por etapa"
                        ><Columns3 class="size-4" />
                        <span class="hidden sm:inline"
                            >Tablero</span
                        ></ToggleGroupItem
                    >
                </ToggleGroup>
                <Button v-if="can.create" @click="openCreate"
                    ><Plus class="size-4" /> Nuevo prospecto</Button
                >
            </template>
        </PageHeader>

        <!-- Vistas rápidas -->
        <nav
            aria-label="Vistas rápidas"
            class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
        >
            <ul class="flex w-max gap-1.5">
                <li v-for="view in views" :key="view.label">
                    <button
                        type="button"
                        :aria-pressed="vista === view.value"
                        :class="
                            cn(
                                'flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm transition-colors',
                                vista === view.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'bg-card hover:bg-muted',
                            )
                        "
                        @click="vista = view.value"
                    >
                        {{ view.label }}
                        <span
                            v-if="view.count !== null"
                            class="tabular-nums opacity-70"
                            >{{ view.count }}</span
                        >
                    </button>
                </li>
            </ul>
        </nav>

        <FilterBar
            v-model:search="search"
            placeholder="Folio, nombre, correo o teléfono"
            :active-count="activeCount"
            @clear="clearFilters"
        >
            <template #filters>
                <Select v-model="estado">
                    <SelectTrigger class="md:w-44" aria-label="Etapa"
                        ><SelectValue placeholder="Etapa"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todas las etapas</SelectItem>
                        <SelectItem
                            v-for="s in options.statuses"
                            :key="s.value"
                            :value="s.value"
                            >{{ s.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="origen">
                    <SelectTrigger class="md:w-44" aria-label="Origen"
                        ><SelectValue placeholder="Origen"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todos los orígenes</SelectItem>
                        <SelectItem
                            v-for="s in options.sources"
                            :key="s.value"
                            :value="s.value"
                            >{{ s.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="responsable">
                    <SelectTrigger class="md:w-48" aria-label="Responsable"
                        ><SelectValue placeholder="Responsable"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL"
                            >Cualquier responsable</SelectItem
                        >
                        <SelectItem value="yo">Asignados a mí</SelectItem>
                        <SelectItem value="sin">Sin responsable</SelectItem>
                        <SelectItem
                            v-for="a in options.assignees"
                            :key="a.value"
                            :value="String(a.value)"
                            >{{ a.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-if="options.categories.length" v-model="categoria">
                    <SelectTrigger class="md:w-48" aria-label="Interés"
                        ><SelectValue placeholder="Interés"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Cualquier espacio</SelectItem>
                        <SelectItem
                            v-for="c in options.categories"
                            :key="c.value"
                            :value="String(c.value)"
                            >{{ c.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <DatePicker
                    v-model="desde"
                    placeholder="Desde"
                    :max="hasta ?? undefined"
                    clearable
                    class="md:w-36"
                />
                <DatePicker
                    v-model="hasta"
                    placeholder="Hasta"
                    :min="desde ?? undefined"
                    clearable
                    class="md:w-36"
                />
            </template>
            <template v-if="modo === 'lista'" #actions>
                <Select v-model="orden">
                    <SelectTrigger class="w-48" aria-label="Ordenar"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="s in sorts"
                            :key="s.value"
                            :value="s.value"
                            >{{ s.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </template>
        </FilterBar>

        <!-- Lista -->
        <DataView
            v-if="modo === 'lista' && leads"
            :columns="columns"
            :paginator="leads"
            :loading="loading"
            row-key="uuid"
            :row-href="(row) => showUrl(row.uuid)"
            item-label="prospectos"
            caption="Prospectos"
        >
            <template #cell-folio="{ row }">
                <div class="min-w-0">
                    <Link
                        :href="showUrl(row.uuid)"
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.customer.name }}</Link
                    >
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ row.folio }} · {{ row.source.label }}
                    </p>
                </div>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="tone(row.status)"
                    :label="row.status.label"
                    size="sm"
                />
            </template>
            <template #cell-categories="{ row }">
                <span class="line-clamp-2 text-sm">{{
                    row.categories.join(', ') || '—'
                }}</span>
            </template>
            <template #cell-postalCode="{ row }">
                <span
                    v-if="row.postalCode"
                    class="inline-flex items-center gap-1.5 tabular-nums"
                >
                    {{ row.postalCode }}
                    <span
                        :class="
                            cn(
                                'size-1.5 rounded-full',
                                row.covered
                                    ? 'bg-success'
                                    : 'bg-muted-foreground/40',
                            )
                        "
                        aria-hidden="true"
                    />
                    <span class="sr-only">{{
                        row.covered ? 'con cobertura' : 'sin cobertura'
                    }}</span>
                </span>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #cell-assignee="{ row }">
                <span v-if="row.assignee">{{ row.assignee.name }}</span>
                <span v-else class="text-muted-foreground">Sin asignar</span>
            </template>
            <template #cell-createdAt="{ row }">
                <DateText :value="row.createdAt" relative />
            </template>
            <template #cell-nextFollowUpAt="{ row }">
                <span
                    v-if="row.nextFollowUpAt"
                    :class="
                        cn(
                            'inline-flex items-center gap-1.5',
                            isOverdue(row.nextFollowUpAt) && 'text-danger-ink',
                        )
                    "
                >
                    <CalendarClock class="size-3.5" aria-hidden="true" />
                    <DateText :value="row.nextFollowUpAt" format="datetime" />
                </span>
                <span v-else class="text-muted-foreground">—</span>
            </template>
            <template #empty>
                <EmptyState
                    v-if="hasAnyFilter"
                    :icon="UserRound"
                    title="Ningún prospecto coincide"
                    description="Prueba con otra búsqueda o quita algunos filtros."
                >
                    <Button
                        variant="outline"
                        @click="(clearFilters(), (search = ''), (vista = null))"
                        >Quitar filtros</Button
                    >
                </EmptyState>
                <EmptyState
                    v-else
                    :icon="UserRound"
                    title="Aún no hay prospectos"
                    description="Aún no hay prospectos. Cuando alguien envíe el formulario de solicitud aparecerá aquí."
                />
            </template>
        </DataView>

        <!-- Tablero -->
        <section
            v-else-if="modo === 'tablero'"
            aria-label="Tablero por etapa"
            class="-mx-4 md:mx-0"
        >
            <p class="mb-3 px-4 text-sm text-muted-foreground md:px-0">
                Arrastra una tarjeta a otra etapa o usa «Mover a…». Para marcar
                como perdido abre el prospecto y registra el motivo.
            </p>
            <div
                class="flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-4 md:px-0"
                :aria-busy="loading"
            >
                <div
                    v-for="column in columnsState"
                    :key="column.status.value"
                    class="flex w-[85vw] max-w-80 shrink-0 snap-start flex-col rounded-xl border bg-muted/40 sm:w-72"
                >
                    <header
                        class="flex items-center justify-between gap-2 border-b px-3 py-2.5"
                    >
                        <StatusBadge
                            :tone="tone(column.status)"
                            :label="column.status.label"
                            size="sm"
                        />
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ column.total }}</span
                        >
                    </header>
                    <VueDraggable
                        v-model="column.leads"
                        :group="{
                            name: 'leads',
                            pull: can.update,
                            put: can.update,
                        }"
                        :disabled="!can.update"
                        :animation="150"
                        ghost-class="opacity-40"
                        :data-status="column.status.value"
                        :on-move="canDrop"
                        class="flex min-h-24 flex-1 flex-col gap-2 p-2"
                        @add="(e) => onAdd(column, e)"
                    >
                        <article
                            v-for="lead in column.leads"
                            :key="lead.uuid"
                            :data-status="lead.status.value"
                            :data-transitions="lead.transitions.join(',')"
                            :class="
                                cn(
                                    'group relative rounded-lg border bg-card p-3 shadow-xs transition-shadow hover:shadow-sm',
                                    can.update &&
                                        'cursor-grab active:cursor-grabbing',
                                    pending === lead.uuid && 'opacity-60',
                                )
                            "
                        >
                            <div class="flex items-start justify-between gap-2">
                                <Link
                                    :href="showUrl(lead.uuid)"
                                    class="min-w-0 leading-snug font-medium underline-offset-4 hover:underline focus-visible:underline"
                                >
                                    {{ lead.customer.name }}
                                </Link>
                                <DropdownMenu
                                    v-if="
                                        can.update &&
                                        transitionsFor(lead).length
                                    "
                                >
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon-xs"
                                            :aria-label="`Mover ${lead.folio} a otra etapa`"
                                        >
                                            <MoreHorizontal />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuLabel
                                            >Mover a…</DropdownMenuLabel
                                        >
                                        <DropdownMenuItem
                                            v-for="t in transitionsFor(lead)"
                                            :key="t.value"
                                            @select="moveLead(lead, t.value)"
                                        >
                                            {{ t.label }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            <p
                                class="mt-0.5 text-xs text-muted-foreground tabular-nums"
                            >
                                {{ lead.folio }}
                            </p>
                            <p
                                v-if="lead.categories.length"
                                class="mt-2 line-clamp-1 text-sm"
                            >
                                {{ lead.categories.join(', ') }}
                            </p>
                            <div
                                class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="lead.postalCode"
                                    class="inline-flex items-center gap-1"
                                    ><MapPin
                                        class="size-3"
                                        aria-hidden="true"
                                    />{{ lead.postalCode }}</span
                                >
                                <span>{{
                                    lead.assignee?.name ?? 'Sin asignar'
                                }}</span>
                                <DateText :value="lead.createdAt" relative />
                            </div>
                        </article>
                    </VueDraggable>
                    <p
                        v-if="column.total > column.leads.length"
                        class="px-3 pb-3 text-xs text-muted-foreground"
                    >
                        Se muestran {{ column.leads.length }} de
                        {{ column.total }}. Usa la lista para ver todos.
                    </p>
                </div>
            </div>
        </section>
    </PageContainer>

    <!-- Alta manual -->
    <FormSheet
        v-model:open="createOpen"
        title="Nuevo prospecto"
        description="Registra a alguien que llamó, escribió o visitó la sala de exhibición."
        size="lg"
    >
        <form id="lead-create" class="grid gap-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="lc-first">Nombre</Label>
                    <Input
                        id="lc-first"
                        v-model="form.first_name"
                        autocomplete="off"
                        :aria-invalid="!!form.errors.first_name"
                    />
                    <InputError :message="form.errors.first_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="lc-last"
                        >Apellidos
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Input
                        id="lc-last"
                        v-model="form.last_name"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.last_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="lc-phone">Teléfono</Label>
                    <Input
                        id="lc-phone"
                        v-model="form.phone"
                        type="tel"
                        inputmode="tel"
                        placeholder="10 dígitos"
                        :aria-invalid="!!form.errors.phone"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="lc-email">Correo</Label>
                    <Input
                        id="lc-email"
                        v-model="form.email"
                        type="email"
                        :aria-invalid="!!form.errors.email"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="lc-contact"
                        >Prefiere que lo contactemos por</Label
                    >
                    <Select v-model="form.preferred_contact">
                        <SelectTrigger id="lc-contact" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="o in options.preferredContacts"
                                :key="o.value"
                                :value="o.value"
                                >{{ o.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.preferred_contact" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="lc-source">¿Cómo llegó?</Label>
                    <Select v-model="form.source">
                        <SelectTrigger id="lc-source" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="o in options.sources"
                                :key="o.value"
                                :value="o.value"
                                >{{ o.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.source" />
                </div>
            </div>

            <fieldset v-if="options.categories.length" class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">
                    ¿Qué quiere renovar?
                </legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="c in options.categories"
                        :key="c.value"
                        type="button"
                        :aria-pressed="form.category_ids.includes(c.value)"
                        :class="
                            cn(
                                'h-9 rounded-full border px-3.5 text-sm transition-colors',
                                form.category_ids.includes(c.value)
                                    ? 'border-primary bg-primary-soft text-primary'
                                    : 'bg-card hover:bg-muted',
                            )
                        "
                        @click="toggleCategory(c.value)"
                    >
                        {{ c.label }}
                    </button>
                </div>
                <InputError :message="form.errors.category_ids" />
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <Label for="lc-cp">Código postal</Label>
                    <Input
                        id="lc-cp"
                        v-model="form.postal_code"
                        inputmode="numeric"
                        maxlength="5"
                    />
                    <InputError :message="form.errors.postal_code" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="lc-city">Ciudad</Label>
                    <Input id="lc-city" v-model="form.city" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="lc-state">Estado</Label>
                    <Input id="lc-state" v-model="form.state" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="field in optionalSelects"
                    :key="field.key"
                    class="grid gap-1.5"
                >
                    <Label :for="`lc-${field.key}`">{{ field.label }}</Label>
                    <Select v-model="form[field.key]">
                        <SelectTrigger :id="`lc-${field.key}`" class="w-full"
                            ><SelectValue placeholder="Sin indicar"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="o in field.options"
                                :key="o.value"
                                :value="o.value"
                                >{{ o.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div v-if="can.assign" class="grid gap-1.5">
                <Label for="lc-assignee">Responsable</Label>
                <SearchSelect
                    id="lc-assignee"
                    v-model="form.assigned_to"
                    :options="options.assignees"
                    placeholder="Asignación automática"
                    clearable
                />
                <p class="text-xs text-muted-foreground">
                    Si lo dejas vacío, se asigna por turno al equipo comercial.
                </p>
                <InputError :message="form.errors.assigned_to" />
            </div>

            <div class="grid gap-1.5">
                <Label for="lc-comments">Comentarios</Label>
                <Textarea
                    id="lc-comments"
                    v-model="form.comments"
                    rows="3"
                    placeholder="Lo que te contó: medidas, estilo, urgencia…"
                />
                <InputError :message="form.errors.comments" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="createOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="lead-create"
                :disabled="form.processing"
            >
                <Spinner v-if="form.processing" />
                {{ form.processing ? 'Guardando…' : 'Registrar prospecto' }}
            </Button>
        </template>
    </FormSheet>
</template>
