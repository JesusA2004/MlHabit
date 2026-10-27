<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import type { StudioNode } from '@/studio/types';

/**
 * Solo visible en el editor: un bloque cuyo tipo ya no existe (o viene de
 * una versión futura del esquema). No rompe la página; se puede eliminar
 * desde el panel de estructura.
 */
defineProps<{ node: StudioNode }>();
</script>

<template>
    <div class="sn-unsupported" role="alert">
        <TriangleAlert class="size-5 shrink-0" aria-hidden="true" />
        <div>
            <p class="font-semibold">Bloque no compatible: «{{ node.type }}»</p>
            <p>No se mostrará en el sitio. Elimínalo o reemplázalo para poder publicar.</p>
        </div>
    </div>
</template>
