<script setup lang="ts">
import { SliderRange, SliderRoot, SliderThumb, SliderTrack } from 'reka-ui';
import { computed } from 'vue';

/**
 * Deslizador accesible (reka-ui) con el estilo del sistema de diseño:
 * teclado (flechas, Inicio/Fin, RePág/AvPág), táctil y lector de pantalla.
 */
const props = withDefaults(
    defineProps<{
        modelValue: number;
        min?: number;
        max?: number;
        step?: number;
        label: string;
        disabled?: boolean;
    }>(),
    { min: 0, max: 100, step: 1, disabled: false },
);

const emit = defineEmits<{
    'update:modelValue': [value: number];
    commit: [value: number];
}>();

const value = computed({
    get: () => [props.modelValue],
    set: (v: number[]) => emit('update:modelValue', v[0] ?? props.min),
});
</script>

<template>
    <SliderRoot
        v-model="value"
        :min="props.min"
        :max="props.max"
        :step="props.step"
        :disabled="props.disabled"
        class="relative flex h-5 w-full touch-none items-center select-none data-[disabled]:opacity-50"
        @value-commit="(v: number[]) => emit('commit', v[0] ?? props.min)"
    >
        <SliderTrack
            class="relative h-1.5 w-full grow overflow-hidden rounded-full bg-muted"
        >
            <SliderRange class="absolute h-full rounded-full bg-primary" />
        </SliderTrack>
        <SliderThumb
            :aria-label="props.label"
            class="block size-4 rounded-full border-2 border-primary bg-white shadow-sm transition-[box-shadow] focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
        />
    </SliderRoot>
</template>
