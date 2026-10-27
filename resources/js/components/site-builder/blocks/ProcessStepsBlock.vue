<script setup lang="ts">
import { computed } from 'vue';
import type { EditorialItem } from '@/types/web';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Línea de tiempo numerada del proceso (de la asesoría al seguimiento). */
const props = defineProps<{ block: Block }>();

const { dark } = useBlock(() => props.block);

const steps = computed(() => {
    const value = props.block.props.steps;

    return Array.isArray(value) ? (value as EditorialItem[]) : [];
});
</script>

<template>
    <ol class="relative">
        <span
            class="absolute top-2 bottom-2 left-[1.375rem] w-px @2xl/sbpage:left-[1.875rem]"
            :class="dark ? 'bg-white/15' : 'bg-border'"
            aria-hidden="true"
        />
        <li
            v-for="(step, i) in steps"
            :key="i"
            class="relative flex gap-5 pb-10 last:pb-0 @2xl/sbpage:gap-8"
        >
            <span
                class="relative z-10 grid size-11 shrink-0 place-items-center rounded-full border font-display text-sm font-semibold tabular-nums @2xl/sbpage:size-[3.75rem] @2xl/sbpage:text-base"
                :class="
                    dark
                        ? 'border-white/15 bg-espresso text-oak'
                        : 'border-border bg-background text-oak-ink'
                "
            >
                {{ String(i + 1).padStart(2, '0') }}
            </span>
            <div class="pt-2 @2xl/sbpage:pt-3.5">
                <h3
                    class="font-display text-xl font-semibold tracking-tight @2xl/sbpage:text-2xl"
                >
                    {{ step.title }}
                </h3>
                <p
                    v-if="step.body"
                    class="mt-2 max-w-prose text-[color:var(--sb-ink-muted)]"
                >
                    {{ step.body }}
                </p>
            </div>
        </li>
    </ol>
</template>
