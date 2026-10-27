<script setup lang="ts" generic="V extends string | number">
import { Check, ChevronsUpDown, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
    ComboboxViewport,
} from '@/components/ui/combobox';
import { cn } from '@/lib/utils';

export type SearchSelectOption<T extends string | number = string | number> = {
    value: T;
    label: string;
    description?: string;
    disabled?: boolean;
};

/**
 * Select con búsqueda (Combobox de reka-ui). v-model con el `value` de la
 * opción o null. Filtra por etiqueta y descripción sin distinguir acentos.
 * Para búsquedas remotas escucha `search` y actualiza `options`.
 */
const model = defineModel<V | null>({ default: null });

const props = withDefaults(
    defineProps<{
        options: SearchSelectOption<V>[];
        id?: string;
        name?: string;
        placeholder?: string;
        searchPlaceholder?: string;
        emptyText?: string;
        clearable?: boolean;
        disabled?: boolean;
        invalid?: boolean;
        /** Si es true, no filtra localmente (búsqueda en servidor). */
        remote?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        placeholder: 'Selecciona una opción',
        searchPlaceholder: 'Buscar…',
        emptyText: 'Sin coincidencias.',
        clearable: false,
        disabled: false,
        invalid: false,
        remote: false,
    },
);

const emit = defineEmits<{ search: [term: string] }>();

const open = ref(false);
const term = ref('');

const normalize = (text: string) =>
    text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

const selected = computed(
    () => props.options.find((o) => o.value === model.value) ?? null,
);

const filtered = computed(() => {
    if (props.remote || !term.value.trim()) {
        return props.options;
    }

    const needle = normalize(term.value.trim());

    return props.options.filter((o) =>
        normalize(`${o.label} ${o.description ?? ''}`).includes(needle),
    );
});

function onTerm(value: string) {
    term.value = value;
    emit('search', value);
}

function onSelect(value: unknown) {
    model.value = (value as V | null) ?? null;
    term.value = '';
    open.value = false;
}
</script>

<template>
    <Combobox
        v-model:open="open"
        :model-value="model"
        :ignore-filter="true"
        :disabled="props.disabled"
        :reset-search-term-on-select="true"
        @update:model-value="onSelect"
    >
        <ComboboxAnchor as-child>
            <div :class="cn('relative w-full', props.class)">
                <ComboboxTrigger as-child>
                    <button
                        :id="props.id"
                        type="button"
                        role="combobox"
                        :aria-expanded="open"
                        :aria-invalid="props.invalid || undefined"
                        :disabled="props.disabled"
                        :class="
                            cn(
                                'flex h-11 w-full items-center justify-between gap-2 rounded-md border border-input bg-card px-3 text-left text-sm shadow-xs transition-[color,box-shadow] outline-none md:h-9',
                                'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/40',
                                'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
                                'disabled:cursor-not-allowed disabled:opacity-50',
                                props.clearable && selected && 'pr-16',
                            )
                        "
                    >
                        <span
                            :class="
                                cn(
                                    'truncate',
                                    !selected && 'text-muted-foreground',
                                )
                            "
                        >
                            {{ selected?.label ?? props.placeholder }}
                        </span>
                        <ChevronsUpDown
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </button>
                </ComboboxTrigger>
                <button
                    v-if="props.clearable && selected && !props.disabled"
                    type="button"
                    class="absolute top-1/2 right-8 grid size-7 -translate-y-1/2 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                    aria-label="Quitar selección"
                    @click.stop="model = null"
                >
                    <X class="size-3.5" />
                </button>
            </div>
        </ComboboxAnchor>

        <ComboboxList
            class="w-(--reka-combobox-trigger-width) min-w-56 rounded-lg p-0 shadow-lg"
            align="start"
        >
            <ComboboxInput
                :model-value="term"
                :placeholder="props.searchPlaceholder"
                class="h-10"
                @update:model-value="onTerm"
            />
            <ComboboxViewport class="max-h-72 p-1">
                <p
                    v-if="filtered.length === 0"
                    class="px-3 py-6 text-center text-sm text-muted-foreground"
                    role="status"
                >
                    {{ props.emptyText }}
                </p>
                <ComboboxGroup>
                    <ComboboxItem
                        v-for="option in filtered"
                        :key="String(option.value)"
                        :value="option.value"
                        :disabled="option.disabled"
                        class="min-h-10 rounded-md px-2.5 py-2 md:min-h-8"
                    >
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate">{{ option.label }}</span>
                            <span
                                v-if="option.description"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ option.description }}
                            </span>
                        </span>
                        <ComboboxItemIndicator class="ml-auto">
                            <Check class="size-4 text-primary" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxGroup>
            </ComboboxViewport>
        </ComboboxList>
    </Combobox>
    <input
        v-if="props.name"
        type="hidden"
        :name="props.name"
        :value="model ?? ''"
    />
</template>
