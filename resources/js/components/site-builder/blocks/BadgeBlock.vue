<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { inkCss } from '../tokens';
import type { Block } from '../types';
import InlineText from './InlineText.vue';
import { useBlock } from './useBlock';

/** Antetítulo o etiqueta corta. */
const props = defineProps<{ block: Block }>();

const { str, dark, textEditing, commit, bridge } = useBlock(() => props.block);

const variant = computed(() => str('variant', 'eyebrow'));
const color = computed(() => {
    const c = str('color', 'auto');
    if (c !== 'auto') return inkCss(c);

    return variant.value === 'eyebrow'
        ? dark.value
            ? 'var(--color-oak)'
            : 'var(--color-oak-ink)'
        : undefined;
});

const classes = computed(() =>
    cn(
        'inline-flex max-w-full items-center gap-2 font-medium',
        variant.value === 'eyebrow' && 'text-sm',
        variant.value === 'pill' &&
            (dark.value
                ? 'rounded-full border border-white/25 px-3.5 py-1.5 text-xs'
                : 'rounded-full border border-border bg-card px-3.5 py-1.5 text-xs shadow-xs'),
        variant.value === 'glass' &&
            'rounded-full border border-white/25 bg-white/10 px-3.5 py-1.5 text-xs tracking-wide backdrop-blur-sm',
        variant.value === 'soft' &&
            'rounded-full bg-primary-soft px-3 py-1 text-xs text-primary',
    ),
);
</script>

<template>
    <p :class="classes" :style="{ color }">
        <span
            v-if="variant === 'eyebrow'"
            aria-hidden="true"
            class="h-px w-6 shrink-0 bg-current opacity-70"
        />
        <span
            v-else-if="variant === 'glass' || variant === 'pill'"
            aria-hidden="true"
            class="size-1.5 shrink-0 rounded-full bg-oak"
        />
        <InlineText
            v-if="textEditing"
            tag="span"
            :value="str('text')"
            placeholder="Etiqueta"
            @commit="(v) => (commit('text', v), bridge?.endTextEdit())"
            @cancel="bridge?.endTextEdit()"
        />
        <span v-else class="truncate">{{
            str('text') || (bridge ? 'Etiqueta' : '')
        }}</span>
    </p>
</template>
