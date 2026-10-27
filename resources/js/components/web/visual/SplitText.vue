<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

/**
 * Titular que entra palabra por palabra desde una máscara. Vue controla el
 * marcado (no se manipula el DOM). El texto completo queda disponible para
 * lectores de pantalla; las palabras animadas son decorativas.
 */
const props = withDefaults(
    defineProps<{
        text: string;
        as?: string;
        delay?: number;
        class?: HTMLAttributes['class'];
        id?: string;
    }>(),
    { as: 'span', delay: 0 },
);

const words = computed(() => props.text.split(/\s+/).filter(Boolean));
</script>

<template>
    <component
        :is="props.as"
        :id="props.id"
        :class="props.class"
        data-motion="split"
        :data-motion-delay="props.delay || undefined"
    >
        <span class="sr-only">{{ props.text }}</span>
        <span aria-hidden="true"
            ><template v-for="(word, i) in words" :key="i"
                ><span class="split-word"
                    ><span :style="{ '--i': i }">{{ word }}</span></span
                >{{ i < words.length - 1 ? ' ' : '' }}</template
            ></span
        >
    </component>
</template>
