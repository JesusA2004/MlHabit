<script setup lang="ts">
import { TriangleAlert, Wand2, X } from '@lucide/vue';
import { computed } from 'vue';
import { contrastRatio, readableOn, resolveHex, TOKEN_DEFAULTS } from '@/studio/color';
import { schema } from '@/studio/schema';

/**
 * Selector de color: tokens de marca primero (se adaptan a Apariencia),
 * hex personalizado después. Si se indica `against`, muestra el contraste
 * WCAG y ofrece corregirlo con un clic.
 */
const props = defineProps<{
    modelValue: string | undefined;
    label: string;
    inherited?: boolean;
    /** Color de fondo contra el que se mide el contraste. */
    against?: string;
    largeText?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string | undefined] }>();

const tokens = Object.entries(schema.colorTokens);
const hex = computed(() => (props.modelValue ? resolveHex(props.modelValue) : ''));

const ratio = computed(() => {
    if (!props.modelValue || !props.against) return null;

    return contrastRatio(resolveHex(props.modelValue), resolveHex(props.against));
});
const minimum = computed(() => (props.largeText ? 3 : 4.5));

function onHex(event: Event) {
    const value = (event.target as HTMLInputElement).value.trim();
    const normalized = value.startsWith('#') ? value : `#${value}`;
    if (/^#[0-9a-fA-F]{6}$/.test(normalized)) emit('update:modelValue', normalized.toUpperCase());
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-center gap-1.5" role="radiogroup" :aria-label="label">
            <button
                v-for="[token, name] in tokens"
                :key="token"
                type="button"
                role="radio"
                :aria-checked="modelValue === token"
                :title="name"
                class="size-7 rounded-full border border-border shadow-xs transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring"
                :class="modelValue === token && (inherited ? 'ring-2 ring-brand/40 ring-offset-1 ring-offset-background' : 'ring-2 ring-brand ring-offset-2 ring-offset-background')"
                :style="{ background: `var(--color-${token}, ${TOKEN_DEFAULTS[token]})` }"
                @click="emit('update:modelValue', token)"
            >
                <span class="sr-only">{{ name }}</span>
            </button>
            <label class="relative size-7 cursor-pointer overflow-hidden rounded-full border border-dashed border-muted-foreground/60" title="Color personalizado">
                <input
                    type="color"
                    class="absolute inset-0 size-full cursor-pointer opacity-0"
                    :value="hex || '#C7AA7D'"
                    :aria-label="`${label}: color personalizado`"
                    @input="emit('update:modelValue', ($event.target as HTMLInputElement).value.toUpperCase())"
                />
                <span class="block size-full" :style="{ background: modelValue && !(modelValue in TOKEN_DEFAULTS) ? modelValue : 'conic-gradient(#c7aa7d, #3f7d58, #2563eb, #c2410c, #c7aa7d)' }" />
            </label>
            <input
                class="h-7 w-[84px] rounded-md border border-input bg-background px-2 font-mono text-[11px] uppercase outline-none focus-visible:ring-2 focus-visible:ring-ring/60"
                :class="{ 'text-muted-foreground': inherited }"
                :value="hex"
                maxlength="7"
                placeholder="#HEX"
                :aria-label="`${label}: hexadecimal`"
                @change="onHex"
            />
            <button
                v-if="modelValue && !inherited"
                type="button"
                class="grid size-7 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                title="Quitar color"
                @click="emit('update:modelValue', undefined)"
            >
                <X class="size-3.5" />
            </button>
        </div>

        <div
            v-if="ratio !== null"
            class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-[11px]"
            :class="ratio >= minimum ? 'bg-success/10 text-success' : 'bg-warning/15 text-warning'"
            role="status"
        >
            <span class="flex items-center gap-1.5">
                <TriangleAlert v-if="ratio < minimum" class="size-3.5" />
                Contraste {{ ratio.toFixed(1) }}:1 · {{ ratio >= minimum ? 'legible' : `mínimo ${minimum}:1` }}
            </span>
            <button
                v-if="ratio < minimum && against"
                type="button"
                class="inline-flex items-center gap-1 font-semibold underline-offset-2 hover:underline"
                @click="emit('update:modelValue', readableOn(against))"
            >
                <Wand2 class="size-3" /> Corregir
            </button>
        </div>
    </div>
</template>
