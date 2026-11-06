<script setup lang="ts">
import { Info } from '@lucide/vue';

/**
 * Aviso del EDITOR sobre un respaldo de ejemplo (no aparece en el sitio):
 * explica qué contenido real lo reemplazará.
 */
</script>

<template>
    <p
        class="inline-flex items-center gap-2 self-start rounded-full border border-dashed border-oak/50 bg-linen/90 px-3 py-1 text-xs font-medium text-oak-ink"
    >
        <Info class="size-3.5" aria-hidden="true" />
        <slot />
    </p>
</template>
