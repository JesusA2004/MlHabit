<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, CopyPlus, Lock, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import tarifarios from '@/routes/admin/tarifarios';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, RateCardRow, SelectOption } from '@/types/operations';

const props = defineProps<{
    rateCard: RateCardRow & { createdBy: string | null; ordersCount: number };
    versions: {
        id: number;
        version: number;
        status: EnumOption;
        activatedAt: string | null;
    }[];
    hasDraft: boolean;
    options: { suppliers: SelectOption[]; statuses: EnumOption[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Tarifarios', href: '/admin/tarifarios' },
            { title: 'Versión', href: '#' },
        ] as BreadcrumbItem[],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('rate_cards.manage'));
const isDraft = computed(() => props.rateCard.status.value === 'draft');
const editable = computed(() => isDraft.value && canManage.value);
const id = computed(() => props.rateCard.id);

// ------------------------------------------------------------ Borrador editable
type ItemRow = {
    code: string;
    description: string;
    unit: string;
    unit_price: string;
};

const form = useForm({
    supplier_id: props.rateCard.supplier?.id ?? null,
    effective_from: props.rateCard.effectiveFrom,
    mobilization_fee: props.rateCard.mobilizationFee,
    minimum_charge: props.rateCard.minimumCharge,
    notes: props.rateCard.notes ?? '',
    items: (props.rateCard.items ?? []).map((i) => ({
        code: i.code,
        description: i.description,
        unit: i.unit,
        unit_price: i.unitPrice,
    })) as ItemRow[],
});

function addRow() {
    form.items = [
        ...form.items,
        { code: '', description: '', unit: 'pieza', unit_price: '' },
    ];
}

function removeRow(index: number) {
    form.items = form.items.filter((_, i) => i !== index);
}

function itemError(index: number, field: keyof ItemRow): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `items.${index}.${field}`
    ];
}

function save() {
    form.put(tarifarios.update(id.value).url, { preserveScroll: true });
}

// ------------------------------------------------------------ Acciones
const activateOpen = ref(false);
function activate() {
    return new Promise<void>((resolve, reject) =>
        router.post(
            tarifarios.activar(id.value).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (errors) =>
                    reject(
                        new Error(
                            Object.values(errors)[0] ?? 'No se pudo activar.',
                        ),
                    ),
            },
        ),
    );
}

const deleteOpen = ref(false);
function destroy() {
    return new Promise<void>((resolve, reject) =>
        router.delete(tarifarios.destroy(id.value).url, {
            onSuccess: () => resolve(),
            onError: () =>
                reject(new Error('No se pudo eliminar el borrador.')),
        }),
    );
}

const cloning = ref(false);
function newVersion() {
    router.post(
        tarifarios.nuevaVersion(id.value).url,
        {},
        {
            onStart: () => (cloning.value = true),
            onFinish: () => (cloning.value = false),
        },
    );
}

const lockedNotice = computed(() =>
    props.rateCard.status.value === 'active'
        ? 'Versión vigente: no editable. Sus precios ya se congelaron en las órdenes que la usan. Para cambiar precios crea una nueva versión.'
        : 'Versión retirada: no editable. Se conserva como historial de las órdenes que la usaron.',
);
</script>

