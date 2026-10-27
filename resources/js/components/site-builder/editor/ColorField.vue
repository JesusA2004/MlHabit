<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Selector de color limitado a los tokens de la marca, con la opción de
 * escribir un #hex propio (el servidor solo acepta #RRGGBB).
 */
const props = defineProps<{
    modelValue: string;
    tokens: { value: string; label: string; css: string }[];
    label: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const isHex = computed(() => /^#[0-9a-f]{6}$/i.test(props.modelValue));
const hex = ref(isHex.value ? props.modelValue : '');
watch(
    () => props.modelValue,
    (v) => {
        if (/^#[0-9a-f]{6}$/i.test(v)) hex.value = v;
    },
);

function commitHex() {
    const value = hex.value.trim().startsWith('#')
        ? hex.value.trim()
        : `#${hex.value.trim()}`;
    if (/^#[0-9a-f]{6}$/i.test(value))
        emit('update:modelValue', value.toLowerCase());
}
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="props.label"
        class="flex flex-wrap items-center gap-1.5"
    >
        <button
            v-for="token in props.tokens"
            :key="token.value"
            type="button"
            role="radio"
            :aria-checked="props.modelValue === token.value"
            :aria-label="token.label"
            :title="token.label"
            :class="
                cn(
                    'relative grid size-7 place-items-center rounded-full ring-1 ring-black/10 transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    props.modelValue === token.value &&
                        'ring-2 ring-primary ring-offset-2 ring-offset-card',
                )
            "
            :style="{
                background:
                    token.value === 'auto'
                        ? 'conic-gradient(var(--color-foreground) 0 50%, #fff 0)'
                        : token.css,
            }"
            @click="emit('update:modelValue', token.value)"
        >
            <Check
                v-if="props.modelValue === token.value"
                class="size-3.5 text-white mix-blend-difference"
                aria-hidden="true"
            />
        </button>
        <label class="relative ml-1 flex items-center">
            <span class="sr-only">Color propio en hexadecimal</span>
            <span
                class="pointer-events-none absolute left-2 size-4 rounded-full ring-1 ring-black/10"
                :style="{
                    background: isHex ? props.modelValue : 'transparent',
                }"
                aria-hidden="true"
            />
            <Input
                v-model="hex"
                placeholder="#RRGGBB"
                maxlength="7"
                class="h-8 w-[6.5rem] pl-8 font-mono text-xs uppercase"
                :class="isHex && 'ring-2 ring-primary'"
                @keydown.enter.prevent="commitHex"
                @blur="commitHex"
            />
        </label>
    </div>
</template>
