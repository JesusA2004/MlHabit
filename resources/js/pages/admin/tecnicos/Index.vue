<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { HardHat, MapPin, Ruler, UserPlus, Wrench } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataPagination from '@/components/ds/DataPagination.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import UserAvatar from '@/components/ds/UserAvatar.vue';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import tecnicos from '@/routes/admin/tecnicos';
import type { BreadcrumbItem } from '@/types';
import type { LengthAwarePaginator } from '@/types/ui';
import type { EnumOption, SelectOption } from '@/types/operations';

type Technician = {
    id: number;
    userId: number;
    name: string;
    email: string;
    phone: string | null;
    active: boolean;
    status: EnumOption;
    doesVisits: boolean;
    doesInstallations: boolean;
    supplier: { id: number; name: string } | null;
    zones: string[];
    activeInstallations: number;
};

const props = defineProps<{
    technicians: LengthAwarePaginator<Technician>;
    filters: {
        q?: string;
        estado?: string;
        proveedor?: number | string;
        zona?: number | string;
    };
    options: {
        statuses: EnumOption[];
        suppliers: SelectOption[];
        zones: SelectOption[];
        role: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Técnicos', href: '/admin/tecnicos' },
        ] as BreadcrumbItem[],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('suppliers.manage'));

const ALL = '__todos__';
const search = ref(props.filters.q ?? '');
const status = ref(props.filters.estado || ALL);
const supplier = ref<number | null>(
    props.filters.proveedor ? Number(props.filters.proveedor) : null,
);
const zone = ref<number | null>(
    props.filters.zona ? Number(props.filters.zona) : null,
);
const loading = ref(false);

