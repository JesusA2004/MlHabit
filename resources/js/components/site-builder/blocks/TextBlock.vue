<script setup lang="ts">
import { computed } from 'vue';
import { inkCss } from '../tokens';
import type { Block } from '../types';
import InlineText from './InlineText.vue';
import { useBlock } from './useBlock';

/**
 * Párrafos con formato limitado (negritas, cursivas, enlaces y listas). El
 * HTML llega saneado del servidor (App\Support\Html) y del editor
 * (sanitizeRichText), por eso se puede pintar con v-html.
 */
const props = defineProps<{ block: Block }>();

const { str, textEditing, commit, bridge } = useBlock(() => props.block);

const classes = computed(() => ['sb-text', `sb-text--${str('size', 'base')}`]);
const style = computed(() => ({ color: inkCss(str('color', 'auto')) }));
</script>

<template>
    <InlineText
        v-if="textEditing"
        rich
        :class="classes"
        :style="style"
        :value="str('html')"
        placeholder="Escribe un texto"
        @commit="(v) => (commit('html', v), bridge?.endTextEdit())"
        @cancel="bridge?.endTextEdit()"
    />
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div
        v-else-if="str('html')"
        :class="classes"
        :style="style"
        v-html="str('html')"
    />
    <p v-else-if="bridge" :class="classes" class="opacity-50">
        Doble clic para escribir
    </p>
</template>
