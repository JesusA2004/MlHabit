<script setup lang="ts">
import { computed } from 'vue';
import FaqAccordion from '@/components/web/public/FaqAccordion.vue';
import type { FaqData } from '@/types/web';
import type { Block } from '../types';
import DynamicEmpty from './DynamicEmpty.vue';
import { useBlock } from './useBlock';

/** Preguntas frecuentes publicadas (filtradas por tema) en acordeón. */
const props = defineProps<{ block: Block }>();

const { section, bridge } = useBlock(() => props.block);

const faqs = computed(
    () => (section?.value.data[props.block.id]?.items ?? []) as FaqData[],
);
</script>

<template>
    <FaqAccordion v-if="faqs.length" :faqs="faqs" />
    <DynamicEmpty
        v-else-if="bridge"
        title="Aún no hay preguntas publicadas"
        body="Publica preguntas en Contenido → Preguntas frecuentes. Mientras no haya, esta sección no se muestra."
    />
</template>
