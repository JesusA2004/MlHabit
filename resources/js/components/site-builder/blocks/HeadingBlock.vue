<script setup lang="ts">
import { computed } from 'vue';
import { inkCss } from '../tokens';
import type { Block } from '../types';
import InlineText from './InlineText.vue';
import { useBlock } from './useBlock';

/**
 * Título (H1–H3) con los tamaños de la escala tipográfica de la marca.
 *
 * Con el movimiento «Palabra por palabra» (`split`) el sitio público
 * dibuja cada palabra en su máscara (motion.css); el texto completo queda
 * para lectores de pantalla. En tamaño `display` la última palabra lleva
 * el acento de roble.
 */
const props = defineProps<{ block: Block }>();

const { str, textEditing, commit, bridge } = useBlock(() => props.block);

const tag = computed(() => str('level', 'h2'));
const size = computed(() => str('size', 'lg'));
const classes = computed(() => ['sb-heading', `sb-heading--${size.value}`]);
const style = computed(() => ({ color: inkCss(str('color', 'auto')) }));

const split = computed(() => !bridge && props.block.motion.preset === 'split');
const words = computed(() => str('text').split(/\s+/).filter(Boolean));
const accent = computed(
    () => size.value === 'display' && words.value.length > 2,
);
</script>

<template>
    <InlineText
        v-if="textEditing"
        :tag="tag"
        :class="classes"
        :style="style"
        :value="str('text')"
        placeholder="Escribe un título"
        @commit="(v) => (commit('text', v), bridge?.endTextEdit())"
        @cancel="bridge?.endTextEdit()"
    />
    <component
        :is="tag"
        v-else-if="split || accent"
        :class="classes"
        :style="style"
    >
        <span class="sr-only">{{ str('text') }}</span>
        <span aria-hidden="true"
            ><template v-for="(word, i) in words" :key="i"
                ><span
                    :class="[
                        split && 'split-word',
                        accent && i === words.length - 1 && 'sb-accent',
                    ]"
                    ><span :style="{ '--i': i }">{{ word }}</span></span
                >{{ i < words.length - 1 ? ' ' : '' }}</template
            ></span
        >
    </component>
    <component :is="tag" v-else :class="classes" :style="style">{{
        str('text') || (bridge ? 'Título sin texto' : '')
    }}</component>
</template>
