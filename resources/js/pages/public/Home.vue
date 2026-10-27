<script setup lang="ts">
import type { Component } from 'vue';
import BenefitsSection from '@/components/web/public/sections/BenefitsSection.vue';
import CategoriesSection from '@/components/web/public/sections/CategoriesSection.vue';
import CoverageSection from '@/components/web/public/sections/CoverageSection.vue';
import FaqSection from '@/components/web/public/sections/FaqSection.vue';
import FeaturedProjectsSection from '@/components/web/public/sections/FeaturedProjectsSection.vue';
import FinalCtaSection from '@/components/web/public/sections/FinalCtaSection.vue';
import HeroSection from '@/components/web/public/sections/HeroSection.vue';
import HomeVisitSection from '@/components/web/public/sections/HomeVisitSection.vue';
import PaymentOptionsSection from '@/components/web/public/sections/PaymentOptionsSection.vue';
import ProcessSection from '@/components/web/public/sections/ProcessSection.vue';
import TestimonialsSection from '@/components/web/public/sections/TestimonialsSection.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import type {
    HomeSection,
    PublicSiteData,
    ResponsiveImageData,
    SeoData,
} from '@/types/web';

/**
 * Portada: renderiza las secciones del CMS en el orden y la visibilidad que
 * define el equipo en Administración → Sitio web.
 */
const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    sections: HomeSection[];
    heroImage: ResponsiveImageData | null;
}>();

const registry: Record<HomeSection['type'], Component> = {
    hero: HeroSection,
    categories: CategoriesSection,
    featured_projects: FeaturedProjectsSection,
    process: ProcessSection,
    benefits: BenefitsSection,
    payment_options: PaymentOptionsSection,
    home_visit: HomeVisitSection,
    coverage: CoverageSection,
    testimonials: TestimonialsSection,
    faq: FaqSection,
    final_cta: FinalCtaSection,
};
</script>

<template>
    <SeoHead :seo="props.seo" :preload-image="props.heroImage" />

    <component
        :is="registry[section.type]"
        v-for="section in props.sections"
        :key="section.id"
        :section="section"
    />
</template>