<template>
    <Head :title="`${rateCard.name} · v${rateCard.version}`" />

    <PageContainer>
        <PageHeader :title="rateCard.name">
            <template #meta>
                <StatusBadge
                    :label="`Versión ${rateCard.version} · ${rateCard.status.label}`"
                    :tone="rateCard.status.tone"
                />
                <span>{{
                    rateCard.supplier?.name ?? 'Cualquier proveedor'
                }}</span>
                <span v-if="rateCard.ordersCount"
                    >· Usado en {{ rateCard.ordersCount }}
                    {{ rateCard.ordersCount === 1 ? 'orden' : 'órdenes' }}</span
                >
            </template>
            <template v-if="canManage" #actions>
                <template v-if="isDraft">
                    <Button
                        variant="ghost"
                        class="text-destructive"
                        @click="deleteOpen = true"
                        ><Trash2 class="size-4" /> Descartar borrador</Button
                    >
                    <Button
                        :disabled="form.isDirty || !rateCard.items?.length"
                        @click="activateOpen = true"
                    >
                        <CheckCircle2 class="size-4" /> Activar versión
                    </Button>
                </template>
                <Button
                    v-else
                    :disabled="hasDraft || cloning"
                    :title="
                        hasDraft
                            ? 'Ya hay un borrador de este tarifario'
                            : undefined
                    "
                    @click="newVersion"
                >
                    <CopyPlus class="size-4" />
                    {{ cloning ? 'Creando…' : 'Nueva versión' }}
                </Button>
            </template>
        </PageHeader>

        <nav
            v-if="versions.length > 1"
            aria-label="Versiones"
            class="flex flex-wrap gap-2"
        >
            <Link
                v-for="v in versions"
                :key="v.id"
                :href="tarifarios.show(v.id).url"
                :aria-current="v.id === rateCard.id ? 'page' : undefined"
                :class="
                    cn(
                        'inline-flex h-9 items-center gap-2 rounded-full border px-3 text-sm transition-[color,background-color,border-color,scale] duration-150 active:scale-[0.96]',
                        v.id === rateCard.id
                            ? 'border-primary bg-primary-soft text-primary'
                            : 'hover:bg-muted',
                    )
                "
            >
                v{{ v.version }}
                <span class="text-xs text-muted-foreground">{{
                    v.status.label
                }}</span>
            </Link>
        </nav>

        <div
            v-if="!isDraft"
            class="flex items-start gap-3 rounded-xl border border-info/30 bg-info-soft p-4 text-sm text-info-ink"
            role="note"
        >
            <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <div>
                <p>{{ lockedNotice }}</p>
                <p v-if="hasDraft && canManage" class="mt-1">
                    Ya existe un borrador de este tarifario; continúalo desde el
                    selector de versiones.
                </p>
            </div>
        </div>
        <div
            v-else-if="form.isDirty"
            class="rounded-xl border border-warning/30 bg-warning-soft p-3 text-sm text-warning-ink"
            role="status"
        >
            Tienes cambios sin guardar. Guarda el borrador antes de activarlo.
        </div>

        <!-- Lectura -->
        <template v-if="!editable">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Movilización</p>
                    <Money
                        :value="rateCard.mobilizationFee"
                        class="font-display text-xl font-semibold"
                    />
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">
                        Cargo mínimo por orden
                    </p>
                    <Money
                        :value="rateCard.minimumCharge"
                        class="font-display text-xl font-semibold"
                    />
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Vigencia</p>
                    <p class="font-display text-xl font-semibold">
                        <DateText
                            :value="
                                rateCard.effectiveFrom ?? rateCard.activatedAt
                            "
                            fallback="Sin activar"
                        />
                    </p>
                </div>
            </div>

            <SectionCard title="Conceptos" flush>
                <EmptyState
                    v-if="!rateCard.items?.length"
                    compact
                    :bordered="false"
                    title="Sin conceptos"
                    description="Esta versión no tiene conceptos capturados."
                />
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="px-4">Clave</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead>Unidad</TableHead>
                            <TableHead class="px-4 text-right"
                                >Precio unitario</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in rateCard.items" :key="item.id">
                            <TableCell class="px-4 font-mono text-xs">{{
                                item.code
                            }}</TableCell>
                            <TableCell class="whitespace-normal">{{
                                item.description
                            }}</TableCell>
                            <TableCell>{{ item.unit }}</TableCell>
                            <TableCell class="px-4 text-right"
                                ><Money :value="item.unitPrice"
                            /></TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </SectionCard>

            <SectionCard v-if="rateCard.notes" title="Notas"
                ><p class="text-sm whitespace-pre-line">
                    {{ rateCard.notes }}
                </p></SectionCard
            >
        </template>

        <!-- Borrador editable -->
        <form v-else class="flex flex-col gap-6" @submit.prevent="save">
            <SectionCard title="Condiciones generales">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid gap-1.5 lg:col-span-1">
                        <Label for="d-supplier">Proveedor</Label>
                        <SearchSelect
                            id="d-supplier"
                            v-model="form.supplier_id"
                            :options="options.suppliers"
                            placeholder="Cualquier proveedor"
                            clearable
                        />
                        <InputError :message="form.errors.supplier_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="d-mob">Movilización (MXN)</Label>
                        <Input
                            id="d-mob"
                            v-model="form.mobilization_fee"
                            inputmode="decimal"
                        />
                        <InputError :message="form.errors.mobilization_fee" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="d-min">Cargo mínimo (MXN)</Label>
                        <Input
                            id="d-min"
                            v-model="form.minimum_charge"
                            inputmode="decimal"
                        />
                        <InputError :message="form.errors.minimum_charge" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="d-from">Vigente desde</Label>
                        <DatePicker
                            id="d-from"
                            v-model="form.effective_from"
                            clearable
                            placeholder="Al activarlo"
                        />
                    </div>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Total de cada orden = el mayor entre (suma de partidas +
                    movilización) y el cargo mínimo. Lo calcula el sistema.
                </p>
            </SectionCard>

            <SectionCard
                title="Conceptos"
                :description="`${form.items.length} ${form.items.length === 1 ? 'concepto' : 'conceptos'}`"
            >
                <template #actions>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="addRow"
                        ><Plus class="size-4" /> Agregar</Button
                    >
                </template>
                <InputError :message="form.errors.items" class="mb-2" />
                <p
                    v-if="!form.items.length"
                    class="text-sm text-muted-foreground"
                >
                    Agrega al menos un concepto para poder activar esta versión.
                </p>
                <div class="flex flex-col gap-2">
                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="grid gap-2 rounded-lg border p-3 sm:grid-cols-[7rem_1fr_6rem_8rem_auto] sm:items-start sm:border-0 sm:p-0"
                    >
                        <div class="grid gap-1">
                            <Label
                                :for="`d-code-${index}`"
                                :class="
                                    cn(
                                        'text-xs text-muted-foreground',
                                        index > 0 && 'sm:sr-only',
                                    )
                                "
                                >Clave</Label
                            >
                            <Input
                                :id="`d-code-${index}`"
                                v-model="item.code"
                                class="uppercase"
                            />
                            <InputError :message="itemError(index, 'code')" />
                        </div>
                        <div class="grid gap-1">
                            <Label
                                :for="`d-desc-${index}`"
                                :class="
                                    cn(
                                        'text-xs text-muted-foreground',
                                        index > 0 && 'sm:sr-only',
                                    )
                                "
                                >Descripción</Label
                            >
                            <Input
                                :id="`d-desc-${index}`"
                                v-model="item.description"
                            />
                            <InputError
                                :message="itemError(index, 'description')"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label
                                :for="`d-unit-${index}`"
                                :class="
                                    cn(
                                        'text-xs text-muted-foreground',
                                        index > 0 && 'sm:sr-only',
                                    )
                                "
                                >Unidad</Label
                            >
                            <Input
                                :id="`d-unit-${index}`"
                                v-model="item.unit"
                            />
                            <InputError :message="itemError(index, 'unit')" />
                        </div>
                        <div class="grid gap-1">
                            <Label
                                :for="`d-price-${index}`"
                                :class="
                                    cn(
                                        'text-xs text-muted-foreground',
                                        index > 0 && 'sm:sr-only',
                                    )
                                "
                                >Precio unitario</Label
                            >
                            <Input
                                :id="`d-price-${index}`"
                                v-model="item.unit_price"
                                inputmode="decimal"
                                class="text-right tabular-nums"
                            />
                            <InputError
                                :message="itemError(index, 'unit_price')"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :class="
                                cn('text-destructive', index === 0 && 'sm:mt-5')
                            "
                            :aria-label="`Quitar concepto ${item.code || index + 1}`"
                            @click="removeRow(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Notas">
                <Textarea
                    v-model="form.notes"
                    rows="3"
                    placeholder="Condiciones pactadas, qué incluye la movilización…"
                />
            </SectionCard>

            <div
                class="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur md:mx-0 md:rounded-xl md:border"
            >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="!form.isDirty || form.processing"
                    @click="form.reset()"
                    >Descartar cambios</Button
                >
                <Button
                    type="submit"
                    :disabled="!form.isDirty || form.processing"
                    >{{
                        form.processing ? 'Guardando…' : 'Guardar borrador'
                    }}</Button
                >
            </div>
        </form>
    </PageContainer>

    <ConfirmDialog
        v-model:open="activateOpen"
        :title="`¿Activar la versión ${rateCard.version}?`"
        description="Quedará vigente y ya no podrá editarse. Si hay otra versión vigente de este tarifario, se retirará. Las órdenes existentes conservan sus precios; solo las nuevas usarán estos."
        confirm-label="Activar versión"
        loading-label="Activando…"
        :confirm="activate"
    />
    <ConfirmDialog
        v-model:open="deleteOpen"
        title="¿Descartar este borrador?"
        description="Se eliminarán sus conceptos. Las versiones vigentes y retiradas no se tocan."
        confirm-label="Descartar"
        loading-label="Descartando…"
        tone="destructive"
        :confirm="destroy"
    />
</template>
