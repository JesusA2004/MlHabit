<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Muestra un monto en MXN (es-MX) a partir del decimal en cadena que envía
 * el backend ("113500.00"). Solo presentación: no hace cálculos.
 */
const props = withDefaults(
    defineProps<{
        value: string | number | null | undefined;
        currency?: string;
        /** false = sin centavos ("$113,500"). */
        decimals?: boolean;
        /** Texto cuando no hay monto. */
        fallback?: string;
        /** Muestra el código de moneda junto al monto. */
        showCurrency?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { currency: 'MXN', decimals: true, fallback: '—', showCurrency: false },
);

const formatted = computed(() =>
    formatMoney(props.value, {
        currency: props.currency,
        decimals: props.decimals,
    }),
);
</script>

<template>
    <span :class="cn('tabular-nums', props.class)" data-slot="money">
        <template v-if="formatted">
            {{ formatted }}
            <span
                v-if="props.showCurrency"
                class="ml-0.5 text-[0.8em] font-normal text-muted-foreground"
            >
                {{ props.currency }}
            </span>
        </template>
        <span v-else class="text-muted-foreground">{{ props.fallback }}</span>
    </span>
</template>
