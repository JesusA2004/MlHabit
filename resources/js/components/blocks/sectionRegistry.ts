import type { Component } from 'vue';
import SectionCategories from './sections/SectionCategories.vue';
import SectionColumns from './sections/SectionColumns.vue';
import SectionCta from './sections/SectionCta.vue';
import SectionGallery from './sections/SectionGallery.vue';
import SectionHero from './sections/SectionHero.vue';
import SectionPosts from './sections/SectionPosts.vue';
import SectionSocial from './sections/SectionSocial.vue';
import SectionTimeline from './sections/SectionTimeline.vue';

/** Registro de secciones permitidas (ver schema.json → sections). */
export const sectionComponents: Record<string, Component> = {
    hero: SectionHero,
    columns: SectionColumns,
    posts: SectionPosts,
    categories: SectionCategories,
    gallery: SectionGallery,
    timeline: SectionTimeline,
    cta: SectionCta,
    social: SectionSocial,
};
