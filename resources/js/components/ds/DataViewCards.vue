<script setup lang="ts" generic="T extends Record<string, any>">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { cellValue, displayValue, rowKeyOf } from './types';
import type { DataColumn, RowKey } from './types';

/** Vista móvil de DataView: una tarjeta por fila con las mismas columnas. */
const props = defineProps<{
    columns: DataColumn<T>[];
    items: T[];
    primary: DataColumn<T>;
    rowKey?: RowKey<T>;
    rowHref?: (row: T) => string;
    loading?: boolean;
    skeletonRows: number;
    caption?: string;
    hasActions: boolean;
}>();

const cardColumns = computed(() =>
    props.columns.filter((c) => c !== props.primary && !c.hideOnMobile),
);
</script>

<template>
    <ul class="flex flex-col gap-2.5" :aria-label="props.caption">
        <template v-if="props.loading">
            <li
                v-for="n in Math.min(props.skeletonRows, 4)"
                :key="`ms-${n}`"
                class="rounded-xl border bg-card p-4"
            >
                <Skeleton class="h-4 w-2/3" />
                <Skeleton class="mt-3 h-3 w-1/2" />
                <Skeleton class="mt-2 h-3 w-1/3" />
            </li>
        </template>
        <template v-else>
            <li
                v-for="(row, index) in props.items"
                :key="rowKeyOf(row, index, props.rowKey)"
                class="relative rounded-xl border bg-card shadow-xs transition-colors has-[a.card-link:active]:bg-muted/50"
            >
                <slot name="card" :row="row">
                    <div class="flex items-start gap-3 p-4">
                        <div class="min-w-0 flex-1">
                            <div
                                class="text-[0.9375rem] font-medium text-foreground"
                            >
                                <slot
                                    :name="`cell-${props.primary.key}`"
                                    :row="row"
                                    :value="cellValue(row, props.primary)"
                                >
                                    {{
                                        displayValue(
                                            cellValue(row, props.primary),
                                        )
                                    }}
                                </slot>
                            </div>
                            <dl
                                v-if="cardColumns.length"
                                class="mt-2.5 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm"
                            >
                                <template
                                    v-for="column in cardColumns"
                                    :key="column.key"
                                >
                                    <dt class="text-muted-foreground">
                                        {{ column.label }}
                                    </dt>
                                    <dd
                                        class="min-w-0 text-right text-foreground"
                                    >
                                        <slot
                                            :name="`cell-${column.key}`"
                                            :row="row"
                                            :value="cellValue(row, column)"
                                        >
                                            {{
                                                displayValue(
                                                    cellValue(row, column),
                                                )
                                            }}
                                        </slot>
                                    </dd>
                                </template>
                            </dl>
                        </div>
                        <div
                            v-if="props.hasActions"
                            class="relative z-[2] -mt-1 -mr-1 flex items-center"
                        >
                            <slot name="actions" :row="row" />
                        </div>
                        <ChevronRight
                            v-else-if="props.rowHref"
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </div>
                </slot>
                <Link
                    v-if="props.rowHref"
                    :href="props.rowHref(row)"
                    class="card-link absolute inset-0 z-[1] rounded-xl"
                    :aria-label="`Abrir ${displayValue(cellValue(row, props.primary))}`"
                />
            </li>
        </template>
    </ul>
</template>
