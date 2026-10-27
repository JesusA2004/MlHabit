<script setup lang="ts">
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export type ChoiceOption = {
    value: string;
    label: string;
    description?: string | null;
};

/**
 * Opciones como tarjetas seleccionables (radio accesible de reka-ui:
 * flechas para moverse, espacio para elegir). v-model = value o null.
 */
const model = defineModel<string | null>({ default: null });

const props = withDefaults(
    defineProps<{
        options: ChoiceOption[];
        name: string;
        label: string;
        columns?: 1 | 2 | 3;
        invalid?: boolean;
        describedBy?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { columns: 2, invalid: false, describedBy: undefined },
);

const cols = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-3',
} as const;
</script>

<template>
    <RadioGroupRoot
        :model-value="model ?? undefined"
        :name="name"
        :aria-label="label"
        :aria-describedby="describedBy"
        :aria-invalid="invalid || undefined"
        :class="cn('grid gap-2.5', cols[columns], props.class)"
        @update:model-value="(v) => (model = (v as string | undefined) ?? null)"
    >
        <RadioGroupItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            :class="
                cn(
                    'group flex min-h-12 w-full items-start gap-3 rounded-lg border bg-card px-4 py-3 text-left transition-colors duration-150',
                    'hover:border-primary/50 focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none',
                    'data-[state=checked]:border-primary data-[state=checked]:bg-primary-soft',
                    invalid && 'border-danger/60',
                )
            "
        >
            <span
                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border border-input bg-card group-data-[state=checked]:border-primary"
                aria-hidden="true"
            >
                <span
                    class="size-2 rounded-full bg-primary opacity-0 group-data-[state=checked]:opacity-100"
                />
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-medium text-foreground">{{
                    option.label
                }}</span>
                <span
                    v-if="option.description"
                    class="mt-0.5 block text-xs leading-relaxed text-muted-foreground"
                >
                    {{ option.description }}
                </span>
            </span>
        </RadioGroupItem>
    </RadioGroupRoot>
</template>
