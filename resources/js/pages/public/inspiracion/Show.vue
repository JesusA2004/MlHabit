<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import CtaBand from '@/components/web/public/CtaBand.vue';
import GalleryLightbox from '@/components/web/public/GalleryLightbox.vue';
import OriginBadge from '@/components/web/public/OriginBadge.vue';
import PublicBreadcrumbs from '@/components/web/public/PublicBreadcrumbs.vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import RichText from '@/components/web/public/RichText.vue';
import SectionHeading from '@/components/web/public/SectionHeading.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import ShowcaseCard from '@/components/web/public/ShowcaseCard.vue';
import type {
    PublicSiteData,
    SeoData,
    ShowcaseCardData,
    ShowcaseDetailData,
    TestimonialData,
} from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    project: ShowcaseDetailData;
    more: ShowcaseCardData[];
    testimonial: TestimonialData | null;
    ctaHref: string;
}>();
</script>

<template>
    <SeoHead :seo="props.seo" :preload-image="props.project.image" />

    <article>
        <header class="bg-linen">
            <div class="container-site pt-10 pb-10">
                <PublicBreadcrumbs
                    :items="[
                        { label: 'Inicio', href: '/' },
                        { label: 'Inspiración', href: '/inspiracion' },
                        { label: props.project.title },
                    ]"
                />
                <div class="mt-8 grid gap-6 lg:grid-cols-12 lg:items-end">
                    <div class="lg:col-span-8">
                        <OriginBadge
                            :origin="props.project.origin"
                            :label="props.project.originLabel"
                            variant="soft"
                        />
                        <h1 class="mt-4 text-display-xl">
                            {{ props.project.title }}
                        </h1>
                    </div>
                    <div
                        class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground lg:col-span-4 lg:justify-end"
                    >
                        <Link
                            v-if="props.project.category?.href"
                            :href="props.project.category.href"
                            class="font-medium text-foreground underline-offset-4 hover:underline"
                        >
                            {{ props.project.category.name }}
                        </Link>
                        <span
                            v-if="props.project.locationLabel"
                            class="inline-flex items-center gap-1.5"
                            ><MapPin class="size-4" aria-hidden="true" />{{
                                props.project.locationLabel
                            }}</span
                        >
                    </div>
                </div>
            </div>
            <div class="container-site pb-12">
                <div
                    class="relative aspect-[16/9] overflow-hidden rounded-[2rem] bg-stone"
                >
                    <ResponsiveImage
                        v-if="props.project.image"
                        :image="props.project.image"
                        priority
                        sizes="(min-width: 1200px) 1200px, 100vw"
                        class="absolute inset-0"
                    />
                    <div
                        v-else
                        class="absolute inset-0 grid place-items-center"
                    >
                        <FinishSwatches class="w-1/2" />
                    </div>
                </div>
                <p
                    v-if="props.project.origin === 'render'"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    Este es un render conceptual: muestra una propuesta de
                    diseño, no un trabajo entregado.
                </p>
            </div>
        </header>

        <section class="section-y" aria-label="Historia del proyecto">
            <div class="container-site grid gap-12 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <p
                        v-if="props.project.summary"
                        class="text-lead text-foreground"
                    >
                        {{ props.project.summary }}
                    </p>
                </div>
                <div class="lg:col-span-8">
                    <RichText
                        v-if="props.project.story"
                        :html="props.project.story"
                    />
                    <figure
                        v-if="props.testimonial"
                        class="mt-12 rounded-2xl bg-primary-soft p-8"
                    >
                        <blockquote
                            class="font-display text-xl leading-relaxed"
                        >
                            “{{ props.testimonial.quote }}”
                        </blockquote>
                        <figcaption class="mt-4 text-sm text-muted-foreground">
                            {{ props.testimonial.authorName
                            }}<span v-if="props.testimonial.authorContext">
                                · {{ props.testimonial.authorContext }}</span
                            >
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <section
            v-if="props.project.gallery.length"
            class="pb-16 sm:pb-24"
            aria-labelledby="galeria-proyecto"
        >
            <div class="container-site">
                <h2 id="galeria-proyecto" class="mb-8 text-display-md">
                    Galería
                </h2>
                <GalleryLightbox
                    :images="props.project.gallery"
                    :title="props.project.title"
                />
            </div>
        </section>
    </article>

    <section
        v-if="props.more.length"
        class="bg-stone/60 section-y"
        aria-labelledby="mas-proyectos"
    >
        <div class="container-site">
            <SectionHeading
                id="mas-proyectos"
                eyebrow="Inspiración"
                title="Más proyectos"
            />
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <ShowcaseCard
                    v-for="item in props.more"
                    :key="item.id"
                    :project="item"
                />
            </div>
        </div>
    </section>

    <div class="pt-16 sm:pt-24">
        <CtaBand :href="props.ctaHref" />
    </div>
</template>
