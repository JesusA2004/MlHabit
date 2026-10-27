<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    HardHat,
    MapPin,
    MapPinned,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DataView from '@/components/ds/DataView.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import FormStatus from '@/components/ds/FormStatus.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatNumber } from '@/lib/format';
import { apiFetch } from '@/lib/http';
import cobertura from '@/routes/admin/cobertura';

type Zone = {
    id: number;
    name: string;
    state: string;
    municipality: string | null;
    isActive: boolean;
    visitsEnabled: boolean;
    centerLat: string | null;
    centerLng: string | null;
    radiusKm: string | null;
    travelBufferMinutes: number;
    maxVisitsPerDay: number | null;
    notes: string | null;
    postalCodesCount: number;
    technicians: { id: number; name: string }[];
    acceptsVisits: boolean;
};

type ImportResult = {
    added: string[];
    existing: string[];
    moved: string[];
    invalid: string[];
    conflicts: { postal_code: string; zone_id: number; zone: string }[];
};

type CheckResult = {
    valid: boolean;
    postalCode?: string;
    registered?: boolean;
    covered?: boolean;
    acceptsVisits?: boolean;
    message?: string;
    zone?: {
        id: number;
        name: string;
        isActive: boolean;
        visitsEnabled: boolean;
        technicians: string[];
    } | null;
};

const props = defineProps<{
    zones: Zone[];
    selected: (Zone & { postalCodes: string[] }) | null;
    technicians: { value: number; label: string }[];
    stats: {
        zones: number;
        active: number;
        postalCodes: number;
        withoutTechnicians: number;
    };
    filters: { q: string };
    can: { manage: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Cobertura', href: '/admin/cobertura' },
        ],
    },
});

const search = ref(props.filters.q ?? '');

function reload(
    extra: Record<string, unknown> = {},
    only: string[] = ['zones', 'selected', 'stats', 'filters'],
) {
    router.get(
        cobertura.index().url,
        { q: search.value || undefined, ...extra },
        { preserveState: true, preserveScroll: true, replace: true, only },
    );
}

function onSearch(value: string) {
    search.value = value;
    reload();
}

const columns: DataColumn<Zone>[] = [
    { key: 'name', label: 'Zona', primary: true },
    { key: 'postalCodesCount', label: 'Códigos postales', align: 'right' },
    { key: 'technicians', label: 'Técnicos' },
    { key: 'status', label: 'Estado' },
];

// ── Verificador ──────────────────────────────────────────────────────
const checkCp = ref('');
const checking = ref(false);
const check = ref<CheckResult | null>(null);

async function runCheck() {
    const cp = checkCp.value.replace(/\D/g, '');

    if (cp.length !== 5) {
        check.value = {
            valid: false,
            message: 'Escribe un código postal de 5 dígitos.',
        };

        return;
    }

    checking.value = true;

    try {
        const response = await apiFetch(
            `${cobertura.verificar().url}?cp=${cp}`,
        );
        check.value = (await response.json()) as CheckResult;
    } catch {
        check.value = {
            valid: false,
            message: 'No pudimos verificar el código. Inténtalo de nuevo.',
        };
    } finally {
        checking.value = false;
    }
}

const checkTone = computed(() => {
    if (!check.value?.valid) {
        return 'danger' as const;
    }

    return check.value.acceptsVisits ? ('success' as const) : ('info' as const);
});

const checkMessage = computed(() => {
    const result = check.value;

    if (!result) {
        return null;
    }

    if (!result.valid) {
        return result.message ?? null;
    }

    if (!result.registered) {
        return `El C.P. ${result.postalCode} no pertenece a ninguna zona. No se pueden vender visitas ahí.`;
    }

    const zone = result.zone;

    if (!zone) {
        return null;
    }

    if (!zone.isActive) {
        return `El C.P. ${result.postalCode} está en «${zone.name}», pero la zona está inactiva.`;
    }

    if (result.acceptsVisits) {
        return `Cubierto por «${zone.name}». Se pueden vender visitas (técnicos: ${zone.technicians.join(', ')}).`;
    }

    return zone.visitsEnabled
        ? `Cubierto por «${zone.name}», pero la zona no tiene técnicos activos: aún no se pueden vender visitas.`
        : `Cubierto por «${zone.name}», con visitas deshabilitadas.`;
});

