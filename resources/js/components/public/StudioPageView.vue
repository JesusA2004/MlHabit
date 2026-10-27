<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import PageRenderer from '@/components/blocks/PageRenderer.vue';
import type { StudioPayload } from '@/studio/types';
import type { SocialLinkSummary } from '@/types';

/**
 * Vista pública de una página compuesta en Studio (incluye SEO y el aviso
 * de vista previa para borradores).
 */
const { studio, title } = defineProps<{
    studio: StudioPayload;
    social: SocialLinkSummary[];
    title?: string;
    previewing?: boolean;
}>();
</script>

<template>
    <Head :title="title ?? studio.meta.seoTitle ?? studio.meta.title">
        <meta
            v-if="studio.meta.seoDescription"
            head-key="description"
            name="description"
            :content="studio.meta.seoDescription"
        />
        <meta v-if="previewing" head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>

    <div
        v-if="previewing"
        class="relative z-50 flex items-center justify-center gap-2 bg-gold px-4 py-2 text-center text-sm font-semibold text-ink"
        role="status"
    >
        <Eye class="size-4" aria-hidden="true" />
        Vista previa del borrador — estos cambios aún no son públicos.
    </div>

    <PageRenderer :document="studio.document" :resolved="studio.resolved" :social="social" />
</template>
