<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    ChevronRight,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import AnimatedNumber from './AnimatedNumber.vue';

/**
 * Indicador del tablero: etiqueta, cifra grande (cuenta al aparecer) y,
 * opcionalmente, la variación contra el periodo anterior. La cifra la
 * calcula el backend. Con `href` la tarjeta se eleva en hover y muestra una
 * flecha.
 */
const props = defineProps<{
    label: string;
    value: string | number | null;
    hint?: string;
    /** Diferencia absoluta vs periodo anterior. */
    delta?: number | null;
    deltaLabel?: string;
    /** Si subir es malo (p. ej. pagos fallidos). */
    invertDelta?: boolean;
    href?: string;
    icon?: Component;
    tone?: 'default' | 'warning' | 'danger';
}>();

const isNumber = computed(() => typeof props.value === 'number');

const display = computed(() => {
    if (
        props.value === null ||
        props.value === undefined ||
        props.value === ''
    ) {
        return '—';
    }

    return typeof props.value === 'number'
        ? formatNumber(props.value)
        : props.value;
});

const alert = computed(
    () =>
        (props.tone === 'warning' || props.tone === 'danger') &&
        !!props.value &&
        props.value !== '0',
);

const deltaInfo = computed(() => {
    if (props.delta === null || props.delta === undefined) {
        return null;
    }

    const d = props.delta;
    const good = props.invertDelta ? d < 0 : d > 0;
    const bad = props.invertDelta ? d > 0 : d < 0;

    return {
        text:
            d === 0
                ? 'Sin cambio'
                : `${d > 0 ? '+' : '−'}${formatNumber(Math.abs(d))}`,
        icon: d > 0 ? ArrowUpRight : d < 0 ? ArrowDownRight : ArrowRight,
        class: good
            ? 'text-success-ink bg-success-soft'
            : bad
              ? 'text-danger-ink bg-danger-soft'
              : 'text-muted-foreground bg-muted',
    };
});

const iconClass = computed(() =>
    alert.value && props.tone === 'danger'
        ? 'bg-danger-soft text-danger-ink'
        : alert.value
          ? 'bg-warning-soft text-warning-ink'
          : 'bg-card text-primary ring-1 ring-border/70',
);
</script>

<template>
    <component
        :is="props.href ? Link : 'div'"
        :href="props.href"
        :class="
            cn(
                'group relative flex min-w-0 flex-col gap-1 overflow-hidden rounded-lg border border-transparent bg-muted/60 p-4',
                alert &&
                    props.tone === 'warning' &&
                    'border-warning/15 bg-warning-soft/45',
                alert &&
                    props.tone === 'danger' &&
                    'border-danger/15 bg-danger-soft/45',
                props.href &&
                    'hover-lift hover:border-primary/20 hover:bg-card focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none',
            )
        "
    >
        <span class="flex items-start justify-between gap-2">
            <span class="text-xs font-medium text-muted-foreground">{{
                props.label
            }}</span>
            <span
                v-if="props.icon"
                :class="
                    cn(
                        '-mt-1 -mr-1 grid size-7 shrink-0 place-items-center rounded-md transition-transform duration-300 group-hover:scale-110',
                        iconClass,
                    )
                "
                aria-hidden="true"
            >
                <component :is="props.icon" class="size-3.5" />
            </span>
            <ChevronRight
                v-else-if="props.href"
                class="size-4 shrink-0 -translate-x-1 text-primary opacity-0 transition-[opacity,translate] duration-200 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:translate-x-0 group-focus-visible:opacity-100"
                aria-hidden="true"
            />
        </span>
        <span
            :class="
                cn(
                    'font-display text-[1.75rem] leading-tight font-semibold tracking-tight tabular-nums',
                    alert && props.tone === 'warning' && 'text-warning-ink',
                    alert && props.tone === 'danger' && 'text-danger-ink',
                )
            "
        >
            <AnimatedNumber v-if="isNumber" :value="props.value as number" />
            <template v-else>{{ display }}</template>
        </span>
        <span
            v-if="deltaInfo"
            class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
        >
            <span
                :class="
                    cn(
                        'inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 font-medium tabular-nums',
                        deltaInfo.class,
                    )
                "
            >
                <component
                    :is="deltaInfo.icon"
                    class="size-3"
                    aria-hidden="true"
                />
                {{ deltaInfo.text }}
            </span>
            <span v-if="props.deltaLabel">{{ props.deltaLabel }}</span>
        </span>
        <span
            v-if="props.hint"
            class="text-xs leading-snug text-muted-foreground"
            >{{ props.hint }}</span
        >
    </component>
</template>