// ── Alta / edición de zona ───────────────────────────────────────────
const zoneOpen = ref(false);
const editing = ref<Zone | null>(null);
const zoneForm = useForm({
    name: '',
    state: '',
    municipality: '',
    is_active: true,
    visits_enabled: true,
    center_lat: '',
    center_lng: '',
    radius_km: '',
    travel_buffer_minutes: 30 as number | string,
    max_visits_per_day: '' as number | string,
    notes: '',
});
const useRadius = ref(false);

function openZone(zone: Zone | null) {
    editing.value = zone;
    zoneForm.clearErrors();
    zoneForm.defaults({
        name: zone?.name ?? '',
        state: zone?.state ?? '',
        municipality: zone?.municipality ?? '',
        is_active: zone?.isActive ?? true,
        visits_enabled: zone?.visitsEnabled ?? true,
        center_lat: zone?.centerLat ?? '',
        center_lng: zone?.centerLng ?? '',
        radius_km: zone?.radiusKm ?? '',
        travel_buffer_minutes: zone?.travelBufferMinutes ?? 30,
        max_visits_per_day: zone?.maxVisitsPerDay ?? '',
        notes: zone?.notes ?? '',
    });
    zoneForm.reset();
    useRadius.value = Boolean(zone?.radiusKm);
    zoneOpen.value = true;
}

function submitZone() {
    const transform = (
        data: typeof zoneForm.data extends () => infer R ? R : never,
    ) => ({
        ...data,
        municipality: data.municipality || null,
        center_lat:
            useRadius.value && data.center_lat !== '' ? data.center_lat : null,
        center_lng:
            useRadius.value && data.center_lng !== '' ? data.center_lng : null,
        radius_km:
            useRadius.value && data.radius_km !== '' ? data.radius_km : null,
        max_visits_per_day:
            data.max_visits_per_day === '' ? null : data.max_visits_per_day,
        notes: data.notes || null,
    });

    const options = {
        preserveScroll: true,
        onSuccess: () => (zoneOpen.value = false),
    };

    if (editing.value) {
        zoneForm
            .transform(transform)
            .put(cobertura.update(editing.value.id).url, options);
    } else {
        zoneForm.transform(transform).post(cobertura.store().url, options);
    }
}

// ── Códigos postales ─────────────────────────────────────────────────
const codesOpen = ref(false);
const codesZoneId = ref<number | null>(null);
const codesForm = useForm({ postal_codes: '', move: false });
const importResult = ref<ImportResult | null>(null);
const codesFilter = ref('');

const codesZone = computed(() =>
    props.selected && props.selected.id === codesZoneId.value
        ? props.selected
        : null,
);
const filteredCodes = computed(() => {
    const codes = codesZone.value?.postalCodes ?? [];
    const term = codesFilter.value.trim();

    return term ? codes.filter((c) => c.startsWith(term)) : codes;
});

function openCodes(zone: Zone) {
    codesZoneId.value = zone.id;
    codesForm.reset();
    codesForm.clearErrors();
    importResult.value = null;
    codesFilter.value = '';
    codesOpen.value = true;
    reload({ zona: zone.id }, ['selected']);
}

function submitCodes() {
    if (!codesZoneId.value) {
        return;
    }

    codesForm.post(cobertura.codigos.store(codesZoneId.value).url, {
        preserveScroll: true,
        preserveState: true,
        only: ['zones', 'selected', 'stats', 'flash'],
        onFlash: (flash) => {
            importResult.value =
                ((flash as Record<string, unknown>).coverageImport as
                    | ImportResult
                    | undefined) ?? null;
        },
        onSuccess: () => {
            codesForm.reset('postal_codes', 'move');
        },
    });
}

