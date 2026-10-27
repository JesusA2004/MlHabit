<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { formatDate, formatRelative, toDate } from '@/lib/format';
import type { DateStyle } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Fecha en es-MX. `relative` muestra "hace 3 horas" con la fecha completa
 * en el tooltip nativo y en `datetime` para lectores de pantalla.
 */
const props = withDefaults(
    defineProps<{
        value: string | number | Date | null | undefined;
        format?: DateStyle;
        relative?: boolean;
        fallback?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { format: 'medium', relative: false, fallback: '—' },
);

const date = computed(() => toDate(props.value));
const absolute = computed(() =>
    formatDate(date.value, props.relative ? 'datetime' : props.format),
);
const text = computed(() =>
    props.relative ? formatRelative(date.value) : absolute.value,
);
const isoValue = computed(() => date.value?.toISOString());
</script>

<template>
    <time
        v-if="date"
        :datetime="isoValue"
        :title="props.relative ? (absolute ?? undefined) : undefined"
        :class="cn('tabular-nums', props.class)"
    >
        {{ text }}
    </time>
    <span v-else :class="cn('text-muted-foreground', props.class)">{{
        props.fallback
    }}</span>
</template>
