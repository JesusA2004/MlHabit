<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Star, Truck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
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
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import proveedores from '@/routes/admin/proveedores';
import type { BreadcrumbItem } from '@/types';
import type { LengthAwarePaginator } from '@/types/ui';
import type { EnumOption, SelectOption, SupplierRef } from '@/types/operations';

type Row = SupplierRef & {
    zones: string[];
    activeOrders: number;
    techniciansCount: number;
};

const props = defineProps<{
    suppliers: LengthAwarePaginator<Row>;
    filters: { q?: string; tipo?: string; estado?: string };
    options: {
        types: EnumOption[];
        statuses: EnumOption[];
        zones: SelectOption[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Proveedores', href: '/admin/proveedores' },
        ] as BreadcrumbItem[],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('suppliers.manage'));

const ALL = '__todos__';
const search = ref(props.filters.q ?? '');
const type = ref(props.filters.tipo || ALL);
const status = ref(props.filters.estado || ALL);
const loading = ref(false);

function visit() {
    router.get(
        proveedores.index().url,
        {
            q: search.value || undefined,
            tipo: type.value === ALL ? undefined : type.value,
            estado: status.value === ALL ? undefined : status.value,
        },
        {
            preserveState: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

watch(search, visit);

function clear() {
    search.value = '';
    type.value = ALL;
    status.value = ALL;
    visit();
}

const activeFilters = computed(
    () => (type.value !== ALL ? 1 : 0) + (status.value !== ALL ? 1 : 0),
);

const columns: DataColumn<Row>[] = [
    { key: 'name', label: 'Proveedor', primary: true },
    { key: 'type', label: 'Tipo' },
    { key: 'status', label: 'Estado' },
    { key: 'rating', label: 'Calificación', align: 'center' },
    { key: 'zones', label: 'Zonas', hideOnMobile: true },
    { key: 'activeOrders', label: 'Órdenes activas', align: 'right' },
];

// ------------------------------------------------------------ Alta
const createOpen = ref(false);
const form = useForm({
    name: '',
    legal_name: '',
    rfc: '',
    type: 'both',
    status: 'active',
    email: '',
    phone: '',
    capabilities: '',
    lead_time_days: '' as string | number,
    notes: '',
    zone_ids: [] as number[],
});

function toggleZone(id: number) {
    form.zone_ids = form.zone_ids.includes(id)
        ? form.zone_ids.filter((z) => z !== id)
        : [...form.zone_ids, id];
}

function submit() {
    form.transform((data) => ({
        ...data,
        lead_time_days:
            data.lead_time_days === '' ? null : Number(data.lead_time_days),
    })).post(proveedores.store().url, {
        onSuccess: () => (createOpen.value = false),
    });
}
</script>

<template>
    <Head title="Proveedores" />

    <PageContainer>
        <PageHeader
            title="Proveedores"
            description="Fabricantes e instaladores con los que trabajamos: contactos, zonas, documentos y desempeño."
        >
            <template #actions>
                <Button v-if="canManage" @click="createOpen = true"
                    ><Plus class="size-4" /> Nuevo proveedor</Button
                >
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="search"
            placeholder="Buscar por nombre, razón social o RFC"
            :active-count="activeFilters"
            @clear="clear"
            @apply="visit"
        >
            <template #filters>
                <Select v-model="type" @update:model-value="visit">
                    <SelectTrigger class="w-full md:w-44" aria-label="Tipo"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todos los tipos</SelectItem>
                        <SelectItem
                            v-for="t in options.types"
                            :key="t.value"
                            :value="t.value"
                            >{{ t.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="status" @update:model-value="visit">
                    <SelectTrigger class="w-full md:w-40" aria-label="Estado"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todos los estados</SelectItem>
                        <SelectItem
                            v-for="s in options.statuses"
                            :key="s.value"
                            :value="s.value"
                            >{{ s.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="suppliers"
            :loading="loading"
            :row-href="(row) => proveedores.show(row.id).url"
            item-label="proveedores"
        >
            <template #cell-type="{ row }">
                <StatusBadge
                    :label="row.type.label"
                    :tone="row.type.tone"
                    :dot="false"
                    size="sm"
                />
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :label="row.status.label"
                    :tone="row.status.tone"
                    size="sm"
                />
            </template>
            <template #cell-rating="{ row }">
                <span
                    v-if="row.rating"
                    class="inline-flex items-center gap-1 tabular-nums"
                >
                    <Star
                        class="size-3.5 fill-warning text-warning"
                        aria-hidden="true"
                    />{{ row.rating }}
                </span>
                <span v-else class="text-xs text-muted-foreground"
                    >Sin evaluar</span
                >
            </template>
            <template #cell-zones="{ row }">
                <span class="text-sm text-muted-foreground">{{
                    row.zones.length ? row.zones.join(', ') : 'Sin zonas'
                }}</span>
            </template>
            <template #cell-activeOrders="{ row }">
                <span class="tabular-nums">{{ row.activeOrders }}</span>
            </template>
            <template #empty>
                <EmptyState
                    :icon="Truck"
                    :title="
                        search || activeFilters
                            ? 'Ningún proveedor coincide'
                            : 'Aún no hay proveedores'
                    "
                    :description="
                        search || activeFilters
                            ? 'Prueba con otro nombre o quita los filtros.'
                            : 'Registra a los talleres que fabrican y a los equipos que instalan para poder asignarles órdenes.'
                    "
                >
                    <template #action>
                        <Button
                            v-if="search || activeFilters"
                            variant="outline"
                            @click="clear"
                            >Quitar filtros</Button
                        >
                        <Button v-else-if="canManage" @click="createOpen = true"
                            ><Plus class="size-4" /> Registrar proveedor</Button
                        >
                    </template>
                </EmptyState>
            </template>
        </DataView>
    </PageContainer>

    <FormSheet
        v-model:open="createOpen"
        title="Nuevo proveedor"
        description="Podrás agregar contactos y documentos después."
        size="lg"
    >
        <form id="supplier-create" class="grid gap-4" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="s-name">Nombre comercial</Label>
                <Input
                    id="s-name"
                    v-model="form.name"
                    required
                    autocomplete="off"
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="s-legal"
                        >Razón social
                        <span class="text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Input id="s-legal" v-model="form.legal_name" />
                    <InputError :message="form.errors.legal_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="s-rfc"
                        >RFC
                        <span class="text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Input
                        id="s-rfc"
                        v-model="form.rfc"
                        class="uppercase"
                        maxlength="13"
                    />
                    <InputError :message="form.errors.rfc" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label>Tipo</Label>
                    <Select v-model="form.type">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="t in options.types"
                                :key="t.value"
                                :value="t.value"
                                >{{ t.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.type" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Estado</Label>
                    <Select v-model="form.status">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="s in options.statuses"
                                :key="s.value"
                                :value="s.value"
                                >{{ s.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="s-email">Correo</Label>
                    <Input id="s-email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="s-phone">Teléfono</Label>
                    <Input
                        id="s-phone"
                        v-model="form.phone"
                        type="tel"
                        inputmode="tel"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label for="s-lead">Tiempo de entrega típico (días)</Label>
                <Input
                    id="s-lead"
                    v-model="form.lead_time_days"
                    type="number"
                    min="0"
                    max="365"
                    inputmode="numeric"
                    class="sm:w-40"
                />
                <InputError :message="form.errors.lead_time_days" />
            </div>
            <div class="grid gap-1.5">
                <Label for="s-cap">Capacidades</Label>
                <Textarea
                    id="s-cap"
                    v-model="form.capabilities"
                    rows="3"
                    placeholder="Cocinas, clósets, laca, herrajes de cierre lento…"
                />
                <InputError :message="form.errors.capabilities" />
            </div>
            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">
                    Zonas que atiende
                </legend>
                <p
                    v-if="!options.zones.length"
                    class="text-sm text-muted-foreground"
                >
                    No hay zonas de cobertura activas.
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="zone in options.zones"
                        :key="zone.value"
                        type="button"
                        :aria-pressed="form.zone_ids.includes(zone.value)"
                        :class="
                            cn(
                                'h-9 rounded-full border px-3 text-sm transition-[color,background-color,border-color,scale] duration-150 active:scale-[0.96] pointer-coarse:h-11',
                                form.zone_ids.includes(zone.value)
                                    ? 'border-primary bg-primary-soft text-primary'
                                    : 'hover:bg-muted',
                            )
                        "
                        @click="toggleZone(zone.value)"
                    >
                        {{ zone.label }}
                    </button>
                </div>
            </fieldset>
            <div class="grid gap-1.5">
                <Label for="s-notes">Notas internas</Label>
                <Textarea id="s-notes" v-model="form.notes" rows="2" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="createOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="supplier-create"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Guardando…' : 'Registrar proveedor' }}
            </Button>
        </template>
    </FormSheet>
</template>
