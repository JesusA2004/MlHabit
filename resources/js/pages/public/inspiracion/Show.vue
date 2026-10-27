<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, Quote } from '@lucide/vue';
import { computed } from 'vue';
import CtaBand from '@/components/web/public/CtaBand.vue';
import GalleryLightbox from '@/components/web/public/GalleryLightbox.vue';
import OriginBadge from '@/components/web/public/OriginBadge.vue';
import PublicBreadcrumbs from '@/components/web/public/PublicBreadcrumbs.vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import RichText from '@/components/web/public/RichText.vue';
import SectionHeading from '@/components/web/public/SectionHeading.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import ShowcaseCard from '@/components/web/public/ShowcaseCard.vue';
import BeforeAfter from '@/components/web/visual/BeforeAfter.vue';
import { categoryIcon, ICON_STROKE } from '@/components/web/visual/icons';
import { illustrationFor } from '@/components/web/visual/illustrations';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import NextStepTeaser from '@/components/web/visual/journey/NextStepTeaser.vue';
import SplitText from '@/components/web/visual/SplitText.vue';
import type {
    GalleryImage,
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

const illustration = computed(() =>
    illustrationFor(props.project.category?.slug, props.project.category?.name),
);

/**
 * Antes/después: solo si la galería trae una imagen cuyo pie empieza por
 * "Antes" y otra por "Después". Nunca se infiere de otra forma.
 */
function captionStarts(image: GalleryImage, word: RegExp): boolean {
    return word.test((image.caption ?? '').trim());
}

const beforeAfter = computed(() => {
    const before = props.project.gallery.find((i) =>
        captionStarts(i, /^antes\b/i),
    );
    const after = props.project.gallery.find((i) =>
        captionStarts(i, /^despu[eé]s\b/i),
    );

    return before && after ? { before, after } : null;
});

const nextHref = computed(() => props.project.category?.href ?? '/catalogo');
const nextTitle = computed(() =>
    props.project.category
        ? `Explora ${props.project.category.name.toLowerCase()} a la medida`
        : 'Elige el espacio que quieres renovar',
);
</script>

<template>
    <SeoHead :seo="props.seo" :preload-image="props.project.image" />

    <article>
        <header
            class="relative isolate overflow-hidden bg-linen"
            data-journey="inspirate"
        >
            <div
                class="tex-blueprint pointer-events-none absolute inset-x-0 top-0 -z-10 h-96 [mask-image:linear-gradient(to_bottom,#000,transparent)]"
                aria-hidden="true"
            />
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
                            data-motion="fade-in"
                        />
                        <SplitText
                            as="h1"
                            class="mt-4 block text-display-xl"
                            :text="props.project.title"
                        />
                    </div>
                    <div
                        class="flex flex-wrap gap-2 text-sm lg:col-span-4 lg:justify-end"
                        data-motion-stagger="90"
                        data-motion-delay="250"
                    >
                        <Link
                            v-if="props.project.category?.href"
                            :href="props.project.category.href"
                            class="group inline-flex h-10 items-center gap-2 rounded-full border border-border bg-card px-4 font-medium transition-colors hover:border-primary hover:text-primary"
                        >
                            <component
                                :is="
                                    categoryIcon(
                                        props.project.category.slug,
                                        props.project.category.name,
                                    )
                                "
                                class="size-4 transition-transform duration-300 group-hover:-rotate-12"
                                :stroke-width="ICON_STROKE"
                                aria-hidden="true"
                            />
                            {{ props.project.category.name }}
                        </Link>
                        <span
                            v-if="props.project.locationLabel"
                            class="inline-flex h-10 items-center gap-1.5 rounded-full border border-border bg-card px-4 text-muted-foreground"
                            ><MapPin
                                class="size-4"
                                :stroke-width="ICON_STROKE"
                                aria-hidden="true"
                            />{{ props.project.locationLabel }}</span
                        >
                    </div>
                </div>
            </div>
            <div class="container-site pb-12">
                <div
                    class="relative aspect-[16/9] overflow-hidden rounded-[2rem] bg-stone"
                    :data-motion="props.project.image ? 'settle' : undefined"
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
                        class="tex-blueprint absolute inset-0 grid place-items-center bg-card p-10"
                    >
                        <LineIllustration
                            :name="illustration"
                            class="w-full max-w-lg text-foreground/75"
                        />
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
                    <div class="lg:sticky lg:top-28" data-motion="fade-up">
                        <span
                            class="mb-5 block h-px w-12 bg-oak"
                            aria-hidden="true"
                        />
                        <p
                            v-if="props.project.summary"
                            class="text-lead text-foreground"
                        >
                            {{ props.project.summary }}
                        </p>
                    </div>
                </div>
                <div class="lg:col-span-8" data-motion="fade-up">
                    <RichText
                        v-if="props.project.story"
                        :html="props.project.story"
                    />
                    <figure
                        v-if="props.testimonial"
                        class="relative mt-12 overflow-hidden rounded-2xl bg-primary-soft p-8 sm:p-10"
                        data-motion="zoom-in"
                    >
                        <Quote
                            class="absolute -top-2 -right-2 size-28 text-primary/10"
                            :stroke-width="1"
                            aria-hidden="true"
                        />
                        <blockquote
                            class="relative font-display text-xl leading-relaxed sm:text-2xl"
                        >
                            “{{ props.testimonial.quote }}”
                        </blockquote>
                        <figcaption
                            class="mt-5 flex items-center gap-3 text-sm"
                        >
                            <span class="h-px w-8 bg-oak" aria-hidden="true" />
                            <span>
                                <span class="font-medium">{{
                                    props.testimonial.authorName
                                }}</span>
                                <span
                                    v-if="props.testimonial.authorContext"
                                    class="text-muted-foreground"
                                >
                                    ·
                                    {{ props.testimonial.authorContext }}</span
                                >
                            </span>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <section
            v-if="beforeAfter"
            class="pb-16 sm:pb-24"
            aria-labelledby="antes-despues"
        >
            <div class="container-site">
                <h2
                    id="antes-despues"
                    class="mb-8 text-display-md"
                    data-motion="fade-up"
                >
                    Antes y después
                </h2>
                <div data-motion="fade-up">
                    <BeforeAfter
                        :before="beforeAfter.before"
                        :after="beforeAfter.after"
                        :title="props.project.title"
                    />
                </div>
            </div>
        </section>

        <section
            v-if="props.project.gallery.length"
            class="pb-16 sm:pb-24"
            aria-labelledby="galeria-proyecto"
        >
            <div class="container-site">
                <h2
                    id="galeria-proyecto"
                    class="mb-8 text-display-md"
                    data-motion="fade-up"
                >
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
            <div
                class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                data-motion-stagger="90"
                data-motion="fade-up"
            >
                <ShowcaseCard
                    v-for="item in props.more"
                    :key="item.id"
                    :project="item"
                />
            </div>
        </div>
    </section>

    <NextStepTeaser
        class="pt-16 sm:pt-24"
        step="espacio"
        :title="nextTitle"
        body="Los modelos del catálogo son un punto de partida: todo se ajusta a tu casa."
        :href="nextHref"
        :illustration="illustration"
    />

    <CtaBand :href="props.ctaHref" :illustration="illustration" />
</template>
