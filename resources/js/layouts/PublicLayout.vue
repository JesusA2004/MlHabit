<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppToaster from '@/components/ds/AppToaster.vue';
import SiteFooter from '@/components/ds/marketing/SiteFooter.vue';
import SiteHeader from '@/components/ds/marketing/SiteHeader.vue';
import JourneyGuide from '@/components/web/visual/journey/JourneyGuide.vue';
import { createMotion } from '@/lib/motion';
import type { MotionController } from '@/lib/motion';
import type { SiteData, SiteNavLink } from '@/types/public';

/**
 * Layout del sitio público (siempre claro). Recibe `site` del backend
 * (props de página compartidas). `overlay` pinta la cabecera transparente
 * sobre un hero a sangre; las páginas lo activan con
 * `defineOptions({ layout: { overlay: true } })`. El SEO lo define cada página.
 *
 * Aquí vive el sistema de movimiento (`lib/motion`): revelados, parallax,
 * efectos de puntero, transiciones de página y el recorrido guiado.
 */
const props = withDefaults(
    defineProps<{
        site?: Partial<SiteData> | null;
        navigation?: SiteNavLink[];
        overlay?: boolean;
    }>(),
    { overlay: false },
);

const page = usePage();

const site = computed<Partial<SiteData>>(
    () =>
        props.site ??
        (page.props.site as Partial<SiteData> | undefined) ?? {
            name: 'ML Habitat',
        },
);
const siteName = computed(() => site.value.name || 'ML Habitat');

const root = ref<HTMLElement | null>(null);
let motion: MotionController | null = null;

onMounted(() => {
    if (root.value) {
        motion = createMotion(root.value);
    }
});

onBeforeUnmount(() => {
    motion?.destroy();
    motion = null;
});
</script>

<template>
    <Head :title="siteName" />
    <div
        ref="root"
        class="motion-scope flex min-h-svh flex-col bg-background text-foreground"
    >
        <a
            href="#contenido"
            class="sr-only z-50 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            Saltar al contenido
        </a>
        <SiteHeader
            :site="site"
            :navigation="props.navigation"
            :overlay="props.overlay"
        />
        <main id="contenido" tabindex="-1" class="flex-1 outline-none">
            <slot />
        </main>
        <SiteFooter :site="site" />
        <JourneyGuide />
        <AppToaster />
    </div>
</template>
