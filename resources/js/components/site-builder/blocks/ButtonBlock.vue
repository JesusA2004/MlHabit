<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import SmartLink from '@/components/web/public/SmartLink.vue';
import { cn } from '@/lib/utils';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Botón o enlace con las variantes de la marca. */
const props = defineProps<{ block: Block }>();

const { str, bool, editing } = useBlock(() => props.block);

const VARIANTS: Record<string, string> = {
    primary:
        'bg-primary text-primary-foreground shadow-xs hover:bg-primary-hover',
    secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
    outline:
        'border border-input bg-transparent text-[color:var(--sb-ink)] hover:bg-foreground/5',
    light: 'bg-white text-espresso shadow-xs hover:bg-linen',
    'outline-light':
        'border border-white/45 bg-transparent text-white hover:bg-white/10',
    oak: 'bg-oak text-white hover:bg-oak/90',
    link: 'h-auto! px-0! font-medium text-[color:var(--sb-link)] underline-offset-4 hover:underline',
};

const variant = computed(() => str('variant', 'primary'));
const classes = computed(() =>
    cn(
        'group inline-flex max-w-full items-center justify-center gap-2 rounded-full font-medium whitespace-nowrap transition-colors duration-150 focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none',
        str('size', 'lg') === 'lg'
            ? 'h-12 px-7 text-base'
            : 'h-10 px-5 text-sm',
        VARIANTS[variant.value] ?? VARIANTS.primary,
    ),
);
const label = computed(() => str('label') || 'Botón');
</script>

<template>
    <span v-if="editing" :class="classes" role="presentation">
        <span class="truncate">{{ label }}</span>
        <ArrowRight
            v-if="bool('icon', true)"
            class="size-4 shrink-0"
            aria-hidden="true"
        />
    </span>
    <SmartLink v-else :href="str('href', '/solicitar')" :class="classes">
        <span class="truncate">{{ label }}</span>
        <ArrowRight
            v-if="bool('icon', true)"
            class="size-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5"
            aria-hidden="true"
        />
    </SmartLink>
</template>
