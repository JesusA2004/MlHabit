<script setup lang="ts">
import { computed } from 'vue';

/** Slider + campo numérico sincronizados, con unidad. */
const props = defineProps<{
    modelValue: number | undefined;
    min: number;
    max: number;
    step?: number;
    unit?: string;
    label: string;
    placeholder?: number | string;
    zeroLabel?: string;
    inherited?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: number]; commit: [] }>();

const value = computed(() => props.modelValue ?? (typeof props.placeholder === 'number' ? props.placeholder : props.min));
const percent = computed(() => ((value.value - props.min) / (props.max - props.min)) * 100);

function clamp(n: number): number {
    const step = props.step ?? 1;
    const rounded = Math.round(n / step) * step;

    return Math.min(props.max, Math.max(props.min, Number(rounded.toFixed(4))));
}

function onRange(event: Event) {
    emit('update:modelValue', clamp(Number((event.target as HTMLInputElement).value)));
}

function onNumber(event: Event) {
    const raw = (event.target as HTMLInputElement).value;
    if (raw === '' || Number.isNaN(Number(raw))) return;
    emit('update:modelValue', clamp(Number(raw)));
}
</script>

<template>
    <div class="flex items-center gap-2">
        <input
            type="range"
            class="studio-range h-8 min-w-0 flex-1"
            :class="{ 'is-inherited': inherited || modelValue === undefined }"
            :min="min"
            :max="max"
            :step="step ?? 1"
            :value="value"
            :aria-label="label"
            :style="{ '--fill': `${percent}%` }"
            @input="onRange"
            @change="emit('commit')"
        />
        <div class="relative w-[84px] shrink-0">
            <input
                type="number"
                inputmode="decimal"
                class="h-8 w-full rounded-md border border-input bg-background px-2 pr-7 text-right text-xs tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring/60"
                :class="{ 'text-muted-foreground': inherited || modelValue === undefined }"
                :min="min"
                :max="max"
                :step="step ?? 1"
                :value="modelValue ?? (typeof placeholder === 'number' ? placeholder : '')"
                :placeholder="zeroLabel && value === 0 ? zeroLabel : String(placeholder ?? '')"
                :aria-label="`${label} (valor)`"
                @change="onNumber"
                @keydown.enter.prevent="onNumber"
            />
            <span class="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-[10px] text-muted-foreground">{{ unit }}</span>
        </div>
    </div>
</template>

<style>
.studio-range {
    -webkit-appearance: none;
    appearance: none;
    background: transparent;
    cursor: pointer;
}
.studio-range::-webkit-slider-runnable-track {
    height: 4px;
    border-radius: 999px;
    background: linear-gradient(to right, var(--color-brand) var(--fill), var(--color-border) var(--fill));
}
.studio-range.is-inherited::-webkit-slider-runnable-track {
    background: linear-gradient(to right, color-mix(in srgb, var(--color-brand) 45%, transparent) var(--fill), var(--color-border) var(--fill));
}
.studio-range::-moz-range-track {
    height: 4px;
    border-radius: 999px;
    background: var(--color-border);
}
.studio-range::-moz-range-progress {
    height: 4px;
    border-radius: 999px;
    background: var(--color-brand);
}
.studio-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    margin-top: -6px;
    border-radius: 999px;
    background: var(--color-background);
    border: 2px solid var(--color-brand);
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.4);
}
.studio-range::-moz-range-thumb {
    width: 14px;
    height: 14px;
    border-radius: 999px;
    background: var(--color-background);
    border: 2px solid var(--color-brand);
}
@media (pointer: coarse) {
    .studio-range::-webkit-slider-thumb {
        width: 24px;
        height: 24px;
        margin-top: -10px;
    }
}
</style>
