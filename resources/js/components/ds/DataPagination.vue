<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { formatNumber } from '@/lib/format';
import { normalizePaginator, pageUrl } from '@/lib/pagination';
import { cn } from '@/lib/utils';
import type { Paginator } from '@/types/ui';

/**
 * Paginación para paginadores de Laravel (`{data, links, meta}` o el
 * formato plano). Navega con Inertia conservando los filtros del query
 * string. Usa `only` para recargas parciales.
 */
const props = withDefaults(
    defineProps<{
        paginator: Paginator<unknown>;
        only?: string[];
        preserveScroll?: boolean;
        itemLabel?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { preserveScroll: false, itemLabel: 'resultados' },
);

const emit = defineEmits<{ change: [page: number] }>();

const state = computed(() => normalizePaginator(props.paginator));

function go(page: number) {
    if (!state.value || page === state.value.currentPage) {
        return;
    }

    emit('change', page);
    router.get(
        pageUrl(page),
        {},
        {
            preserveState: true,
            preserveScroll: props.preserveScroll,
            only: props.only,
        },
    );
}
</script>

<template>
    <div
        v-if="state && state.total > 0"
        :class="
            cn(
                'flex flex-col items-center gap-3 sm:flex-row sm:justify-between',
                props.class,
            )
        "
    >
        <p
            class="text-sm text-muted-foreground tabular-nums"
            aria-live="polite"
        >
            <template v-if="state.from && state.to">
                {{ formatNumber(state.from) }}–{{ formatNumber(state.to) }} de
                {{ formatNumber(state.total) }} {{ props.itemLabel }}
            </template>
            <template v-else
                >{{ formatNumber(state.total) }} {{ props.itemLabel }}</template
            >
        </p>

        <Pagination
            v-if="state.lastPage > 1"
            v-slot="{ page }"
            :page="state.currentPage"
            :total="state.total"
            :items-per-page="state.perPage"
            :sibling-count="1"
            show-edges
            class="mx-0 w-auto"
            @update:page="go"
        >
            <PaginationContent v-slot="{ items }">
                <PaginationPrevious
                    class="h-11 sm:h-9"
                    aria-label="Página anterior"
                >
                    <ChevronLeft />
                    <span class="hidden sm:block">Anterior</span>
                </PaginationPrevious>
                <template v-for="(item, index) in items" :key="index">
                    <PaginationItem
                        v-if="item.type === 'page'"
                        :value="item.value"
                        :is-active="item.value === page"
                        class="hidden size-9 tabular-nums sm:inline-flex"
                        :aria-label="`Página ${item.value}`"
                    >
                        {{ item.value }}
                    </PaginationItem>
                    <PaginationEllipsis
                        v-else
                        :index="index"
                        class="hidden sm:flex"
                    />
                </template>
                <span
                    class="px-2 text-sm text-muted-foreground tabular-nums sm:hidden"
                >
                    {{ page }} / {{ state.lastPage }}
                </span>
                <PaginationNext
                    class="h-11 sm:h-9"
                    aria-label="Página siguiente"
                >
                    <span class="hidden sm:block">Siguiente</span>
                    <ChevronRight />
                </PaginationNext>
            </PaginationContent>
        </Pagination>
    </div>
</template>
