<script setup lang="ts">
import StudioPageView from '@/components/public/StudioPageView.vue';
import type { StudioPayload } from '@/studio/types';
import type { SocialLinkSummary } from '@/types';

/**
 * Mi historia: la composición viene de Studio; el título, la introducción,
 * la portada y el cuerpo se administran en Contenido → Páginas y Studio
 * los muestra por referencia.
 */
defineProps<{
    social: SocialLinkSummary[];
    studio: StudioPayload;
}>();
</script>

<template>
    <StudioPageView :studio="studio" :social="social" />
</template>
