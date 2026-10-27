<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { inkCss } from '../tokens';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Lista de puntos con palomitas, viñetas o números. */
const props = defineProps<{ block: Block }>();

const { str, bridge, dark } = useBlock(() => props.block);

const items = computed(() => {
    const value = props.block.props.items;

    return Array.isArray(value)
        ? value
              .map((i) =>
                  i && typeof i === 'object' && 'text' in i
                      ? String((i as { text: unknown }).text)
                      : '',
              )
              .filter(Boolean)
        : [];
});
const marker = computed(() => str('marker', 'check'));
</script>

<template>
    <component
        :is="marker === 'number' ? 'ol' : 'ul'"
        class="grid gap-3"
        :style="{ color: inkCss(str('color', 'auto')) }"
    >
        <li
            v-for="(item, i) in items"
            :key="i"
            class="flex items-start gap-3 leading-relaxed"
        >
            <span
                v-if="marker === 'check'"
                class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground"
                aria-hidden="true"
            >
                <Check class="size-3.5" />
            </span>
            <span
                v-else-if="marker === 'number'"
                class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full font-display text-xs font-semibold tabular-nums"
                :class="
                    dark
                        ? 'bg-white/10 text-oak'
                        : 'bg-primary-soft text-primary'
                "
                aria-hidden="true"
                >{{ i + 1 }}</span
            >
            <span
                v-else
                class="mt-2.5 size-1.5 shrink-0 rounded-full bg-oak"
                aria-hidden="true"
            />
            <span>{{ item }}</span>
        </li>
        <li v-if="!items.length && bridge" class="text-sm opacity-60">
            Agrega puntos en el panel derecho
        </li>
    </component>
</template>
