<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { Search, SlidersHorizontal, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

/**
 * Barra de búsqueda y filtros. En escritorio los filtros (`#filters`) van
 * en línea; en móvil se abren en una hoja inferior. `search` se emite con
 * debounce para disparar la recarga de Inertia desde la página.
 */
const search = defineModel<string>('search', { default: '' });

const props = withDefaults(
    defineProps<{
        placeholder?: string;
        /** Número de filtros activos (se muestra en el botón móvil). */
        activeCount?: number;
        debounce?: number;
        searchable?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { placeholder: 'Buscar…', activeCount: 0, debounce: 300, searchable: true },
);

const emit = defineEmits<{ clear: []; apply: [] }>();

const local = ref(search.value);
const sheetOpen = ref(false);

const commit = useDebounceFn((value: string) => {
    search.value = value;
}, props.debounce);

watch(local, (value) => commit(value));
watch(search, (value) => {
    if (value !== local.value) {
        local.value = value;
    }
});

function clearSearch() {
    local.value = '';
    search.value = '';
}

function applyMobile() {
    emit('apply');
    sheetOpen.value = false;
}
</script>

<template>
    <div
        data-slot="filter-bar"
        role="search"
        :class="
            cn(
                'flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center',
                props.class,
            )
        "
    >
        <div class="flex items-center gap-2 md:contents">
            <div
                v-if="props.searchable"
                class="group/search relative min-w-0 flex-1 md:max-w-sm xl:max-w-md"
            >
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground transition-colors duration-200 group-focus-within/search:text-primary"
                    aria-hidden="true"
                />
                <Input
                    v-model="local"
                    type="search"
                    :placeholder="props.placeholder"
                    :aria-label="props.placeholder"
                    class="h-11 bg-card pr-9 pl-9 shadow-xs transition-[border-color,box-shadow] duration-200 hover:border-input md:h-9"
                />
                <button
                    v-if="local"
                    type="button"
                    class="admin-pop absolute top-1/2 right-1.5 grid size-8 -translate-y-1/2 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground md:size-7"
                    aria-label="Limpiar búsqueda"
                    @click="clearSearch"
                >
                    <X class="size-4" />
                </button>
            </div>

            <Button
                v-if="$slots.filters"
                variant="outline"
                class="relative h-11 shrink-0 bg-card md:hidden"
                :aria-label="
                    props.activeCount
                        ? `Filtros (${props.activeCount} activos)`
                        : 'Filtros'
                "
                @click="sheetOpen = true"
            >
                <SlidersHorizontal />
                Filtros
                <span
                    v-if="props.activeCount"
                    class="admin-pop grid size-5 place-items-center rounded-full bg-primary text-[11px] font-semibold text-primary-foreground tabular-nums"
                >
                    {{ props.activeCount }}
                </span>
            </Button>
        </div>

        <div
            v-if="$slots.filters"
            class="hidden flex-wrap items-center gap-2 md:order-last md:flex md:basis-full"
        >
            <slot name="filters" />
            <Button
                v-if="props.activeCount"
                variant="ghost"
                size="sm"
                class="text-muted-foreground"
                @click="emit('clear')"
            >
                <X />
                Limpiar filtros
            </Button>
        </div>

        <div v-if="$slots.actions" class="flex items-center gap-2 md:ml-auto">
            <slot name="actions" />
        </div>

        <Sheet v-if="$slots.filters" v-model:open="sheetOpen">
            <SheetContent
                side="bottom"
                class="max-h-[85svh] gap-0 rounded-t-2xl p-0"
            >
                <SheetHeader class="border-b px-4 py-4 text-left">
                    <SheetTitle class="font-display text-lg"
                        >Filtros</SheetTitle
                    >
                    <SheetDescription
                        >Ajusta qué resultados quieres ver.</SheetDescription
                    >
                </SheetHeader>
                <div
                    class="flex flex-col gap-4 overflow-y-auto px-4 py-5 [&_button[role=combobox]]:h-11 [&_button[role=combobox]]:w-full"
                >
                    <slot name="filters" />
                </div>
                <SheetFooter class="flex-row gap-2 border-t px-4 py-3 pb-safe">
                    <Button
                        variant="outline"
                        class="h-11 flex-1"
                        :disabled="!props.activeCount"
                        @click="emit('clear')"
                    >
                        Limpiar
                    </Button>
                    <Button class="h-11 flex-1" @click="applyMobile"
                        >Ver resultados</Button
                    >
                </SheetFooter>
            </SheetContent>
        </Sheet>
    </div>
</template>
