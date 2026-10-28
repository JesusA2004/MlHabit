<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileText, History, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/lib/permissions';
import tarifarios from '@/routes/admin/tarifarios';
import type { BreadcrumbItem } from '@/types';
import type { EnumOption, RateCardRow, SelectOption } from '@/types/operations';

type Group = {
    name: string;
    active: RateCardRow | null;
    draft: RateCardRow | null;
    versions: RateCardRow[];
};

const props = defineProps<{
    groups: Group[];
    filters: { q?: string };
    options: { suppliers: SelectOption[]; statuses: EnumOption[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Tarifarios', href: '/admin/tarifarios' },
        ] as BreadcrumbItem[],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('rate_cards.manage'));

const search = ref(props.filters.q ?? '');
watch(search, (q) =>
    router.get(
        tarifarios.index().url,
        { q: q || undefined },
        { preserveState: true, replace: true },
    ),
);

// ------------------------------------------------------------ Nuevo borrador
type ItemRow = {
    code: string;
    description: string;
    unit: string;
    unit_price: string;
};
const blankItem = (): ItemRow => ({
    code: '',
    description: '',
    unit: 'pieza',
    unit_price: '',
});

const createOpen = ref(false);
const form = useForm({
    name: '',
    supplier_id: null as number | null,
    effective_from: null as string | null,
    mobilization_fee: '',
    minimum_charge: '',
    notes: '',
    items: [blankItem()] as ItemRow[],
});

function addRow() {
    form.items = [...form.items, blankItem()];
}

function removeRow(index: number) {
    form.items = form.items.filter((_, i) => i !== index);
}

function itemError(index: number, field: keyof ItemRow): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `items.${index}.${field}`
    ];
}

function submit() {
    form.post(tarifarios.store().url, {
        onSuccess: () => (createOpen.value = false),
    });
}

function olderVersions(group: Group): RateCardRow[] {
    return group.versions.filter(
        (v) => v.id !== group.active?.id && v.id !== group.draft?.id,
    );
}
</script>

