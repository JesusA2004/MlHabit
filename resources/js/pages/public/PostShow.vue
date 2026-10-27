<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import StudioPageView from '@/components/public/StudioPageView.vue';
import type { StudioPayload } from '@/studio/types';
import type { SocialLinkSummary } from '@/types';

/**
 * Publicación (Tip) renderizada a través del motor de bloques de Studio:
 * la plantalla del sistema `tip` vincula sus bloques a la publicación actual.
 */
const props = withDefaults(
    defineProps<{
        studio: StudioPayload;
        social: SocialLinkSummary[];
        previewing?: boolean;
    }>(),
    { previewing: false },
);

// Barra de progreso de lectura (oro) sobre el desplazamiento de toda la página.
const progress = ref(0);
let raf = 0;
function onScroll() {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => {
        const doc = document.documentElement;
        const total = doc.scrollHeight - window.innerHeight;
        progress.value = total > 0 ? Math.min(1, Math.max(0, window.scrollY / total)) : 0;
    });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    onScroll();
});
onBeforeUnmount(() => {
    cancelAnimationFrame(raf);
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);
});
</script>

<template>
    <Head :title="studio.meta.title">
        <meta
            v-if="studio.meta.seoDescription"
            head-key="description"
            name="description"
            :content="studio.meta.seoDescription"
        />
        <meta v-if="previewing" head-key="robots" name="robots" content="noindex, nofollow" />
    </Head>

    <div
        class="sn-read-progress fixed inset-x-0 top-0 z-50 h-0.5 origin-left bg-gold"
        :style="{ transform: `scaleX(${progress})` }"
        aria-hidden="true"
    />

    <StudioPageView :studio="studio" :social="social" :previewing="previewing" :title="studio.meta.title" />
</template>
