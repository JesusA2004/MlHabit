<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Ban,
    CheckCheck,
    HandCoins,
    RotateCcw,
    ShieldCheck,
    Wallet,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import pagosProveedores from '@/routes/admin/pagos-proveedores';
import type { EnumOption, PayoutRow } from '@/types/operations';
import type { LengthAwarePaginator } from '@/types/ui';

type Row = PayoutRow & {
    can: {
        authorize: boolean;
        pay: boolean;
        reject: boolean;
        resubmit: boolean;
        blockedReason: string | null;
    };
};

const props = defineProps<{
    payouts: LengthAwarePaginator<Row>;
    filters: { estado?: string; q?: string };
    statuses: EnumOption[];
    summary: {
        pending: { count: number; amount: string };
        authorized: { count: number; amount: string };
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Pagos a proveedores', href: '/admin/pagos-proveedores' },
        ],
    },
});

const search = ref(props.filters.q ?? '');
const status = ref<string | null>(props.filters.estado ?? null);
const loading = ref(false);

watch([search, status], () => {
    router.get(
        pagosProveedores.index().url,
        { q: search.value || undefined, estado: status.value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['payouts', 'filters'],
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
});

const columns: DataColumn<Row>[] = [
    { key: 'folio', label: 'Pago', primary: true },
    { key: 'supplier', label: 'Proveedor' },
    { key: 'source', label: 'Origen' },
    { key: 'amount', label: 'Monto', align: 'right' },
    { key: 'status', label: 'Estado' },
    { key: 'trail', label: 'Seguimiento', hideOnMobile: true },
];

function sourceHref(row: Row): string | null {
    if (!row.source.id) {
        return null;
    }

    return row.source.kind === 'installation'
        ? `/admin/instalaciones/${row.source.id}`
        : `/admin/fabricacion/${row.source.id}`;
}

function send(url: string, data: Record<string, unknown> = {}): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(url, data as never, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors) =>
                reject(
                    new Error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                ),
        });
    });
}

// ---------------------------------------------------------------- Acciones

const current = ref<Row | null>(null);

const authorizeOpen = ref(false);
function askAuthorize(row: Row) {
    current.value = row;
    authorizeOpen.value = true;
}
const confirmAuthorize = () =>
    send(pagosProveedores.autorizar(current.value!.id).url);

const rejectOpen = ref(false);
const rejectReason = ref('');
function askReject(row: Row) {
    current.value = row;
    rejectReason.value = '';
    rejectOpen.value = true;
}
const confirmReject = () => {
    if (!rejectReason.value.trim()) {
        return Promise.reject(new Error('Escribe el motivo del rechazo.'));
    }

    return send(pagosProveedores.rechazar(current.value!.id).url, {
        reason: rejectReason.value,
    });
};

const resubmitOpen = ref(false);
function askResubmit(row: Row) {
    current.value = row;
    resubmitOpen.value = true;
}
const confirmResubmit = () =>
    send(pagosProveedores.reenviar(current.value!.id).url);

const payOpen = ref(false);
const payForm = useForm({ reference: '' });
function openPay(row: Row) {
    current.value = row;
    payForm.reset();
    payForm.clearErrors();
    payOpen.value = true;
}
function submitPay() {
    payForm.post(pagosProveedores.pagar(current.value!.id).url, {
        preserveScroll: true,
        onSuccess: () => (payOpen.value = false),
    });
}

const hasFilters = computed(() => !!status.value || !!search.value);
</script>

