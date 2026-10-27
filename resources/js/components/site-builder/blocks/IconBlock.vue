<script setup lang="ts">
import { computed } from 'vue';
import { ICONS } from '../icons';
import { inkCss } from '../tokens';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Ícono curado de Lucide, opcionalmente dentro de un círculo. */
const props = defineProps<{ block: Block }>();

const { str, bool, dark } = useBlock(() => props.block);

const SIZES: Record<string, { icon: number; frame: number }> = {
    sm: { icon: 16, frame: 36 },
    md: { icon: 22, frame: 48 },
    lg: { icon: 28, frame: 60 },
    xl: { icon: 36, frame: 76 },
};

const icon = computed(() => ICONS[str('name', 'sparkles')] ?? ICONS.sparkles);
const size = computed(() => SIZES[str('size', 'md')] ?? SIZES.md);
</script>

<template>
    <span
        class="inline-grid shrink-0 place-items-center rounded-full"
        :class="
            bool('framed', true)
                ? dark
                    ? 'bg-white/10 ring-1 ring-white/15'
                    : 'bg-primary-soft'
                : ''
        "
        :style="{
            width: bool('framed', true) ? `${size.frame}px` : undefined,
            height: bool('framed', true) ? `${size.frame}px` : undefined,
            color: inkCss(str('color', 'primary')),
        }"
    >
        <component
            :is="icon.component"
            :size="size.icon"
            :stroke-width="1.75"
            aria-hidden="true"
        />
    </span>
</template>