<template>
    <Head title="Tarifarios" />

    <PageContainer>
        <PageHeader
            title="Tarifarios de instalación"
            description="Precios por módulo que se congelan en cada orden. Las versiones vigentes no se editan: crea una nueva versión para cambiar precios."
        >
            <template #actions>
                <Button v-if="canManage" @click="createOpen = true"
                    ><Plus class="size-4" /> Nuevo tarifario</Button
                >
            </template>
        </PageHeader>

        <FilterBar v-model:search="search" placeholder="Buscar tarifario" />

        <EmptyState
            v-if="!groups.length"
            :icon="FileText"
            :title="
                search ? 'Ningún tarifario coincide' : 'Aún no hay tarifarios'
            "
            :description="
                search
                    ? 'Prueba con otro nombre.'
                    : 'Captura los precios acordados con tus instaladores. Sin un tarifario vigente no se pueden crear órdenes de instalación.'
            "
        >
            <template v-if="!search && canManage" #action>
                <Button @click="createOpen = true"
                    ><Plus class="size-4" /> Crear primer tarifario</Button
                >
            </template>
        </EmptyState>

        <ul v-else class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            <li
                v-for="group in groups"
                :key="group.name"
                class="flex flex-col rounded-xl border bg-card"
            >
                <div
                    class="flex items-start justify-between gap-3 border-b p-4"
                >
                    <div class="min-w-0">
                        <h2 class="truncate font-display text-lg font-semibold">
                            {{ group.name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                (
                                    group.active ??
                                    group.draft ??
                                    group.versions[0]
                                )?.supplier?.name ?? 'Cualquier proveedor'
                            }}
                        </p>
                    </div>
                    <StatusBadge
                        v-if="group.active"
                        :label="`Vigente · v${group.active.version}`"
                        tone="success"
                    />
                    <StatusBadge
                        v-else
                        label="Sin versión vigente"
                        tone="warning"
                    />
                </div>

                <Link
                    v-if="group.active"
                    :href="tarifarios.show(group.active.id).url"
                    class="grid grid-cols-3 gap-3 p-4 transition-colors hover:bg-muted/40"
                >
                    <div>
                        <p class="text-xs text-muted-foreground">Conceptos</p>
                        <p class="font-semibold tabular-nums">
                            {{ group.active.itemsCount ?? 0 }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Movilización
                        </p>
                        <Money
                            :value="group.active.mobilizationFee"
                            class="font-semibold"
                        />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Cargo mínimo
                        </p>
                        <Money
                            :value="group.active.minimumCharge"
                            class="font-semibold"
                        />
                    </div>
                    <p class="col-span-3 text-xs text-muted-foreground">
                        Vigente desde
                        <DateText
                            :value="
                                group.active.effectiveFrom ??
                                group.active.activatedAt
                            "
                        />
                    </p>
                </Link>

                <div
                    class="mt-auto flex flex-col gap-2 p-4 pt-0"
                    :class="!group.active && 'pt-4'"
                >
                    <Link
                        v-if="group.draft"
                        :href="tarifarios.show(group.draft.id).url"
                        class="flex items-center justify-between gap-3 rounded-lg border border-dashed px-3 py-2 text-sm hover:bg-muted/40"
                    >
                        <span
                            ><span class="font-medium"
                                >Borrador v{{ group.draft.version }}</span
                            >
                            · {{ group.draft.itemsCount ?? 0 }} conceptos</span
                        >
                        <span class="text-primary">Continuar editando</span>
                    </Link>
                    <div
                        v-if="olderVersions(group).length"
                        class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <History class="size-3.5" aria-hidden="true" />
                        Historial:
                        <Link
                            v-for="v in olderVersions(group)"
                            :key="v.id"
                            :href="tarifarios.show(v.id).url"
                            class="rounded-md border px-2 py-0.5 hover:bg-muted"
                        >
                            v{{ v.version }} ·
                            {{ v.status.label.toLowerCase() }}
                        </Link>
                    </div>
                </div>
            </li>
        </ul>
    </PageContainer>

    <FormSheet
        v-model:open="createOpen"
        title="Nuevo tarifario"
        description="Se crea como borrador. Al activarlo, sus precios se usarán en las órdenes nuevas."
        size="xl"
    >
        <form id="rate-card-create" class="grid gap-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="rc-name">Nombre</Label>
                    <Input
                        id="rc-name"
                        v-model="form.name"
                        required
                        placeholder="Instalación cocinas CDMX"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="rc-supplier"
                        >Proveedor
                        <span class="text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <SearchSelect
                        id="rc-supplier"
                        v-model="form.supplier_id"
                        :options="options.suppliers"
                        placeholder="Cualquier proveedor"
                        clearable
                    />
                    <InputError :message="form.errors.supplier_id" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-1.5">
                    <Label for="rc-mob">Movilización (MXN)</Label>
                    <Input
                        id="rc-mob"
                        v-model="form.mobilization_fee"
                        inputmode="decimal"
                        placeholder="0.00"
                    />
                    <InputError :message="form.errors.mobilization_fee" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="rc-min">Cargo mínimo (MXN)</Label>
                    <Input
                        id="rc-min"
                        v-model="form.minimum_charge"
                        inputmode="decimal"
                        placeholder="0.00"
                    />
                    <InputError :message="form.errors.minimum_charge" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="rc-from">Vigente desde</Label>
                    <DatePicker
                        id="rc-from"
                        v-model="form.effective_from"
                        clearable
                        placeholder="Al activarlo"
                    />
                </div>
            </div>

            <fieldset class="grid gap-3">
                <legend class="mb-1 text-sm font-medium">Conceptos</legend>
                <InputError :message="form.errors.items" />
                <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="grid gap-2 rounded-lg border p-3 sm:grid-cols-[7rem_1fr_6rem_8rem_auto] sm:items-start"
                >
                    <div class="grid gap-1">
                        <Label
                            :for="`rc-code-${index}`"
                            class="text-xs text-muted-foreground"
                            >Clave</Label
                        >
                        <Input
                            :id="`rc-code-${index}`"
                            v-model="item.code"
                            class="uppercase"
                        />
                        <InputError :message="itemError(index, 'code')" />
                    </div>
                    <div class="grid gap-1">
                        <Label
                            :for="`rc-desc-${index}`"
                            class="text-xs text-muted-foreground"
                            >Descripción</Label
                        >
                        <Input
                            :id="`rc-desc-${index}`"
                            v-model="item.description"
                        />
                        <InputError
                            :message="itemError(index, 'description')"
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label
                            :for="`rc-unit-${index}`"
                            class="text-xs text-muted-foreground"
                            >Unidad</Label
                        >
                        <Input :id="`rc-unit-${index}`" v-model="item.unit" />
                        <InputError :message="itemError(index, 'unit')" />
                    </div>
                    <div class="grid gap-1">
                        <Label
                            :for="`rc-price-${index}`"
                            class="text-xs text-muted-foreground"
                            >Precio unitario</Label
                        >
                        <Input
                            :id="`rc-price-${index}`"
                            v-model="item.unit_price"
                            inputmode="decimal"
                            placeholder="0.00"
                        />
                        <InputError :message="itemError(index, 'unit_price')" />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-destructive sm:mt-5"
                        :aria-label="`Quitar concepto ${index + 1}`"
                        @click="removeRow(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="justify-self-start"
                    @click="addRow"
                    ><Plus class="size-4" /> Agregar concepto</Button
                >
            </fieldset>

            <div class="grid gap-1.5">
                <Label for="rc-notes">Notas</Label>
                <Textarea
                    id="rc-notes"
                    v-model="form.notes"
                    rows="2"
                    placeholder="Condiciones pactadas, qué incluye la movilización…"
                />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="createOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="rate-card-create"
                :disabled="form.processing"
                >{{ form.processing ? 'Guardando…' : 'Crear borrador' }}</Button
            >
        </template>
    </FormSheet>
</template>
