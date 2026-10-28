<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    CircleDollarSign,
    CreditCard,
    FolderKanban,
    Hourglass,
    MessagesSquare,
    ReceiptText,
    Ruler,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    DateText,
    EmptyState,
    Money,
    PageContainer,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import type { PaymentTotals, PortalPayment } from '@/components/portal/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ContractData } from '@/types/projects';

const props = defineProps<{
    payments: PortalPayment[];
    totals: PaymentTotals;
    pendingDeposits: ContractData[];
    contracts: ContractData[];
}>();

const hasCredit = (c: ContractData) => Number(c.visit_credit_amount) > 0;

type Filter = 'all' | 'paid' | 'pending';
const filter = ref<Filter>('all');
const filters: { key: Filter; label: string }[] = [
    { key: 'all', label: 'Todos' },
    { key: 'paid', label: 'Pagados' },
    { key: 'pending', label: 'Por pagar' },
];
const awaiting = computed(() => props.payments.filter((p) => p.awaiting));
const visible = computed(() =>
    props.payments.filter((p) =>
        filter.value === 'all'
            ? true
            : filter.value === 'pending'
              ? p.awaiting
              : !p.awaiting,
    ),
);

const subjectIcon = {
    visit: Ruler,
    consultation: MessagesSquare,
    project: FolderKanban,
    other: CircleDollarSign,
} as const;

/** Porcentaje cubierto del proyecto (para la barra del contrato). */
function coverage(c: ContractData): number {
    const total = Number(c.contract_amount);

    if (!total) {
        return 0;
    }

    const paid = c.deposit_paid_at ? Number(c.deposit_amount) : 0;

    return Math.min(100, Math.round((paid / total) * 100));
}
</script>