function retryMovingConflicts() {
    if (!importResult.value) {
        return;
    }

    codesForm.postal_codes = importResult.value.conflicts
        .map((c) => c.postal_code)
        .join(', ');
    codesForm.move = true;
    submitCodes();
}

const removingCode = ref<string | null>(null);

function removeCode(code: string) {
    if (!codesZoneId.value) {
        return;
    }

    removingCode.value = code;
    router.delete(
        cobertura.codigos.destroy({ zone: codesZoneId.value, postalCode: code })
            .url,
        {
            preserveScroll: true,
            preserveState: true,
            only: ['zones', 'selected', 'stats', 'flash'],
            onFinish: () => (removingCode.value = null),
        },
    );
}

// ── Técnicos ─────────────────────────────────────────────────────────
const techOpen = ref(false);
const techZone = ref<Zone | null>(null);
const techForm = useForm({ technician_ids: [] as number[] });

function openTechnicians(zone: Zone) {
    techZone.value = zone;
    techForm.clearErrors();
    techForm.technician_ids = zone.technicians.map((t) => t.id);
    techOpen.value = true;
}

function toggleTech(id: number, checked: boolean | 'indeterminate') {
    const set = new Set(techForm.technician_ids);

    if (checked === true) {
        set.add(id);
    } else {
        set.delete(id);
    }

    techForm.technician_ids = [...set];
}

function submitTechnicians() {
    if (!techZone.value) {
        return;
    }

    techForm.put(cobertura.tecnicos(techZone.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (techOpen.value = false),
    });
}

// ── Eliminar ─────────────────────────────────────────────────────────
const deleteOpen = ref(false);
const deleting = ref<Zone | null>(null);

function askDelete(zone: Zone) {
    deleting.value = zone;
    deleteOpen.value = true;
}

function confirmDelete(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!deleting.value) {
            resolve();

            return;
        }

        router.delete(cobertura.destroy(deleting.value.id).url, {
            preserveScroll: true,
            onSuccess: (page) => {
                const errors = page.props.errors as
                    | Record<string, string>
                    | undefined;

                if (errors?.zone) {
                    reject(new Error(errors.zone));
                } else {
                    resolve();
                }
            },
            onError: (errors) =>
                reject(
                    new Error(errors.zone ?? 'No se pudo eliminar la zona.'),
                ),
        });
    });
}
</script>

