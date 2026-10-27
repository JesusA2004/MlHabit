<script setup lang="ts">
import StudioPageView from '@/components/public/StudioPageView.vue';
import type { StudioPayload } from '@/studio/types';
import type { SocialLinkSummary } from '@/types';

/** Páginas personalizadas de Studio y vistas previas de borradores. */
withDefaults(
    defineProps<{
        social: SocialLinkSummary[];
        studio: StudioPayload;
        previewing?: boolean;
    }>(),
    { previewing: false },
);
</script>

<template>
    <StudioPageView :studio="studio" :social="social" :previewing="previewing" />
</template>
