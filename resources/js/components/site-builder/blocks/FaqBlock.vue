<script setup lang="ts">
import { computed } from 'vue';
import FaqAccordion from '@/components/web/public/FaqAccordion.vue';
import type { FaqData } from '@/types/web';
import type { Block } from '../types';
import DynamicEmpty from './DynamicEmpty.vue';
import ExampleNote from './fallback/ExampleNote.vue';
import { useBlock } from './useBlock';

/**
 * Preguntas frecuentes publicadas (filtradas por tema) en acordeón.
 * Respaldo («Mostrar contenido de ejemplo»): respuestas generales sobre
 * cómo empezar, sin precios ni plazos prometidos.
 */
const props = defineProps<{ block: Block }>();

const { section, bridge, bool } = useBlock(() => props.block);

const faqs = computed(
    () => (section?.value.data[props.block.id]?.items ?? []) as FaqData[],
);

const EXAMPLES: FaqData[] = [
    {
        id: -1,
        question: '¿Cómo empiezo mi proyecto?',
        answer: 'Llena la solicitud y agenda tu asesoría por videollamada. Platicamos sobre tu espacio, tus ideas y tu presupuesto, y te decimos cuál es el siguiente paso.',
        topic: 'general',
        topicLabel: 'General',
    },
    {
        id: -2,
        question: '¿Van a mi casa a medir?',
        answer: 'Sí. Un técnico visita tu domicilio, toma medidas exactas y revisa muros, pisos e instalaciones. Revisa si llegamos a tu código postal y agenda la visita en línea.',
        topic: 'visitas',
        topicLabel: 'Visitas',
    },
    {
        id: -3,
        question: '¿Puedo ver el diseño antes de aprobarlo?',
        answer: 'Sí. Te presentamos una propuesta con materiales y acabados; puedes pedir ajustes antes de aprobar la cotización y empezar la fabricación.',
        topic: 'diseno',
        topicLabel: 'Diseño',
    },
    {
        id: -4,
        question: '¿Qué formas de pago tienen?',
        answer: 'El proyecto se formaliza con un anticipo y el saldo se cubre según lo acordado en tu cotización. Si prefieres pagar en parcialidades, platícalo con tu asesor: cada caso se evalúa.',
        topic: 'pagos',
        topicLabel: 'Pagos',
    },
];
</script>

<template>
    <FaqAccordion v-if="faqs.length" :faqs="faqs" />
    <div v-else-if="bool('show_example')" class="flex flex-col gap-3">
        <ExampleNote v-if="bridge">
            Ejemplo: al publicar preguntas frecuentes aparecerán aquí.
        </ExampleNote>
        <FaqAccordion :faqs="EXAMPLES" />
    </div>
    <DynamicEmpty
        v-else-if="bridge"
        title="Aún no hay preguntas publicadas"
        body="Publica preguntas en Contenido → Preguntas frecuentes. Mientras no haya, esta sección no se muestra (o activa «Mostrar contenido de ejemplo»)."
    />
</template>