<template>
    <Head title="Pagos a proveedores" />

    <PageContainer>
        <PageHeader
            title="Pagos a proveedores"
            description="Quien solicita o ejecuta un trabajo no lo autoriza. Finanzas autoriza y después registra el pago con su referencia."
        />

        <div class="grid gap-3 sm:grid-cols-2">
            <button
                type="button"
                :class="
                    cn(
                        'flex hover-lift items-center gap-4 rounded-xl border bg-card p-4 text-left hover:border-warning/50',
                        status === 'pending_authorization' &&
                            'border-warning ring-3 ring-warning/20',
                    )
                "
                @click="
                    status =
                        status === 'pending_authorization'
                            ? null
                            : 'pending_authorization'
                "
            >
                <span
                    class="grid size-11 place-items-center rounded-xl bg-warning-soft text-warning-ink"
                    ><ShieldCheck class="size-5"
                /></span>
                <span class="min-w-0">
                    <span class="block text-sm text-muted-foreground"
                        >Por autorizar · {{ summary.pending.count }}</span
                    >
                    <Money
                        :value="summary.pending.amount"
                        class="font-display text-2xl font-semibold tracking-tight"
                    />
                </span>
            </button>
            <button
                type="button"
                :class="
                    cn(
                        'flex hover-lift items-center gap-4 rounded-xl border bg-card p-4 text-left hover:border-info/50',
                        status === 'authorized' &&
                            'border-info ring-3 ring-info/20',
                    )
                "
                @click="status = status === 'authorized' ? null : 'authorized'"
            >
                <span
                    class="grid size-11 place-items-center rounded-xl bg-info-soft text-info-ink"
                    ><Wallet class="size-5"
                /></span>
                <span class="min-w-0">
                    <span class="block text-sm text-muted-foreground"
                        >Autorizados por pagar ·
                        {{ summary.authorized.count }}</span
                    >
                    <Money
                        :value="summary.authorized.amount"
                        class="font-display text-2xl font-semibold tracking-tight"
                    />
                </span>
            </button>
        </div>

        <FilterBar
            v-model:search="search"
            placeholder="Folio o proveedor"
            :active-count="status ? 1 : 0"
            @clear="status = null"
        >
            <template #filters>
                <div
                    class="flex flex-wrap gap-1.5"
                    role="group"
                    aria-label="Estado"
                >
                    <button
                        v-for="option in statuses"
                        :key="option.value"
                        type="button"
                        :aria-pressed="status === option.value"
                        :class="
                            cn(
                                'h-9 rounded-full border px-3 text-sm transition-[color,background-color,border-color,scale] duration-150 active:scale-[0.96]',
                                status === option.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'bg-card hover:bg-muted',
                            )
                        "
                        @click="
                            status =
                                status === option.value ? null : option.value
                        "
                    >
                        {{ option.label }}
                    </button>
                </div>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="payouts"
            :loading="loading"
            item-label="pagos"
        >
            <template #cell-folio="{ row }">
                <span class="font-medium">{{ row.folio }}</span>
                <span class="block text-xs text-muted-foreground"
                    ><DateText :value="row.createdAt"
                /></span>
            </template>
            <template #cell-supplier="{ row }">{{
                row.supplier?.name ?? '—'
            }}</template>
            <template #cell-source="{ row }">
                <Link
                    v-if="sourceHref(row)"
                    :href="sourceHref(row)!"
                    class="text-primary underline-offset-4 hover:underline"
                >
                    {{
                        row.source.kind === 'installation'
                            ? 'Instalación'
                            : 'Fabricación'
                    }}
                    {{ row.source.folio }}
                </Link>
            </template>
            <template #cell-amount="{ row }"
                ><Money :value="row.amount" class="font-medium tabular-nums"
            /></template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="row.status.tone"
                    :label="row.status.label"
                />
                <p
                    v-if="row.rejectionReason"
                    class="mt-1 max-w-56 text-xs text-danger-ink"
                >
                    {{ row.rejectionReason }}
                </p>
                <p
                    v-if="row.can.blockedReason"
                    class="mt-1 max-w-56 text-xs text-muted-foreground"
                >
                    {{ row.can.blockedReason }}
                </p>
            </template>
            <template #cell-trail="{ row }">
                <ul class="text-xs text-muted-foreground">
                    <li>Solicitó: {{ row.requestedBy?.name ?? '—' }}</li>
                    <li v-if="row.authorizedBy">
                        Autorizó: {{ row.authorizedBy.name }},
                        <DateText :value="row.authorizedAt" />
                    </li>
                    <li v-if="row.paidBy">
                        Pagó: {{ row.paidBy.name }},
                        <DateText :value="row.paidAt" /> · Ref.
                        {{ row.reference }}
                    </li>
                </ul>
            </template>
            <template #actions="{ row }">
                <Button
                    v-if="row.can.authorize"
                    size="sm"
                    @click="askAuthorize(row)"
                    ><ShieldCheck class="size-4" /> Autorizar</Button
                >
                <Button v-if="row.can.pay" size="sm" @click="openPay(row)"
                    ><HandCoins class="size-4" /> Registrar pago</Button
                >
                <Button
                    v-if="row.can.reject"
                    size="sm"
                    variant="ghost"
                    class="text-destructive"
                    @click="askReject(row)"
                    ><Ban class="size-4" /> Rechazar</Button
                >
                <Button
                    v-if="row.can.resubmit"
                    size="sm"
                    variant="outline"
                    @click="askResubmit(row)"
                    ><RotateCcw class="size-4" /> Volver a solicitar</Button
                >
            </template>
            <template #empty>
                <EmptyState
                    :icon="CheckCheck"
                    :title="
                        hasFilters
                            ? 'Ningún pago coincide'
                            : 'No hay pagos a proveedores'
                    "
                    :description="
                        hasFilters
                            ? 'Quita los filtros para ver todos.'
                            : 'Los pagos se solicitan desde una instalación aprobada por calidad o desde una orden de fabricación emitida.'
                    "
                />
            </template>
        </DataView>
    </PageContainer>

    <ConfirmDialog
        v-model:open="authorizeOpen"
        title="Autorizar pago"
        confirm-label="Autorizar pago"
        loading-label="Autorizando…"
        :confirm="confirmAuthorize"
    >
        <p v-if="current" class="mb-2">
            {{
                `Autorizas pagar ${current.folio} a ${current.supplier?.name ?? 'el proveedor'}.`
            }}
        </p>
        <Money
            v-if="current"
            :value="current.amount"
            class="font-display text-2xl font-semibold"
        />
    </ConfirmDialog>

    <ConfirmDialog
        v-model:open="rejectOpen"
        title="Rechazar pago"
        confirm-label="Rechazar pago"
        tone="destructive"
        :confirm="confirmReject"
    >
        <div class="flex flex-col gap-2">
            <p>Quien lo solicitó podrá corregir y volver a solicitarlo.</p>
            <Label for="reject-reason">Motivo</Label>
            <Textarea
                id="reject-reason"
                v-model="rejectReason"
                rows="3"
                maxlength="300"
            />
        </div>
    </ConfirmDialog>

    <ConfirmDialog
        v-model:open="resubmitOpen"
        title="Volver a solicitar"
        :description="
            current
                ? `${current.folio} regresará a autorización con el monto vigente de su orden.`
                : ''
        "
        confirm-label="Volver a solicitar"
        :confirm="confirmResubmit"
    />

    <FormSheet
        v-model:open="payOpen"
        title="Registrar pago"
        :description="
            current ? `${current.folio} · ${current.supplier?.name ?? ''}` : ''
        "
    >
        <form
            id="pay-form"
            class="flex flex-col gap-5"
            @submit.prevent="submitPay"
        >
            <div v-if="current" class="rounded-xl bg-muted/60 p-4">
                <p class="text-sm text-muted-foreground">Monto autorizado</p>
                <Money
                    :value="current.amount"
                    class="font-display text-3xl font-semibold tracking-tight"
                />
            </div>
            <div class="grid gap-2">
                <Label for="pay-ref">Referencia del pago</Label>
                <Input
                    id="pay-ref"
                    v-model="payForm.reference"
                    placeholder="Clave de rastreo SPEI, número de cheque…"
                    autocomplete="off"
                />
                <InputError
                    :message="
                        payForm.errors.reference ??
                        (payForm.errors as Record<string, string | undefined>)
                            .status
                    "
                />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="payOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="pay-form"
                :disabled="payForm.processing || !payForm.reference.trim()"
                ><Spinner v-if="payForm.processing" /> Registrar pago</Button
            >
        </template>
    </FormSheet>
</template>
