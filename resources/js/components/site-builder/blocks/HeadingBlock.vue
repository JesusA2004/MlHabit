<script setup lang="ts">
import { computed } from 'vue';
import { inkCss } from '../tokens';
import type { Block } from '../types';
import InlineText from './InlineText.vue';
import { useBlock } from './useBlock';

/** Título (H1–H3) con los tamaños de la escala tipográfica de la marca. */
const props = defineProps<{ block: Block }>();

const { str, textEditing, commit, bridge } = useBlock(() => props.block);

const tag = computed(() => str('level', 'h2'));
const classes = computed(() => [
    'sb-heading',
    `sb-heading--${str('size', 'lg')}`,
]);
const style = computed(() => ({ color: inkCss(str('color', 'auto')) }));
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
    <component :is="tag" v-else :class="classes" :style="style">{{
        str('text') || (bridge ? 'Título sin texto' : '')
    }}</component>
</template>
