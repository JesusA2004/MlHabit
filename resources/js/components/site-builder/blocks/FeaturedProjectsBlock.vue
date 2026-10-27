<script setup lang="ts">
import { computed } from 'vue';
import ShowcaseCard from '@/components/web/public/ShowcaseCard.vue';
import type { ShowcaseCardData } from '@/types/web';
import type { Block } from '../types';
import DynamicEmpty from './DynamicEmpty.vue';
import { useBlock } from './useBlock';

/** Proyectos destacados: composición asimétrica (uno grande + columna). */
const props = defineProps<{ block: Block }>();

const { section, bridge } = useBlock(() => props.block);

const projects = computed(
    () =>
        (section?.value.data[props.block.id]?.items ??
            []) as ShowcaseCardData[],
);
const lead = computed(() => projects.value[0] ?? null);
const rest = computed(() => projects.value.slice(1));
</script>

<template>
    <div v-if="lead" class="grid gap-5 @5xl/sbpage:grid-cols-12">
        <ShowcaseCard
            :project="lead"
            size="lg"
            :class="
                rest.length
                    ? '@5xl/sbpage:col-span-7'
                    : '@5xl/sbpage:col-span-12'
            "
        />
        <div
            v-if="rest.length"
            class="grid gap-5 @2xl/sbpage:grid-cols-2 @5xl/sbpage:col-span-5 @5xl/sbpage:grid-cols-1"
        >
            <ShowcaseCard
                v-for="project in rest.slice(0, 2)"
                :key="project.id"
                :project="project"
            />
        </div>
        <div
            v-if="rest.length > 2"
            class="grid gap-5 @2xl/sbpage:grid-cols-2 @5xl/sbpage:col-span-12 @5xl/sbpage:grid-cols-3"
        >
            <ShowcaseCard
                v-for="project in rest.slice(2)"
                :key="project.id"
                :project="project"
            />
        </div>
    </div>
    <DynamicEmpty
        v-else-if="bridge"
        title="Aún no hay proyectos destacados"
        body="Marca como destacado un proyecto publicado en Inspiración. Mientras tanto, esta sección no se muestra en el sitio."
    />
</template>
