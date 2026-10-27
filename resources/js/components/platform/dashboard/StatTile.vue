<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowRight, ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Indicador del tablero: etiqueta, cifra grande y, opcionalmente, la
 * variación contra el periodo anterior. La cifra la calcula el backend.
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
    tone?: 'default' | 'warning' | 'danger';
}>();

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
</script>

<template>
    <component
        :is="props.href ? Link : 'div'"
        :href="props.href"
        :class="
            cn(
                'group flex min-w-0 flex-col gap-1 rounded-lg bg-muted/60 p-4 transition-colors',
                props.href &&
                    'hover:bg-primary-soft focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none',
            )
        "
    >
        <span class="text-xs font-medium text-muted-foreground">{{
            props.label
        }}</span>
        <span
            :class="
                cn(
                    'font-display text-[1.75rem] leading-tight font-semibold tracking-tight tabular-nums',
                    props.tone === 'warning' && props.value
                        ? 'text-warning-ink'
                        : '',
                    props.tone === 'danger' && props.value
                        ? 'text-danger-ink'
                        : '',
                )
            "
        >
            {{ display }}
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
