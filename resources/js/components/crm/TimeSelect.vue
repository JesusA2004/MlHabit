<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

/**
 * Selector de hora ("HH:MM", 24 h) en pasos fijos. Muestra la hora en
 * formato es-MX ("9:30 a. m.").
 */
const model = defineModel<string | null>({ default: null });

const props = withDefaults(
    defineProps<{
        id?: string;
        from?: string;
        to?: string;
        step?: number;
        placeholder?: string;
        invalid?: boolean;
        disabled?: boolean;
        ariaLabel?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        from: '06:00',
        to: '22:00',
        step: 30,
        placeholder: 'Hora',
        invalid: false,
        disabled: false,
    },
);

function toMinutes(value: string): number {
    const [h, m] = value.split(':').map(Number);

    return h * 60 + (m || 0);
}

function label(value: string): string {
    const [h, m] = value.split(':').map(Number);

    return new Intl.DateTimeFormat('es-MX', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(2000, 0, 1, h, m));
}

const options = computed(() => {
    const out: { value: string; label: string }[] = [];

    for (
        let t = toMinutes(props.from);
        t <= toMinutes(props.to);
        t += props.step
    ) {
        const value = `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
        out.push({ value, label: label(value) });
    }

    if (model.value && !out.some((o) => o.value === model.value)) {
        out.push({ value: model.value, label: label(model.value) });
        out.sort((a, b) => toMinutes(a.value) - toMinutes(b.value));
    }

    return out;
});

const value = computed({
    get: () => model.value ?? undefined,
    set: (v: string | undefined) => (model.value = v ?? null),
});
</script>

<template>
    <Select v-model="value" :disabled="disabled">
        <SelectTrigger
            :id="id"
            :aria-label="ariaLabel"
            :aria-invalid="invalid ? true : undefined"
            :class="cn('w-full tabular-nums', props.class)"
        >
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent class="max-h-72">
            <SelectItem
                v-for="option in options"
                :key="option.value"
                :value="option.value"
                >{{ option.label }}</SelectItem
            >
        </SelectContent>
    </Select>
</template>
