<script setup lang="ts" generic="T extends Record<string, any>">
import { Link } from '@inertiajs/vue3';
import { Inbox } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Paginator } from '@/types/ui';
import DataPagination from './DataPagination.vue';
import DataViewCards from './DataViewCards.vue';
import EmptyState from './EmptyState.vue';
import { displayValue, rowKeyOf } from './types';
import type { DataColumn, RowKey } from './types';

/**
 * Lista responsiva: tabla en ≥768 px y tarjetas apiladas en móvil, a partir
 * de las mismas columnas. Celdas personalizables con `#cell-{key}`
 * (`{ row, value }`), acciones por fila con `#actions`, vacío con `#empty`.
 */
const props = withDefaults(
    defineProps<{
        columns: DataColumn<T>[];
        /** Filas sueltas o un paginador de Laravel. */
        rows?: T[];
        paginator?: Paginator<T>;
        rowKey?: RowKey<T>;
        /** Convierte la fila en enlace (título en tabla, tarjeta completa en móvil). */
        rowHref?: (row: T) => string;
        loading?: boolean;
        skeletonRows?: number;
        emptyTitle?: string;
        emptyDescription?: string;
        caption?: string;
        only?: string[];
        itemLabel?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        rowKey: 'id' as never,
        loading: false,
        skeletonRows: 5,
        emptyTitle: 'Sin resultados',
        emptyDescription: 'Cuando haya registros aparecerán aquí.',
    },
);

const items = computed<T[]>(() => props.rows ?? props.paginator?.data ?? []);
const tableColumns = computed(() =>
    props.columns.filter((c) => !c.hideOnDesktop),
);
const primary = computed(
    () => props.columns.find((c) => c.primary) ?? props.columns[0],
);
const keyOf = (row: T, index: number) => rowKeyOf(row, index, props.rowKey);
const valueOf = (row: T, column: DataColumn<T>): unknown =>
    column.value
        ? column.value(row)
        : (row as Record<string, unknown>)[column.key];
const display = displayValue;

const alignClass = {
    left: 'text-left',
    right: 'text-right',
    center: 'text-center',
} as const;
</script>

<template>
    <div data-slot="data-view" :class="cn('flex flex-col gap-4', props.class)">
        <!-- Escritorio: tabla -->
        <div
            v-if="props.loading || items.length > 0"
            class="hidden overflow-hidden rounded-xl border bg-card shadow-xs md:block"
        >
            <Table>
                <caption v-if="props.caption" class="sr-only">
                    {{
                        props.caption
                    }}
                </caption>
                <TableHeader class="bg-muted/50">
                    <TableRow class="hover:bg-transparent">
                        <TableHead
                            v-for="column in tableColumns"
                            :key="column.key"
                            scope="col"
                            :class="
                                cn(
                                    'h-10 px-4 text-xs font-medium text-muted-foreground',
                                    alignClass[column.align ?? 'left'],
                                    column.headerClass,
                                )
                            "
                        >
                            {{ column.label }}
                        </TableHead>
                        <TableHead v-if="$slots.actions" class="w-px px-4"
                            ><span class="sr-only">Acciones</span></TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="props.loading">
                        <TableRow
                            v-for="n in props.skeletonRows"
                            :key="`s-${n}`"
                            class="hover:bg-transparent"
                        >
                            <TableCell
                                v-for="column in tableColumns"
                                :key="column.key"
                                class="px-4 py-3.5"
                            >
                                <Skeleton
                                    class="h-4"
                                    :class="
                                        column === primary ? 'w-40' : 'w-20'
                                    "
                                />
                            </TableCell>
                            <TableCell v-if="$slots.actions" class="px-4" />
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow
                            v-for="(row, index) in items"
                            :key="keyOf(row, index)"
                            class="group/row"
                        >
                            <TableCell
                                v-for="column in tableColumns"
                                :key="column.key"
                                :class="
                                    cn(
                                        'px-4 py-3',
                                        alignClass[column.align ?? 'left'],
                                        column.class,
                                    )
                                "
                            >
                                <slot
                                    :name="`cell-${column.key}`"
                                    :row="row"
                                    :value="valueOf(row, column)"
                                >
                                    <Link
                                        v-if="
                                            props.rowHref && column === primary
                                        "
                                        :href="props.rowHref(row)"
                                        class="font-medium text-foreground underline-offset-4 hover:underline"
                                    >
                                        {{ display(valueOf(row, column)) }}
                                    </Link>
                                    <span
                                        v-else
                                        :class="
                                            column === primary && 'font-medium'
                                        "
                                    >
                                        {{ display(valueOf(row, column)) }}
                                    </span>
                                </slot>
                            </TableCell>
                            <TableCell
                                v-if="$slots.actions"
                                class="px-4 py-2 text-right"
                            >
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <slot name="actions" :row="row" />
                                </div>
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>

        <!-- Móvil: tarjetas -->
        <DataViewCards
            v-if="props.loading || items.length > 0"
            class="md:hidden"
            :columns="props.columns"
            :items="items"
            :primary="primary"
            :row-key="props.rowKey"
            :row-href="props.rowHref"
            :loading="props.loading"
            :skeleton-rows="props.skeletonRows"
            :caption="props.caption"
            :has-actions="!!$slots.actions"
        >
            <template v-for="(_, name) in $slots" #[name]="slotProps">
                <slot :name="name" v-bind="slotProps ?? {}" />
            </template>
        </DataViewCards>

        <div v-if="!props.loading && items.length === 0">
            <slot name="empty">
                <EmptyState
                    :icon="Inbox"
                    :title="props.emptyTitle"
                    :description="props.emptyDescription"
                />
            </slot>
        </div>

        <DataPagination
            v-if="props.paginator && !props.loading"
            :paginator="props.paginator"
            :only="props.only"
            :item-label="props.itemLabel"
        />
    </div>
</template>
