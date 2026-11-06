<script setup lang="ts">
import { Eye } from '@lucide/vue';
import PageRenderer from '@/components/site-builder/PageRenderer.vue';
import type { RenderSection } from '@/components/site-builder/types';
import SeoHead from '@/components/web/public/SeoHead.vue';
import type { PublicSiteData, ResponsiveImageData, SeoData } from '@/types/web';

/**
 * Página propia del sitio (Sitio web → Páginas), armada con el mismo
 * editor visual que la portada y servida en `/{slug}`.
 */
const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    page: { title: string; slug: string };
    sections: RenderSection[];
    heroImage: ResponsiveImageData | null;
    /** Borrador visto por el equipo (no se indexa). */
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
        Vista previa · esta página aún no está publicada
    </div>

    <PageRenderer v-if="props.sections.length" :sections="props.sections" />
    <section v-else class="container-site py-24 text-center">
        <h1 class="text-display-md font-display font-semibold tracking-tight">
            {{ props.page.title }}
        </h1>
        <p class="mx-auto mt-3 max-w-prose text-muted-foreground">
            Estamos preparando esta página. Vuelve pronto.
        </p>
    </section>
</template>
