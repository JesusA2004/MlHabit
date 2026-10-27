<script setup lang="ts">
import { Info } from '@lucide/vue';
import { computed } from 'vue';
import type { EditorialItem } from '@/types/web';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Formas de pago. El aviso de evaluación es obligatorio y siempre visible:
 * preferir pagar en parcialidades no equivale a una aprobación.
 */
const props = defineProps<{ block: Block }>();

const { str, dark } = useBlock(() => props.block);

const options = computed(() => {
    const value = props.block.props.items;

    return Array.isArray(value) ? (value as EditorialItem[]) : [];
});
</script>

<template>
    <div>
        <ul
            class="divide-y border-y"
            :class="
                dark
                    ? 'divide-white/15 border-white/15'
                    : 'divide-border border-border'
            "
        >
            <li
                v-for="(option, i) in options"
                :key="i"
                class="grid gap-2 py-6 @2xl/sbpage:grid-cols-[12rem_1fr] @2xl/sbpage:gap-8"
            >
                <h3 class="font-display text-lg font-semibold tracking-tight">
                    {{ option.title }}
                </h3>
                <p v-if="option.body" class="text-[color:var(--sb-ink-muted)]">
                    {{ option.body }}
                </p>
            </li>
        </ul>
        <p
            v-if="str('disclaimer')"
            role="note"
            class="mt-6 flex items-start gap-3 rounded-xl bg-info-soft p-4 text-sm text-info-ink"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {{ str('disclaimer') }}
        </p>
    </div>
</template>
