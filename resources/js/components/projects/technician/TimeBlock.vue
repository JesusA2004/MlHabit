<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Bloque de hora para las tarjetas de la app de campo: la hora grande y
 * "a.m./p.m." debajo, para que nunca se parta a la mitad en móvil.
 */
const props = withDefaults(
    defineProps<{
        at: string | null;
        tone?: 'primary' | 'oak';
        icon?: Component;
        showDate?: boolean;
    }>(),
    { tone: 'primary', showDate: false },
);

const parts = computed(() => {
    const text = formatDate(props.at, 'time') ?? '';
    const match = text.match(/^(\S+)\s+(.+)$/);

    return match
        ? { main: match[1], suffix: match[2] }
        : { main: text || '—', suffix: '' };
});
</script>

<template>
    <div
        :class="
            cn(
                'flex w-[4.5rem] shrink-0 flex-col items-center justify-center gap-0.5 rounded-lg px-1 py-2',
                props.tone === 'primary'
                    ? 'bg-primary-soft text-primary'
                    : 'bg-oak/15 text-oak-ink',
            )
        "
    >
        <component
            :is="props.icon"
            v-if="props.icon"
            class="mb-0.5 size-4"
            aria-hidden="true"
        />
        <span v-if="props.showDate" class="text-[11px] font-medium">
            {{ formatDate(props.at, 'short') }}
        </span>
        <span
            class="font-display text-lg leading-none font-semibold tabular-nums"
            >{{ parts.main }}</span
        >
        <span v-if="parts.suffix" class="text-xs font-medium opacity-80">{{
            parts.suffix
        }}</span>
    </div>
</template>
