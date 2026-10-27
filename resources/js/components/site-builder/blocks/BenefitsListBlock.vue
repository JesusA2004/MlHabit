<script setup lang="ts">
import { computed } from 'vue';
import type { EditorialItem } from '@/types/web';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Lista editorial numerada de beneficios en dos columnas. */
const props = defineProps<{ block: Block }>();

const { dark } = useBlock(() => props.block);

const items = computed(() => {
    const value = props.block.props.items;

    return Array.isArray(value) ? (value as EditorialItem[]) : [];
});
</script>

<template>
    <dl class="grid gap-x-10 gap-y-8 @2xl/sbpage:grid-cols-2">
        <div
            v-for="(benefit, i) in items"
            :key="i"
            class="border-t pt-5"
            :class="dark ? 'border-white/15' : 'border-border'"
        >
            <dt
                class="flex items-baseline gap-3 font-display text-lg font-semibold tracking-tight"
            >
                <span
                    class="text-sm tabular-nums"
                    :class="dark ? 'text-oak' : 'text-oak-ink'"
                    aria-hidden="true"
                    >{{ String(i + 1).padStart(2, '0') }}</span
                >
                {{ benefit.title }}
            </dt>
            <dd
                v-if="benefit.body"
                class="mt-2 text-[color:var(--sb-ink-muted)]"
            >
                {{ benefit.body }}
            </dd>
        </div>
    </dl>
</template>
