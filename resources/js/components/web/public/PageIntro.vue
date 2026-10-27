<script setup lang="ts">
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import type { IllustrationName } from '@/components/web/visual/illustrations/types';
import SplitText from '@/components/web/visual/SplitText.vue';
import type { JourneyStepKey } from '@/lib/motion/journey';
import PublicBreadcrumbs from './PublicBreadcrumbs.vue';

/**
 * Encabezado de páginas interiores del sitio: migas, título (entra palabra
 * por palabra), entrada y, opcionalmente, una ilustración de línea que se
 * dibuja sobre una cuadrícula de plano. `journey` marca el paso del
 * recorrido guiado.
 */
const props = defineProps<{
    title: string;
    eyebrow?: string | null;
    lead?: string | null;
    breadcrumbs?: { label: string; href?: string }[];
    visual?: IllustrationName | null;
    journey?: JourneyStepKey | null;
}>();
</script>

<template>
    <header
        class="relative isolate overflow-hidden border-b border-border/70 bg-linen"
        :data-journey="props.journey || undefined"
    >
        <div
            class="tex-blueprint pointer-events-none absolute inset-0 -z-10 [mask-image:radial-gradient(ellipse_at_80%_30%,#000_10%,transparent_70%)]"
            aria-hidden="true"
        />
        <div class="container-site pt-10 pb-12 sm:pt-14 sm:pb-16">
            <PublicBreadcrumbs
                v-if="props.breadcrumbs?.length"
                :items="props.breadcrumbs"
                class="mb-8"
                data-motion="fade-in"
            />
            <div
                :class="[
                    'grid gap-6 lg:grid-cols-12',
                    props.visual ? 'lg:items-center' : 'lg:items-end',
                ]"
            >
                <div :class="props.visual ? 'lg:col-span-7' : 'lg:col-span-8'">
                    <p
                        v-if="props.eyebrow"
                        class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-oak-ink"
                        data-motion="fade-in"
                    >
                        <span aria-hidden="true" class="eyebrow-rule" />
                        {{ props.eyebrow }}
                    </p>
                    <SplitText
                        as="h1"
                        class="text-display-xl"
                        :text="props.title"
                    />
                    <p
                        v-if="props.lead && props.visual"
                        class="mt-6 max-w-xl text-lead text-muted-foreground"
                        data-motion="fade-up"
                        data-motion-delay="250"
                    >
                        {{ props.lead }}
                    </p>
                </div>
                <p
                    v-if="props.lead && !props.visual"
                    class="text-lead text-muted-foreground lg:col-span-4"
                    data-motion="fade-up"
                    data-motion-delay="250"
                >
                    {{ props.lead }}
                </p>
                <div
                    v-if="props.visual"
                    class="relative hidden lg:col-span-5 lg:block"
                    data-parallax="-0.06"
                >
                    <LineIllustration
                        :name="props.visual"
                        class="w-full text-foreground/80"
                        :delay="300"
                    />
                </div>
            </div>
            <slot />
        </div>
    </header>
</template>