function visit() {
    router.get(
        tecnicos.index().url,
        {
            q: search.value || undefined,
            estado: status.value === ALL ? undefined : status.value,
            proveedor: supplier.value ?? undefined,
            zona: zone.value ?? undefined,
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

const activeFilters = computed(
    () =>
        (status.value !== ALL ? 1 : 0) +
        (supplier.value ? 1 : 0) +
        (zone.value ? 1 : 0),
);

function clear() {
    search.value = '';
    status.value = ALL;
    supplier.value = null;
    zone.value = null;
    visit();
}

// ------------------------------------------------------------ Invitación
const inviteOpen = ref(false);
const form = useForm({
    name: '',
    email: '',
    phone: '',
    supplier_id: null as number | null,
    status: 'active',
    does_visits: true,
    does_installations: false,
    skills: '',
    notes: '',
    zone_ids: [] as number[],
});

function toggleZone(id: number) {
    form.zone_ids = form.zone_ids.includes(id)
        ? form.zone_ids.filter((z) => z !== id)
        : [...form.zone_ids, id];
}

function invite() {
    form.post(tecnicos.store().url, {
        onSuccess: () => (inviteOpen.value = false),
    });
}
</script>

<template>
    <Head title="Técnicos" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Técnicos e instaladores"
            description="Quién mide, quién instala, en qué zonas y con cuánta carga de trabajo."
        >
            <template #actions>
                <Button v-if="canManage" @click="inviteOpen = true"
                    ><UserPlus class="size-4" /> Invitar técnico</Button
                >
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="search"
            placeholder="Buscar por nombre o correo"
            :active-count="activeFilters"
            @clear="clear"
            @apply="visit"
        >
            <template #filters>
                <Select v-model="status" @update:model-value="visit">
                    <SelectTrigger class="w-full md:w-44" aria-label="Estado"
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
                <SearchSelect
                    v-model="supplier"
                    :options="options.suppliers"
                    placeholder="Proveedor"
                    clearable
                    class="w-full md:w-52"
                    @update:model-value="visit"
                />
                <SearchSelect
                    v-model="zone"
                    :options="options.zones"
                    placeholder="Zona"
                    clearable
                    class="w-full md:w-44"
                    @update:model-value="visit"
                />
            </template>
        </FilterBar>

        <div
            v-if="loading"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            aria-busy="true"
        >
            <Skeleton v-for="n in 6" :key="n" class="h-40 rounded-xl" />
        </div>

        <EmptyState
            v-else-if="!technicians.data.length"
            :icon="HardHat"
            :title="
                search || activeFilters
                    ? 'Ningún técnico coincide'
                    : 'Aún no hay técnicos'
            "
            :description="
                search || activeFilters
                    ? 'Prueba con otro nombre o quita los filtros.'
                    : 'Invita por correo a quienes miden e instalan. Recibirán un enlace para crear su contraseña y entrar a la app de campo.'
            "
        >
            <template #action>
                <Button
                    v-if="search || activeFilters"
                    variant="outline"
                    @click="clear"
                    >Quitar filtros</Button
                >
                <Button v-else-if="canManage" @click="inviteOpen = true"
                    ><UserPlus class="size-4" /> Invitar técnico</Button
                >
            </template>
        </EmptyState>

        <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <li v-for="t in technicians.data" :key="t.id">
                <Link
                    :href="tecnicos.show(t.id).url"
                    :class="
                        cn(
                            'flex h-full flex-col gap-3 rounded-xl border bg-card p-4 transition-shadow hover:shadow-md',
                            !t.active && 'opacity-70',
                        )
                    "
                >
                    <div class="flex items-start gap-3">
                        <UserAvatar :name="t.name" size="lg" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ t.name }}</p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{
                                    t.supplier?.name ?? 'Personal de ML Habitat'
                                }}
                            </p>
                        </div>
                        <StatusBadge
                            v-if="!t.active"
                            label="Sin acceso"
                            tone="neutral"
                            size="sm"
                        />
                        <StatusBadge
                            v-else
                            :label="t.status.label"
                            :tone="t.status.tone"
                            size="sm"
                        />
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-if="t.doesVisits"
                            class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs"
                            ><Ruler class="size-3" />Visitas</span
                        >
                        <span
                            v-if="t.doesInstallations"
                            class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs"
                            ><Wrench class="size-3" />Instalaciones</span
                        >
                    </div>
                    <p
                        class="flex items-start gap-1.5 text-xs text-muted-foreground"
                    >
                        <MapPin
                            class="mt-0.5 size-3 shrink-0"
                            aria-hidden="true"
                        />
                        {{
                            t.zones.length
                                ? t.zones.join(', ')
                                : 'Sin zonas asignadas'
                        }}
                    </p>
                    <p class="mt-auto border-t pt-3 text-sm">
                        <span class="font-semibold tabular-nums">{{
                            t.activeInstallations
                        }}</span>
                        <span class="text-muted-foreground">
                            {{
                                t.activeInstallations === 1
                                    ? 'instalación activa'
                                    : 'instalaciones activas'
                            }}</span
                        >
                    </p>
                </Link>
            </li>
        </ul>

        <DataPagination :paginator="technicians" item-label="técnicos" />
    </PageContainer>

    <FormSheet
        v-model:open="inviteOpen"
        title="Invitar técnico"
        description="Creamos su cuenta con rol de técnico y le enviamos por correo un enlace para definir su contraseña."
        size="lg"
    >
        <form id="tech-invite" class="grid gap-4" @submit.prevent="invite">
            <div class="grid gap-1.5">
                <Label for="t-name">Nombre completo</Label>
                <Input
                    id="t-name"
                    v-model="form.name"
                    required
                    autocomplete="off"
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="t-email">Correo</Label>
                    <Input
                        id="t-email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="t-phone">Celular</Label>
                    <Input
                        id="t-phone"
                        v-model="form.phone"
                        type="tel"
                        inputmode="tel"
                    />
                    <InputError :message="form.errors.phone" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="t-supplier"
                        >Proveedor
                        <span class="text-muted-foreground"
                            >(si es externo)</span
                        ></Label
                    >
                    <SearchSelect
                        id="t-supplier"
                        v-model="form.supplier_id"
                        :options="options.suppliers"
                        placeholder="Personal de ML Habitat"
                        clearable
                    />
                    <InputError :message="form.errors.supplier_id" />
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
            <div class="grid gap-2 sm:grid-cols-2">
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border p-3"
                >
                    <span class="text-sm font-medium"
                        >Hace visitas de medición</span
                    >
                    <Switch v-model="form.does_visits" />
                </label>
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border p-3"
                >
                    <span class="text-sm font-medium">Hace instalaciones</span>
                    <Switch v-model="form.does_installations" />
                </label>
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
                        v-for="z in options.zones"
                        :key="z.value"
                        type="button"
                        :aria-pressed="form.zone_ids.includes(z.value)"
                        :class="
                            cn(
                                'h-9 rounded-full border px-3 text-sm transition-colors pointer-coarse:h-11',
                                form.zone_ids.includes(z.value)
                                    ? 'border-primary bg-primary-soft text-primary'
                                    : 'hover:bg-muted',
                            )
                        "
                        @click="toggleZone(z.value)"
                    >
                        {{ z.label }}
                    </button>
                </div>
            </fieldset>
            <div class="grid gap-1.5">
                <Label for="t-skills">Habilidades</Label>
                <Textarea
                    id="t-skills"
                    v-model="form.skills"
                    rows="2"
                    placeholder="Cocinas, cubiertas de cuarzo, herrajes…"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="t-notes">Notas internas</Label>
                <Textarea id="t-notes" v-model="form.notes" rows="2" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="inviteOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="tech-invite"
                :disabled="form.processing"
                >{{
                    form.processing
                        ? 'Enviando…'
                        : 'Crear cuenta y enviar invitación'
                }}</Button
            >
        </template>
    </FormSheet>
</template>