<template>
    <Head title="Mis pagos" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Pagos"
            description="Tus visitas, asesorías y anticipos: lo que pagaste, lo pendiente y cada comprobante."
        />

        <!-- Resumen -->
        <div
            class="portal-stagger grid gap-3 sm:grid-cols-3"
            style="--stagger-base: 60ms"
        >
            <div class="rounded-xl border bg-card p-4 shadow-xs sm:p-5">
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <BadgeCheck
                        class="size-4 text-success"
                        aria-hidden="true"
                    />
                    Total pagado
                </div>
                <Money
                    :value="totals.paid"
                    class="mt-2 block font-display text-2xl font-semibold tracking-tight text-foreground"
                />
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ totals.count }}
                    {{ totals.count === 1 ? 'pago' : 'pagos' }} recibidos
                </p>
            </div>
            <div
                :class="
                    cn(
                        'rounded-xl border p-4 shadow-xs sm:p-5',
                        Number(totals.pending) > 0
                            ? 'border-warning/30 bg-warning-soft'
                            : 'bg-card',
                    )
                "
            >
                <div
                    :class="
                        cn(
                            'flex items-center gap-2 text-sm',
                            Number(totals.pending) > 0
                                ? 'text-warning-ink'
                                : 'text-muted-foreground',
                        )
                    "
                >
                    <Hourglass class="size-4" aria-hidden="true" />
                    Por pagar
                </div>
                <Money
                    :value="totals.pending"
                    class="mt-2 block font-display text-2xl font-semibold tracking-tight text-foreground"
                />
                <p
                    :class="
                        cn(
                            'mt-1 text-xs',
                            Number(totals.pending) > 0
                                ? 'text-warning-ink'
                                : 'text-muted-foreground',
                        )
                    "
                >
                    {{
                        Number(totals.pending) > 0
                            ? 'Complétalo para confirmar tu cita o proyecto'
                            : 'Estás al corriente'
                    }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs sm:p-5">
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <CreditCard
                        class="size-4 text-primary"
                        aria-hidden="true"
                    />
                    Pago seguro
                </div>
                <p class="mt-2 text-sm text-foreground">
                    Tarjeta con 3D Secure procesada por Openpay. Nunca guardamos
                    los datos de tu tarjeta.
                </p>
            </div>
        </div>

        <!-- Anticipos pendientes -->
        <div
            v-for="c in pendingDeposits"
            :key="c.uuid"
            class="portal-pop flex flex-col gap-4 rounded-2xl border border-warning/30 bg-warning-soft p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-sm text-warning-ink">
                    Anticipo pendiente · {{ c.project?.name }}
                </p>
                <Money
                    :value="c.deposit_due_amount"
                    class="mt-1 block font-display text-2xl font-semibold text-foreground"
                />
                <p class="mt-1 text-sm text-warning-ink">
                    Con este pago apartamos la fabricación de tu proyecto.
                </p>
            </div>
            <Button v-if="c.pay_url" as-child size="lg" class="shrink-0">
                <Link :href="c.pay_url"
                    >Pagar anticipo
                    <ArrowRight class="size-4" aria-hidden="true"
                /></Link>
            </Button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <!-- Historial -->
            <section
                aria-labelledby="historial"
                class="flex min-w-0 flex-col gap-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2
                        id="historial"
                        class="text-base font-semibold text-foreground"
                    >
                        Historial de pagos
                    </h2>
                    <div
                        v-if="payments.length"
                        role="group"
                        aria-label="Filtrar pagos"
                        class="inline-flex rounded-lg border bg-card p-1 shadow-xs"
                    >
                        <button
                            v-for="f in filters"
                            :key="f.key"
                            type="button"
                            :aria-pressed="filter === f.key"
                            :class="
                                cn(
                                    'min-h-8 rounded-md px-3 text-sm font-medium transition-colors',
                                    filter === f.key
                                        ? 'bg-primary-soft text-primary'
                                        : 'text-muted-foreground hover:text-foreground',
                                )
                            "
                            @click="filter = f.key"
                        >
                            {{ f.label }}
                            <span
                                v-if="f.key === 'pending' && awaiting.length"
                                class="ml-0.5 text-xs tabular-nums"
                                >{{ awaiting.length }}</span
                            >
                        </button>
                    </div>
                </div>

                <div
                    v-if="payments.length"
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                >
                    <Transition name="portal-fade" mode="out-in">
                        <ul
                            :key="filter"
                            class="portal-stagger divide-y"
                            style="--stagger-base: 0ms"
                        >
                            <li
                                v-for="p in visible"
                                :key="p.uuid"
                                class="flex gap-3 px-4 py-4 transition-colors hover:bg-muted/30 sm:gap-4 sm:px-5"
                            >
                                <span
                                    :class="
                                        cn(
                                            'grid size-10 shrink-0 place-items-center rounded-full',
                                            p.awaiting
                                                ? 'bg-warning-soft text-warning-ink'
                                                : 'bg-primary-soft text-primary',
                                        )
                                    "
                                    aria-hidden="true"
                                >
                                    <component
                                        :is="subjectIcon[p.subject.kind]"
                                        class="size-[18px]"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="font-medium text-foreground"
                                            >
                                                {{
                                                    p.concept?.label ??
                                                    p.description
                                                }}
                                                <span
                                                    v-if="
                                                        p.subject.kind ===
                                                            'project' ||
                                                        p.subject.label !==
                                                            p.concept?.label
                                                    "
                                                    class="font-normal text-muted-foreground"
                                                    >·
                                                    {{ p.subject.label }}</span
                                                >
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs text-muted-foreground tabular-nums"
                                            >
                                                {{ p.folio }}
                                                <template v-if="p.paid_at">
                                                    ·
                                                    <DateText
                                                        :value="p.paid_at"
                                                        format="medium"
                                                /></template>
                                                <template
                                                    v-else-if="p.subject.date"
                                                >
                                                    · cita del
                                                    <DateText
                                                        :value="p.subject.date"
                                                        format="medium"
                                                /></template>
                                                <template v-if="p.card_last4">
                                                    ·
                                                    <span class="capitalize">{{
                                                        p.card_brand
                                                    }}</span>
                                                    ••••
                                                    {{ p.card_last4 }}</template
                                                >
                                                <template v-else-if="p.method">
                                                    ·
                                                    {{
                                                        p.method.label
                                                    }}</template
                                                >
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <Money
                                                :value="p.amount"
                                                class="block font-semibold text-foreground tabular-nums"
                                            />
                                            <p
                                                v-if="
                                                    Number(p.refunded_amount) >
                                                    0
                                                "
                                                class="text-xs text-muted-foreground"
                                            >
                                                Reembolsado
                                                <Money
                                                    :value="p.refunded_amount"
                                                />
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-2"
                                    >
                                        <StatusBadge
                                            v-if="p.status"
                                            :tone="p.status.tone"
                                            :label="p.status.label"
                                            size="sm"
                                        />
                                        <Button
                                            v-if="p.pay_url"
                                            as-child
                                            size="sm"
                                            class="ml-auto"
                                        >
                                            <a :href="p.pay_url"
                                                >Pagar ahora
                                                <ArrowRight
                                                    class="size-4"
                                                    aria-hidden="true"
                                            /></a>
                                        </Button>
                                        <a
                                            v-if="p.receipt_url"
                                            :href="p.receipt_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="ml-auto inline-flex min-h-10 items-center gap-1.5 rounded-md px-2 text-sm text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <ReceiptText
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            Comprobante
                                        </a>
                                    </div>
                                </div>
                            </li>
                            <li
                                v-if="!visible.length"
                                class="px-5 py-8 text-center text-sm text-muted-foreground"
                            >
                                No hay pagos en este filtro.
                            </li>
                        </ul>
                    </Transition>
                </div>
                <EmptyState
                    v-else
                    :icon="Wallet"
                    title="Aún no hay pagos"
                    description="Cuando pagues una asesoría, tu visita técnica o tu anticipo, verás aquí cada pago con su comprobante."
                />
            </section>

            <!-- Contratos -->
            <aside class="flex flex-col gap-4">
                <SectionCard
                    v-for="c in contracts"
                    :key="`k-${c.uuid}`"
                    :title="c.project?.name ?? 'Contrato'"
                    :description="`Contrato ${c.folio}`"
                >
                    <template #actions>
                        <StatusBadge
                            :tone="c.status.tone"
                            :label="c.status.label"
                            size="sm"
                        />
                    </template>
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">
                                Total del proyecto
                            </dt>
                            <dd class="font-medium tabular-nums">
                                <Money :value="c.contract_amount" />
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">
                                Anticipo ({{ Number(c.deposit_percent) }}%)
                            </dt>
                            <dd class="tabular-nums">
                                <Money :value="c.deposit_amount" />
                            </dd>
                        </div>
                        <div
                            v-if="hasCredit(c)"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-muted-foreground">
                                Crédito de tu visita técnica
                            </dt>
                            <dd class="text-success-ink tabular-nums">
                                −<Money :value="c.visit_credit_amount" />
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t pt-2">
                            <dt class="font-medium text-foreground">
                                Anticipo a pagar
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                <Money :value="c.deposit_due_amount" />
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-4">
                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="coverage(c)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="Anticipo cubierto del total"
                        >
                            <div
                                class="portal-bar h-full rounded-full bg-gradient-to-r from-primary to-oak"
                                :style="{ width: `${coverage(c)}%` }"
                            />
                        </div>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            <template v-if="c.deposit_paid_at">
                                Anticipo cubierto el
                                <DateText
                                    :value="c.deposit_paid_at"
                                    format="long"
                                />
                            </template>
                            <template v-else>Anticipo por cubrir</template>
                        </p>
                    </div>
                </SectionCard>
            </aside>
        </div>
    </PageContainer>
</template>
