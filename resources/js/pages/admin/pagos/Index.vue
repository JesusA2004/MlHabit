<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CreditCard, Wallet } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DataView from '@/components/ds/DataView.vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import type { DataColumn } from '@/components/ds/types';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { LengthAwarePaginator } from '@/types/ui';

type Option = { value: string; label: string; tone: string };

type PaymentRow = {
    uuid: string;
    folio: string;
    orderId: string;
    concept: Option;
    description: string;
    method: Option;
    provider: Option;
    amount: string;
    refundedAmount: string;
    refundableAmount: string;
    currency: string;
    status: Option;
    cardBrand: string | null;
    cardLast4: string | null;
    authorization: string | null;
    reference: string | null;
    paidAt: string | null;
    createdAt: string | null;
    errorMessage: string | null;
    customer: { name: string; email: string | null } | null;
};

type Total = {
    status: Option;
    count: number;
    amount: string;
    refunded: string;
};

const props = defineProps<{
    payments: LengthAwarePaginator<PaymentRow>;
    totals: Total[];
    filters: {
        estado?: string;
        concepto?: string;
        metodo?: string;
        desde?: string;
        hasta?: string;
        q?: string;
    };
    options: { statuses: Option[]; concepts: Option[]; methods: Option[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Pagos', href: '/admin/pagos' },
        ],
    },
});

const ALL = '__all';
const tone = (o: { tone?: string } | null | undefined) =>
    (o?.tone ?? 'neutral') as Tone;

const search = ref(props.filters.q ?? '');
const estado = ref(props.filters.estado ?? ALL);
const concepto = ref(props.filters.concepto ?? ALL);
const metodo = ref(props.filters.metodo ?? ALL);
const desde = ref<string | null>(props.filters.desde ?? null);
const hasta = ref<string | null>(props.filters.hasta ?? null);
const loading = ref(false);

const activeCount = computed(
    () =>
        [estado.value, concepto.value, metodo.value].filter((v) => v !== ALL)
            .length +
        (desde.value ? 1 : 0) +
        (hasta.value ? 1 : 0),
);

function reload() {
    const pick = (v: string) => (v === ALL ? undefined : v);

    router.get(
        '/admin/pagos',
        {
            q: search.value || undefined,
            estado: pick(estado.value),
            concepto: pick(concepto.value),
            metodo: pick(metodo.value),
            desde: desde.value || undefined,
            hasta: hasta.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

watch([search, estado, concepto, metodo, desde, hasta], reload);

function clearFilters() {
    estado.value = ALL;
    concepto.value = ALL;
    metodo.value = ALL;
    desde.value = null;
    hasta.value = null;
}

const columns: DataColumn<PaymentRow>[] = [
    { key: 'folio', label: 'Pago', primary: true },
    { key: 'concept', label: 'Concepto' },
    { key: 'amount', label: 'Monto', align: 'right' },
    { key: 'status', label: 'Estado' },
    { key: 'method', label: 'Método', hideOnMobile: true },
    { key: 'createdAt', label: 'Fecha' },
];

const showUrl = (uuid: string) => `/admin/pagos/${uuid}`;
</script>

<template>
    <Head title="Pagos" />

    <PageContainer>
        <PageHeader
            title="Pagos"
            description="Cobros con tarjeta (Openpay) y pagos registrados manualmente: estado, reembolsos y conciliación."
        />

        <section
            aria-label="Totales por estado"
            class="grid grid-cols-2 gap-3 md:grid-cols-4"
        >
            <div
                v-for="t in totals"
                :key="t.status.value"
                class="rounded-xl border bg-card p-4 shadow-xs"
            >
                <div class="flex items-center justify-between gap-2">
                    <StatusBadge
                        :tone="tone(t.status)"
                        :label="t.status.label"
                        size="sm"
                    />
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{{ t.count }}
                        {{ t.count === 1 ? 'pago' : 'pagos' }}</span
                    >
                </div>
                <Money
                    :value="t.amount"
                    class="mt-3 block font-display text-xl font-semibold tabular-nums"
                />
                <p
                    v-if="Number(t.refunded) > 0"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    Reembolsado: <Money :value="t.refunded" />
                </p>
            </div>
            <p
                v-if="totals.length === 0"
                class="col-span-full text-sm text-muted-foreground"
            >
                Sin pagos con estos filtros.
            </p>
        </section>

        <FilterBar
            v-model:search="search"
            placeholder="Folio, orden, referencia o cliente"
            :active-count="activeCount"
            @clear="clearFilters"
        >
            <template #filters>
                <Select v-model="estado">
                    <SelectTrigger class="md:w-44" aria-label="Estado"
                        ><SelectValue placeholder="Estado"
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
                <Select v-model="concepto">
                    <SelectTrigger class="md:w-44" aria-label="Concepto"
                        ><SelectValue placeholder="Concepto"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL"
                            >Todos los conceptos</SelectItem
                        >
                        <SelectItem
                            v-for="s in options.concepts"
                            :key="s.value"
                            :value="s.value"
                            >{{ s.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="metodo">
                    <SelectTrigger class="md:w-40" aria-label="Método"
                        ><SelectValue placeholder="Método"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todos los métodos</SelectItem>
                        <SelectItem
                            v-for="s in options.methods"
                            :key="s.value"
                            :value="s.value"
                            >{{ s.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <DatePicker
                    v-model="desde"
                    placeholder="Desde"
                    :max="hasta ?? undefined"
                    clearable
                    class="md:w-40"
                />
                <DatePicker
                    v-model="hasta"
                    placeholder="Hasta"
                    :min="desde ?? undefined"
                    clearable
                    class="md:w-40"
                />
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="payments"
            :loading="loading"
            row-key="uuid"
            :row-href="(row) => showUrl(row.uuid)"
            item-label="pagos"
            caption="Pagos"
        >
            <template #cell-folio="{ row }">
                <div class="min-w-0">
                    <Link
                        :href="showUrl(row.uuid)"
                        class="font-medium underline-offset-4 hover:underline"
                        >{{ row.customer?.name ?? row.folio }}</Link
                    >
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ row.folio }}
                    </p>
                </div>
            </template>
            <template #cell-concept="{ row }">
                <span class="text-sm">{{ row.concept.label }}</span>
            </template>
            <template #cell-amount="{ row }">
                <Money :value="row.amount" class="font-medium tabular-nums" />
                <p
                    v-if="Number(row.refundedAmount) > 0"
                    class="text-xs text-muted-foreground"
                >
                    −<Money :value="row.refundedAmount" />
                </p>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="tone(row.status)"
                    :label="row.status.label"
                    size="sm"
                />
            </template>
            <template #cell-method="{ row }">
                <span class="inline-flex items-center gap-1.5 text-sm">
                    <CreditCard
                        v-if="row.method.value === 'card'"
                        class="size-3.5 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {{ row.method.label
                    }}<template v-if="row.cardLast4">
                        ···· {{ row.cardLast4 }}</template
                    >
                </span>
            </template>
            <template #cell-createdAt="{ row }">
                <DateText
                    :value="row.paidAt ?? row.createdAt"
                    format="datetime"
                    class="text-sm"
                />
            </template>
            <template #empty>
                <EmptyState
                    :icon="Wallet"
                    title="Sin pagos"
                    description="Cuando un cliente pague su visita o anticipo, el pago aparecerá aquí."
                />
            </template>
        </DataView>
    </PageContainer>
</template>
