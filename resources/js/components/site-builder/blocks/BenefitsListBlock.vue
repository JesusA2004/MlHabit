<script setup lang="ts">
import { computed } from 'vue';
import { featureIcon, ICON_STROKE } from '@/components/web/visual/icons';
import type { EditorialItem } from '@/types/web';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Propuesta de valor: beneficios en dos columnas, cada uno con su icono
 * (deducido del título) y número. En hover/foco la tarjeta se eleva, el
 * icono gira y una regla de roble se dibuja bajo el título.
 */
const props = defineProps<{ block: Block }>();

const { dark } = useBlock(() => props.block);

const items = computed(() => {
    const value = props.block.props.items;

    return Array.isArray(value) ? (value as EditorialItem[]) : [];
});
</script>

<template>
    <dl class="grid gap-4 @2xl/sbpage:grid-cols-2">
        <div
            v-for="(benefit, i) in items"
            :key="i"
            class="group relative rounded-2xl p-5 ring-1 transition-[transform,box-shadow,background-color] duration-500 ease-out hover:-translate-y-1"
            :class="
                dark
                    ? 'bg-white/[0.04] ring-white/10 hover:bg-white/[0.07]'
                    : 'bg-card/70 ring-border hover:bg-card hover:shadow-[0_18px_40px_-24px_rgb(35_30_26/0.35)]'
            "
        >
            <dt class="flex items-start gap-3.5">
                <span
                    class="grid size-11 shrink-0 place-items-center rounded-xl transition-transform duration-500 ease-out group-hover:-rotate-6"
                    :class="
                        dark
                            ? 'bg-oak/20 text-oak'
                            : 'bg-primary-soft text-primary'
                    "
                    aria-hidden="true"
                >
                    <component
                        :is="featureIcon(`${benefit.title} ${benefit.body}`)"
                        class="size-5"
                        :stroke-width="ICON_STROKE"
                    />
                </span>
                <span class="min-w-0 pt-0.5">
                    <span
                        class="block text-xs font-semibold tabular-nums"
                        :class="dark ? 'text-oak' : 'text-oak-ink'"
                        aria-hidden="true"
                        >{{ String(i + 1).padStart(2, '0') }}</span
                    >
                    <span
                        class="block font-display text-lg leading-snug font-semibold tracking-tight"
                        >{{ benefit.title }}</span
                    >
                    <span
                        class="mt-1.5 block h-px w-8 origin-left scale-x-50 bg-oak transition-transform duration-500 ease-out group-hover:scale-x-100"
                        aria-hidden="true"
                    />
                </span>
            </dt>
            <dd
                v-if="benefit.body"
                class="mt-3 pl-[3.625rem] text-[0.9375rem] leading-relaxed text-[color:var(--sb-ink-muted)]"
            >
                {{ benefit.body }}
            </dd>
        </div>
    </dl>
</template>
