<script setup lang="ts">
import StudioPageView from '@/components/public/StudioPageView.vue';
import type { StudioPayload } from '@/studio/types';
import type { SocialLinkSummary } from '@/types';

/** Inicio: composición publicada en Studio (/admin/studio). */
defineProps<{
    social: SocialLinkSummary[];
    studio: StudioPayload;
}>();
</script>

<template>
    <StudioPageView :studio="studio" :social="social" title="Inicio" />
</template>
