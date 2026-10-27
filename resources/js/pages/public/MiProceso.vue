<script setup lang="ts">
import StudioPageView from '@/components/public/StudioPageView.vue';
import type { StudioPayload } from '@/studio/types';
import type { SocialLinkSummary } from '@/types';

/**
 * Mi proceso: composición de Studio con timeline alimentado por las
 * entradas visibles de la página del CMS.
 */
defineProps<{
    social: SocialLinkSummary[];
    studio: StudioPayload;
}>();
</script>

<template>
    <StudioPageView :studio="studio" :social="social" />
</template>
