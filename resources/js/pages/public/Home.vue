<script setup lang="ts">
import { Eye } from '@lucide/vue';
import PageRenderer from '@/components/site-builder/PageRenderer.vue';
import type { RenderSection } from '@/components/site-builder/types';
import SeoHead from '@/components/web/public/SeoHead.vue';
import type { PublicSiteData, ResponsiveImageData, SeoData } from '@/types/web';

/**
 * Portada: secciones armadas en el editor visual (Administración → Sitio
 * web → Editor visual). El acomodo de escritorio lo decide el equipo; el
 * de tableta y móvil se calcula solo.
 */
const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    sections: RenderSection[];
    heroImage: ResponsiveImageData | null;
    /** Vista previa firmada del borrador (no se indexa). */
    preview?: boolean;
}>();
</script>

<template>
    <SeoHead :seo="props.seo" :preload-image="props.heroImage" />

    <div
        v-if="props.preview"
        role="status"
        class="fixed bottom-4 left-1/2 z-50 inline-flex -translate-x-1/2 items-center gap-2 rounded-full bg-espresso px-4 py-2 text-sm text-white shadow-lg"
    >
        <Eye class="size-4 text-oak" aria-hidden="true" />
        Vista previa del borrador · aún no está publicada
    </div>

    <PageRenderer :sections="props.sections" />
</template>