<template>
    <Head title="Cobertura" />

    <PageContainer>
        <PageHeader
            title="Cobertura"
            description="Zonas donde sí podemos vender y atender visitas técnicas. Un código postal pertenece a una sola zona y la zona necesita técnicos activos para aceptar visitas."
        >
            <template #actions>
                <Button v-if="can.manage" @click="openZone(null)">
                    <Plus class="size-4" />
                    Nueva zona
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <dl
                class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-2 xl:grid-cols-4"
            >
                <div class="rounded-lg border bg-card p-4">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Zonas activas
                    </dt>
                    <dd
                        class="mt-1 font-display text-2xl font-semibold tabular-nums"
                    >
                        {{ stats.active
                        }}<span
                            class="text-base font-normal text-muted-foreground"
                        >
                            / {{ stats.zones }}</span
                        >
                    </dd>
                </div>
                <div class="rounded-lg border bg-card p-4">
                    <dt class="text-xs font-medium text-muted-foreground">
                        Códigos postales
                    </dt>
                    <dd
                        class="mt-1 font-display text-2xl font-semibold tabular-nums"
                    >
                        {{ formatNumber(stats.postalCodes) }}
                    </dd>
                </div>
                <div
                    class="col-span-2 rounded-lg border bg-card p-4"
                    :class="
                        stats.withoutTechnicians > 0 &&
                        'border-warning/40 bg-warning-soft/40'
                    "
                >
                    <dt class="text-xs font-medium text-muted-foreground">
                        Zonas sin técnicos
                    </dt>
                    <dd class="mt-1 flex items-baseline gap-2">
                        <span
                            class="font-display text-2xl font-semibold tabular-nums"
                            >{{ stats.withoutTechnicians }}</span
                        >
                        <span class="text-sm text-muted-foreground">
                            {{
                                stats.withoutTechnicians > 0
                                    ? 'no pueden recibir visitas aunque estén activas'
                                    : 'todas las zonas activas tienen técnico'
                            }}
                        </span>
                    </dd>
                </div>
            </dl>

            <SectionCard title="Verificar un código postal" :icon="MapPinned">
                <form class="flex gap-2" @submit.prevent="runCheck">
                    <Label for="cp-check" class="sr-only">Código postal</Label>
                    <Input
                        id="cp-check"
                        v-model="checkCp"
                        inputmode="numeric"
                        maxlength="5"
                        placeholder="Ej. 06700"
                        class="tabular-nums"
                        autocomplete="off"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="checking"
                    >
                        <Spinner v-if="checking" />
                        <Search v-else class="size-4" />
                        Verificar
                    </Button>
                </form>
                <FormStatus
                    v-if="checkMessage"
                    class="mt-3"
                    :tone="checkTone"
                    :message="checkMessage"
                    aria-live="polite"
                />
            </SectionCard>
        </div>

        <FilterBar
            :search="search"
            placeholder="Buscar zona, municipio o código postal…"
            @update:search="onSearch"
        />

        <DataView :columns="columns" :rows="zones" item-label="zonas">
            <template #cell-name="{ row }">
                <div class="flex min-w-0 flex-col">
                    <span class="truncate font-medium text-foreground">{{
                        row.name
                    }}</span>
                    <span class="truncate text-sm text-muted-foreground">
                        {{
                            [row.municipality, row.state]
                                .filter(Boolean)
                                .join(', ')
                        }}
                        <template v-if="row.radiusKm">
                            · radio {{ row.radiusKm }} km</template
                        >
                    </span>
                </div>
            </template>
            <template #cell-postalCodesCount="{ row }">
                <button
                    type="button"
                    class="rounded-sm font-medium text-primary tabular-nums underline-offset-4 hover:underline focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none"
                    @click="openCodes(row)"
                >
                    {{ formatNumber(row.postalCodesCount) }}
                </button>
            </template>
            <template #cell-technicians="{ row }">
                <span v-if="row.technicians.length" class="text-sm">{{
                    row.technicians.map((t) => t.name).join(', ')
                }}</span>
                <span
                    v-else
                    class="inline-flex items-center gap-1.5 text-sm text-warning-ink"
                >
                    <CircleAlert class="size-4" aria-hidden="true" />
                    Sin técnicos
                </span>
            </template>
            <template #cell-status="{ row }">
                <div class="flex flex-wrap gap-1.5">
                    <StatusBadge
                        v-if="!row.isActive"
                        tone="neutral"
                        label="Inactiva"
                    />
                    <StatusBadge
                        v-else-if="row.acceptsVisits"
                        tone="success"
                        label="Vende visitas"
                    />
                    <StatusBadge
                        v-else-if="!row.visitsEnabled"
                        tone="neutral"
                        label="Visitas pausadas"
                    />
                    <StatusBadge v-else tone="warning" label="Falta técnico" />
                </div>
            </template>

            <template #actions="{ row }">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Acciones para ${row.name}`"
                        >
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuItem @select="openCodes(row)"
                            ><MapPin class="size-4" /> Códigos
                            postales</DropdownMenuItem
                        >
                        <template v-if="can.manage">
                            <DropdownMenuItem @select="openTechnicians(row)"
                                ><HardHat class="size-4" />
                                Técnicos</DropdownMenuItem
                            >
                            <DropdownMenuItem @select="openZone(row)"
                                ><Pencil class="size-4" /> Editar
                                zona</DropdownMenuItem
                            >
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="text-danger-ink"
                                @select="askDelete(row)"
                                ><Trash2 class="size-4" />
                                Eliminar</DropdownMenuItem
                            >
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>

            <template #empty>
                <EmptyState
                    :icon="MapPinned"
                    :title="
                        filters.q
                            ? 'Ninguna zona coincide con la búsqueda'
                            : 'Aún no hay zonas de cobertura'
                    "
                    :description="
                        filters.q
                            ? 'Prueba con otro nombre, municipio o código postal.'
                            : 'Crea la primera zona, pega sus códigos postales y asigna técnicos para empezar a vender visitas.'
                    "
                >
                    <template v-if="can.manage && !filters.q" #action>
                        <Button @click="openZone(null)"
                            ><Plus class="size-4" /> Nueva zona</Button
                        >
                    </template>
                </EmptyState>
            </template>
        </DataView>
    </PageContainer>

    <!-- Zona -->
    <FormSheet
        v-model:open="zoneOpen"
        :title="editing ? `Editar ${editing.name}` : 'Nueva zona'"
        description="Los cambios aplican de inmediato al formulario público y a la venta de visitas."
        size="lg"
    >
        <form id="zone-form" class="grid gap-5" @submit.prevent="submitZone">
            <div class="grid gap-2">
                <Label for="zone-name">Nombre</Label>
                <Input
                    id="zone-name"
                    v-model="zoneForm.name"
                    placeholder="Ej. CDMX Poniente"
                    required
                    :aria-invalid="zoneForm.errors.name ? true : undefined"
                />
                <InputError :message="zoneForm.errors.name" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="zone-state">Estado</Label>
                    <Input
                        id="zone-state"
                        v-model="zoneForm.state"
                        required
                        :aria-invalid="zoneForm.errors.state ? true : undefined"
                    />
                    <InputError :message="zoneForm.errors.state" />
                </div>
                <div class="grid gap-2">
                    <Label for="zone-municipality"
                        >Municipio o alcaldía
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Input
                        id="zone-municipality"
                        v-model="zoneForm.municipality"
                    />
                    <InputError :message="zoneForm.errors.municipality" />
                </div>
            </div>

            <div class="grid gap-3 rounded-lg border p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <Label for="zone-active">Zona activa</Label>
                        <p class="text-sm text-muted-foreground">
                            Si la desactivas, sus códigos dejan de contar como
                            cubiertos.
                        </p>
                    </div>
                    <Switch id="zone-active" v-model="zoneForm.is_active" />
                </div>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <Label for="zone-visits">Vender visitas técnicas</Label>
                        <p class="text-sm text-muted-foreground">
                            Pausa la venta sin perder la cobertura (p. ej.
                            temporada alta).
                        </p>
                    </div>
                    <Switch
                        id="zone-visits"
                        v-model="zoneForm.visits_enabled"
                    />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="zone-buffer">Tiempo de traslado (min)</Label>
                    <Input
                        id="zone-buffer"
                        v-model="zoneForm.travel_buffer_minutes"
                        type="number"
                        min="0"
                        max="240"
                        inputmode="numeric"
                    />
                    <p class="text-xs text-muted-foreground">
                        Margen entre visitas consecutivas.
                    </p>
                    <InputError
                        :message="zoneForm.errors.travel_buffer_minutes"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="zone-max"
                        >Visitas por día
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Input
                        id="zone-max"
                        v-model="zoneForm.max_visits_per_day"
                        type="number"
                        min="1"
                        max="50"
                        inputmode="numeric"
                    />
                    <InputError :message="zoneForm.errors.max_visits_per_day" />
                </div>
            </div>

            <div class="grid gap-3">
                <div class="flex items-center gap-3">
                    <Switch id="zone-radius" v-model="useRadius" />
                    <Label for="zone-radius"
                        >Definir radio desde un punto base</Label
                    >
                </div>
                <div v-if="useRadius" class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="zone-lat">Latitud</Label>
                        <Input
                            id="zone-lat"
                            v-model="zoneForm.center_lat"
                            inputmode="decimal"
                            placeholder="19.4326"
                        />
                        <InputError :message="zoneForm.errors.center_lat" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="zone-lng">Longitud</Label>
                        <Input
                            id="zone-lng"
                            v-model="zoneForm.center_lng"
                            inputmode="decimal"
                            placeholder="-99.1332"
                        />
                        <InputError :message="zoneForm.errors.center_lng" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="zone-km">Radio (km)</Label>
                        <Input
                            id="zone-km"
                            v-model="zoneForm.radius_km"
                            inputmode="decimal"
                            placeholder="15"
                        />
                        <InputError :message="zoneForm.errors.radius_km" />
                    </div>
                    <p class="text-xs text-muted-foreground sm:col-span-3">
                        Referencia operativa. La cobertura que vende visitas se
                        decide por código postal.
                    </p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="zone-notes"
                    >Notas internas
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    ></Label
                >
                <Textarea id="zone-notes" v-model="zoneForm.notes" rows="3" />
                <InputError :message="zoneForm.errors.notes" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="zoneOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="zone-form"
                :disabled="zoneForm.processing"
            >
                <Spinner v-if="zoneForm.processing" />
                {{
                    zoneForm.processing
                        ? 'Guardando…'
                        : editing
                          ? 'Guardar cambios'
                          : 'Crear zona'
                }}
            </Button>
        </template>
    </FormSheet>

    <!-- Códigos postales -->
    <FormSheet
        v-model:open="codesOpen"
        :title="
            codesZone
                ? `Códigos postales · ${codesZone.name}`
                : 'Códigos postales'
        "
        description="Pega una lista desde Excel o separada por comas. Validamos que tengan 5 dígitos y que no estén en otra zona."
        size="lg"
    >
        <div class="grid gap-6">
            <form
                v-if="can.manage"
                id="codes-form"
                class="grid gap-3"
                @submit.prevent="submitCodes"
            >
                <Label for="codes-input">Agregar códigos</Label>
                <Textarea
                    id="codes-input"
                    v-model="codesForm.postal_codes"
                    rows="4"
                    class="font-mono tabular-nums"
                    placeholder="06600, 06700, 06720…"
                    :aria-invalid="
                        codesForm.errors.postal_codes ? true : undefined
                    "
                />
                <InputError :message="codesForm.errors.postal_codes" />
                <div class="flex items-center gap-3">
                    <Switch id="codes-move" v-model="codesForm.move" />
                    <Label for="codes-move" class="font-normal"
                        >Mover a esta zona los que ya están en otra</Label
                    >
                </div>
                <div>
                    <Button
                        type="submit"
                        :disabled="
                            codesForm.processing ||
                            !codesForm.postal_codes.trim()
                        "
                    >
                        <Spinner v-if="codesForm.processing" />
                        {{
                            codesForm.processing
                                ? 'Agregando…'
                                : 'Agregar códigos'
                        }}
                    </Button>
                </div>
            </form>

            <div
                v-if="importResult"
                class="grid gap-3 rounded-lg border bg-muted/40 p-4 text-sm"
                aria-live="polite"
            >
                <p class="flex items-center gap-2 font-medium">
                    <CircleCheck
                        class="size-4 text-success"
                        aria-hidden="true"
                    />
                    {{ importResult.added.length }} agregados<template
                        v-if="importResult.moved.length"
                        >, {{ importResult.moved.length }} movidos</template
                    ><template v-if="importResult.existing.length"
                        >, {{ importResult.existing.length }} ya
                        estaban</template
                    >.
                </p>
                <div v-if="importResult.conflicts.length" class="grid gap-2">
                    <p
                        class="flex items-center gap-2 font-medium text-warning-ink"
                    >
                        <CircleAlert class="size-4" aria-hidden="true" />
                        {{ importResult.conflicts.length }} pertenecen a otra
                        zona y no se movieron:
                    </p>
                    <ul class="grid gap-1 pl-6">
                        <li
                            v-for="c in importResult.conflicts"
                            :key="c.postal_code"
                            class="tabular-nums"
                        >
                            {{ c.postal_code }}
                            <span class="text-muted-foreground"
                                >→ {{ c.zone }}</span
                            >
                        </li>
                    </ul>
                    <div v-if="can.manage">
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="retryMovingConflicts"
                            >Moverlos a esta zona</Button
                        >
                    </div>
                </div>
                <p v-if="importResult.invalid.length" class="text-danger-ink">
                    Ignoramos {{ importResult.invalid.length }} entradas que no
                    son códigos de 5 dígitos:
                    <span class="font-mono"
                        >{{ importResult.invalid.slice(0, 12).join(', ')
                        }}<template v-if="importResult.invalid.length > 12"
                            >…</template
                        ></span
                    >
                </p>
            </div>

            <div class="grid gap-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-medium">
                        En esta zona
                        <span class="text-muted-foreground tabular-nums"
                            >({{ codesZone?.postalCodes.length ?? '…' }})</span
                        >
                    </h3>
                    <Input
                        v-model="codesFilter"
                        class="h-8 w-36"
                        inputmode="numeric"
                        placeholder="Filtrar"
                        aria-label="Filtrar códigos"
                    />
                </div>
                <div
                    v-if="!codesZone"
                    class="grid grid-cols-4 gap-2"
                    aria-busy="true"
                >
                    <div
                        v-for="n in 8"
                        :key="n"
                        class="h-8 animate-pulse rounded-md bg-muted"
                    />
                </div>
                <p
                    v-else-if="codesZone.postalCodes.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Esta zona aún no tiene códigos postales. Mientras no tenga,
                    nadie verá cobertura en ella.
                </p>
                <ul
                    v-else
                    class="flex max-h-80 flex-wrap gap-2 overflow-y-auto"
                    aria-label="Códigos postales de la zona"
                >
                    <li
                        v-for="code in filteredCodes"
                        :key="code"
                        class="inline-flex items-center gap-1 rounded-md border bg-card py-1 pr-1 pl-2.5 font-mono text-sm tabular-nums"
                    >
                        {{ code }}
                        <button
                            v-if="can.manage"
                            type="button"
                            class="rounded-sm p-1 text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none disabled:opacity-50"
                            :aria-label="`Quitar ${code}`"
                            :disabled="removingCode === code"
                            @click="removeCode(code)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        <template #footer>
            <Button variant="outline" type="button" @click="codesOpen = false"
                >Cerrar</Button
            >
        </template>
    </FormSheet>

    <!-- Técnicos -->
    <FormSheet
        v-model:open="techOpen"
        :title="techZone ? `Técnicos · ${techZone.name}` : 'Técnicos'"
        description="Solo se venden visitas en zonas con al menos un técnico activo. Su disponibilidad se configura en Agenda."
    >
        <form
            id="tech-form"
            class="grid gap-2"
            @submit.prevent="submitTechnicians"
        >
            <p
                v-if="technicians.length === 0"
                class="text-sm text-muted-foreground"
            >
                No hay usuarios con rol de técnico. Invítalos desde Usuarios.
            </p>
            <label
                v-for="tech in technicians"
                :key="tech.value"
                class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 py-2 hover:bg-muted/50"
            >
                <Checkbox
                    :model-value="techForm.technician_ids.includes(tech.value)"
                    @update:model-value="(v) => toggleTech(tech.value, v)"
                />
                <span class="text-sm">{{ tech.label }}</span>
            </label>
            <InputError :message="techForm.errors.technician_ids" />
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="techOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="tech-form"
                :disabled="techForm.processing"
            >
                <Spinner v-if="techForm.processing" />
                {{ techForm.processing ? 'Guardando…' : 'Guardar técnicos' }}
            </Button>
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="deleteOpen"
        :title="
            deleting ? `¿Eliminar la zona «${deleting.name}»?` : 'Eliminar zona'
        "
        description="Se quitan también sus códigos postales y técnicos asignados. Si ya tiene prospectos o visitas, mejor desactívala."
        confirm-label="Eliminar zona"
        loading-label="Eliminando…"
        tone="destructive"
        :confirm="confirmDelete"
    />
</template>
