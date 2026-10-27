<script setup lang="ts">
import { cn } from '@/lib/utils';

/** Control segmentado (radiogroup) para enums cortos. */
const model = defineModel<string | null | undefined>();

defineProps<{
    options: { value: string; label: string }[];
    label: string;
    inherited?: boolean;
}>();
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="label"
        class="flex w-full gap-0.5 rounded-md border border-border bg-muted/50 p-0.5"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            :class="
                cn(
                    'min-h-8 flex-1 truncate rounded-[5px] px-2 text-xs font-medium pointer-coarse:min-h-11 text-muted-foreground transition-colors hover:text-foreground',
                    model === option.value &&
                        (inherited
                            ? 'bg-background/60 text-foreground shadow-xs'
                            : 'bg-background text-foreground shadow-xs ring-1 ring-brand/40'),
                )
            "
            @click="model = option.value"
        >
            {{ option.label }}
        </button>
    </div>
</template>
