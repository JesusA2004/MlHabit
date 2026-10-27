<script setup lang="ts">
import '../../../css/site-builder.css';
import SectionRenderer from './SectionRenderer.vue';
import type { RenderSection } from './types';

/**
 * Página construida con el editor visual: el mismo renderizador para el
 * sitio público, la vista previa del borrador y el lienzo del editor.
 */
const props = defineProps<{ sections: RenderSection[] }>();
</script>

<template>
    <div class="sb-page motion-scope">
        <SectionRenderer
            v-for="(section, index) in props.sections"
            :key="section.key"
            :section="section"
            :index="index"
        />
    </div>
</template>
