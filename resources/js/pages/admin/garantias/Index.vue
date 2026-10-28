<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CalendarClock,
    ChevronDown,
    LifeBuoy,
    Plus,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import {
    DataView,
    DatePicker,
    DateText,
    FilterBar,
    FormSheet,
    PageContainer,
    PageHeader,
    SearchSelect,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import type { DataColumn, SearchSelectOption } from '@/components/ds';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import garantias from '@/routes/admin/garantias';
import type { BreadcrumbItem, LengthAwarePaginator } from '@/types';
import type {
    EnumOption,
    ProjectRef,
    SelectOption,
    WarrantyRow,
} from '@/types/operations';

type OpenClaim = {
    id: number;
    folio: string;
    warrantyId: number;
    warrantyFolio: string;
    type: EnumOption;
    project: string;
    status: EnumOption;
    createdAt: string | null;
};

const props = defineProps<{
    warranties: LengthAwarePaginator<WarrantyRow>;
    openClaims: OpenClaim[];
    filters: {
        q?: string;
        estado?: string;
        tipo?: string;
        proyecto?: number | string;
    };
    expiringSoon: number;
    options: {
        types: EnumOption[];
        statuses: EnumOption[];
        suppliers?: SelectOption[];
        projects?: (ProjectRef & { completedAt: string | null })[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Garantías', href: '/admin/garantias' },
        ] as BreadcrumbItem[],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('warranties.manage'));
const ALL = '__todas__';

// ------------------------------------------------------------ Filtros
const search = ref(props.filters.q ?? '');
const estado = ref(props.filters.estado || ALL);
const tipo = ref(props.filters.tipo || ALL);
const loading = ref(false);

watch([search, estado, tipo], () => {
    router.get(
        garantias.index().url,
        {
            q: search.value || undefined,
            estado: estado.value === ALL ? undefined : estado.value,
            tipo: tipo.value === ALL ? undefined : tipo.value,
            proyecto: props.filters.proyecto || undefined,
        },
        {
            preserveState: true,
            replace: true,
            only: ['warranties', 'filters'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
});

const columns: DataColumn<WarrantyRow>[] = [
    { key: 'project', label: 'Proyecto', primary: true },
    { key: 'type', label: 'Tipo' },
    { key: 'endsOn', label: 'Vigencia' },
    { key: 'status', label: 'Estado' },
    {
        key: 'supplier',
        label: 'Proveedor responsable',
        hideOnMobile: true,
        value: (r) => r.supplier?.name ?? '—',
    },
];

// ------------------------------------------------------------ Registro
type Block = {
    enabled: boolean;
    supplier_id: number | null;
    starts_on: string | null;
    ends_on: string | null;
    scope: string;
    exclusions: string;
};

const sheetOpen = ref(false);
const blocks = reactive<Record<string, Block>>({});

function resetBlocks() {
    for (const type of props.options.types) {
        blocks[type.value] = {
            enabled: false,
            supplier_id: null,
            starts_on: null,
            ends_on: null,
            scope: '',
            exclusions: '',
        };
    }
}
resetBlocks();

const form = useForm({
    project_id: null as number | null,
    warranties: [] as Array<Record<string, string | number | null>>,
});

const enabledTypes = computed(() =>
    props.options.types.filter((t) => blocks[t.value]?.enabled),
);

function errorFor(type: string, field: string): string | undefined {
    const index = enabledTypes.value.findIndex((t) => t.value === type);
    const errors = form.errors as Record<string, string | undefined>;

    return index >= 0 ? errors[`warranties.${index}.${field}`] : undefined;
}

const projectOptions = computed<SearchSelectOption<number>[]>(() =>
    (props.options.projects ?? []).map((p) => ({
        value: p.id,
        label: `${p.folio} · ${p.name}`,
        description: [p.customer, p.status.label].filter(Boolean).join(' · '),
    })),
);
const supplierOptions = computed<SearchSelectOption<number>[]>(() =>
    (props.options.suppliers ?? []).map((s) => ({
        value: s.value,
        label: s.label,
    })),
);

function openRegister() {
    form.reset();
    form.clearErrors();
    resetBlocks();
    if (props.filters.proyecto)
        form.project_id = Number(props.filters.proyecto);
    sheetOpen.value = true;
}

function submit() {
    form.transform((data) => ({
        ...data,
        warranties: enabledTypes.value.map((t) => {
            const b = blocks[t.value];

            return {
                type: t.value,
                supplier_id: b.supplier_id,
                starts_on: b.starts_on,
                ends_on: b.ends_on,
                scope: b.scope,
                exclusions: b.exclusions || null,
            };
        }),
    })).post(garantias.store().url, {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    });
}

const TYPE_HINT: Record<string, string> = {
    product: 'Cubre el mueble: materiales, herrajes y fabricación.',
    installation:
        'Cubre el trabajo de instalación: anclajes, nivelación y ajustes.',
    additional_service:
        'Servicios contratados aparte (p. ej. mantenimiento o ajustes extra).',
};
</script>

<template>
    <Head title="Garantías" />

    <PageContainer>
        <PageHeader
            title="Garantías"
            description="Garantías registradas por proyecto y reclamos en atención."
        >
            <template #actions>
                <Button v-if="canManage" @click="openRegister"
                    ><Plus class="size-4" /> Registrar garantías</Button
                >
            </template>
        </PageHeader>

        <SectionCard
            v-if="openClaims.length"
            title="Reclamos en atención"
            :icon="LifeBuoy"
            flush
        >
            <ul class="divide-y">
                <li v-for="claim in openClaims" :key="claim.id">
                    <Link
                        :href="garantias.show(claim.warrantyId).url"
                        class="flex flex-col gap-1 px-4 py-3 hover:bg-muted/50 sm:flex-row sm:items-center sm:gap-4"
                    >
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate font-medium">{{
                                claim.project
                            }}</span>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                                >{{ claim.folio }} · Garantía de
                                {{ claim.type.label.toLowerCase() }}
                                {{ claim.warrantyFolio }}</span
                            >
                        </span>
                        <span class="flex items-center gap-3">
                            <StatusBadge
                                :tone="claim.status.tone"
                                :label="claim.status.label"
                                size="sm"
                            />
                            <span class="text-xs text-muted-foreground"
                                >Abierto
                                <DateText :value="claim.createdAt" relative
                            /></span>
                        </span>
                    </Link>
                </li>
            </ul>
        </SectionCard>

        <p
            v-if="expiringSoon > 0"
            class="flex items-center gap-2 rounded-xl border border-warning/25 bg-warning-soft px-4 py-3 text-sm text-warning-ink"
        >
            <CalendarClock class="size-4 shrink-0" aria-hidden="true" />
            {{
                expiringSoon === 1
                    ? 'Una garantía vence'
                    : `${expiringSoon} garantías vencen`
            }}
            en los próximos 30 días.
        </p>

        <FilterBar
            v-model:search="search"
            placeholder="Folio o proyecto"
            :active-count="
                [estado !== ALL, tipo !== ALL].filter(Boolean).length
            "
            @clear="((estado = ALL), (tipo = ALL), (search = ''))"
        >
            <template #filters>
                <div class="grid gap-1.5">
                    <Label class="text-xs text-muted-foreground">Estado</Label>
                    <Select v-model="estado">
                        <SelectTrigger class="w-full md:w-40"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Todos</SelectItem>
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
                    <Label class="text-xs text-muted-foreground">Tipo</Label>
                    <Select v-model="tipo">
                        <SelectTrigger class="w-full md:w-48"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Todos</SelectItem>
                            <SelectItem
                                v-for="t in options.types"
                                :key="t.value"
                                :value="t.value"
                                >{{ t.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="warranties"
            :loading="loading"
            :row-href="(row) => garantias.show(row.id).url"
            item-label="garantías"
        >
            <template #cell-project="{ row }">
                <Link :href="garantias.show(row.id).url" class="flex flex-col">
                    <span class="font-medium hover:underline">{{
                        row.project.name
                    }}</span>
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{{ row.folio }} · {{ row.project.folio }}</span
                    >
                </Link>
            </template>
            <template #cell-type="{ row }">
                <span class="flex items-center gap-2">
                    {{ row.type.label }}
                    <StatusBadge
                        v-if="row.openClaims"
                        tone="danger"
                        :label="`${row.openClaims} reclamo${row.openClaims > 1 ? 's' : ''}`"
                        size="sm"
                    />
                </span>
            </template>
            <template #cell-endsOn="{ row }">
                <span class="text-sm"
                    ><DateText :value="row.startsOn" /> –
                    <DateText :value="row.endsOn"
                /></span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="row.status.tone"
                    :label="row.status.label"
                    size="sm"
                />
            </template>
            <template #empty>
                <div
                    class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-12 text-center"
                >
                    <BadgeCheck
                        class="size-8 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p class="font-medium">Sin garantías registradas</p>
                    <p class="max-w-sm text-sm text-muted-foreground">
                        Cuando un proyecto se complete, registra aquí sus
                        garantías de producto, instalación o servicios
                        adicionales.
                    </p>
                    <Button
                        v-if="canManage"
                        variant="outline"
                        size="sm"
                        class="mt-2"
                        @click="openRegister"
                        >Registrar garantías</Button
                    >
                </div>
            </template>
        </DataView>
    </PageContainer>

    <FormSheet
        v-model:open="sheetOpen"
        title="Registrar garantías"
        description="Captura los términos acordados; el sistema no propone vigencias. Cada tipo se registra por separado."
        size="xl"
    >
        <form
            id="warranty-form"
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5">
                <Label>Proyecto completado</Label>
                <SearchSelect
                    v-model="form.project_id"
                    :options="projectOptions"
                    placeholder="Elige el proyecto"
                    empty-text="No hay proyectos completados."
                    :invalid="!!form.errors.project_id"
                />
                <InputError :message="form.errors.project_id" />
            </div>

            <Collapsible
                v-for="type in options.types"
                :key="type.value"
                :open="blocks[type.value].enabled"
                :class="
                    cn(
                        'rounded-xl border transition-colors',
                        blocks[type.value].enabled &&
                            'border-primary/40 bg-primary-soft/20',
                    )
                "
            >
                <div class="flex items-center gap-3 p-4">
                    <Switch
                        :id="`w-${type.value}`"
                        v-model="blocks[type.value].enabled"
                        :aria-label="`Registrar garantía de ${type.label.toLowerCase()}`"
                    />
                    <label
                        :for="`w-${type.value}`"
                        class="flex flex-1 cursor-pointer flex-col"
                    >
                        <span class="font-medium"
                            >Garantía de {{ type.label.toLowerCase() }}</span
                        >
                        <span class="text-xs text-muted-foreground">{{
                            TYPE_HINT[type.value]
                        }}</span>
                    </label>
                    <CollapsibleTrigger as-child>
                        <button
                            type="button"
                            class="grid size-9 place-items-center rounded-md hover:bg-muted"
                            :aria-label="`Mostrar campos de ${type.label}`"
                            @click="
                                blocks[type.value].enabled =
                                    !blocks[type.value].enabled
                            "
                        >
                            <ChevronDown
                                :class="
                                    cn(
                                        'size-4 transition-transform',
                                        blocks[type.value].enabled &&
                                            'rotate-180',
                                    )
                                "
                            />
                        </button>
                    </CollapsibleTrigger>
                </div>
                <CollapsibleContent>
                    <div class="grid gap-4 border-t px-4 pt-4 pb-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label>Inicio de vigencia</Label>
                                <DatePicker
                                    v-model="blocks[type.value].starts_on"
                                    :invalid="
                                        !!errorFor(type.value, 'starts_on')
                                    "
                                />
                                <InputError
                                    :message="errorFor(type.value, 'starts_on')"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Fin de vigencia</Label>
                                <DatePicker
                                    v-model="blocks[type.value].ends_on"
                                    :min="
                                        blocks[type.value].starts_on ??
                                        undefined
                                    "
                                    :invalid="!!errorFor(type.value, 'ends_on')"
                                />
                                <InputError
                                    :message="errorFor(type.value, 'ends_on')"
                                />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Proveedor responsable (opcional)</Label>
                            <SearchSelect
                                v-model="blocks[type.value].supplier_id"
                                :options="supplierOptions"
                                clearable
                                placeholder="ML Habitat responde directamente"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`scope-${type.value}`"
                                >Qué cubre</Label
                            >
                            <Textarea
                                :id="`scope-${type.value}`"
                                v-model="blocks[type.value].scope"
                                rows="3"
                                :aria-invalid="!!errorFor(type.value, 'scope')"
                            />
                            <InputError
                                :message="errorFor(type.value, 'scope')"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`excl-${type.value}`"
                                >Exclusiones (opcional)</Label
                            >
                            <Textarea
                                :id="`excl-${type.value}`"
                                v-model="blocks[type.value].exclusions"
                                rows="3"
                            />
                        </div>
                    </div>
                </CollapsibleContent>
            </Collapsible>

            <InputError :message="form.errors.warranties" />
            <p class="text-xs text-muted-foreground">
                Al registrar, avisamos al cliente y las garantías aparecen en su
                portal.
            </p>
        </form>

        <template #footer>
            <Button variant="outline" @click="sheetOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="warranty-form"
                :disabled="
                    form.processing ||
                    !form.project_id ||
                    enabledTypes.length === 0
                "
            >
                {{
                    form.processing
                        ? 'Registrando…'
                        : enabledTypes.length > 1
                          ? `Registrar ${enabledTypes.length} garantías`
                          : 'Registrar garantía'
                }}
            </Button>
        </template>
    </FormSheet>
</template>
