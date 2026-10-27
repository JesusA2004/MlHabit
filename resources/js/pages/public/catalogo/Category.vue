<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Factory, PencilRuler, Sofa, Wrench } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import { Button } from '@/components/ui/button';
import CtaBand from '@/components/web/public/CtaBand.vue';
import FaqAccordion from '@/components/web/public/FaqAccordion.vue';
import ProductCard from '@/components/web/public/ProductCard.vue';
import PublicBreadcrumbs from '@/components/web/public/PublicBreadcrumbs.vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import SectionHeading from '@/components/web/public/SectionHeading.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import ShowcaseCard from '@/components/web/public/ShowcaseCard.vue';
import { categoryIcon, ICON_STROKE } from '@/components/web/visual/icons';
import { illustrationFor } from '@/components/web/visual/illustrations';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import NextStepTeaser from '@/components/web/visual/journey/NextStepTeaser.vue';
import MaterialChips from '@/components/web/visual/MaterialChips.vue';
import SplitText from '@/components/web/visual/SplitText.vue';
import type {
    CategoryCardData,
    FaqData,
    ProductCardData,
    PublicSiteData,
    SeoData,
    ShowcaseCardData,
} from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    category: CategoryCardData & { description: string | null };
    products: ProductCardData[];
    projects: ShowcaseCardData[];
    faqs: FaqData[];
}>();

const requestHref = `/solicitar?categoria=${props.category.slug}`;

const icon = computed(() =>
    categoryIcon(props.category.slug, props.category.name),
);
const illustration = computed(() =>
    illustrationFor(props.category.slug, props.category.name),
);
const firstProduct = computed(() => props.products[0] ?? null);

const traits = [
    { icon: PencilRuler, label: 'Diseño a la medida' },
    { icon: Factory, label: 'Fabricación propia' },
    { icon: Wrench, label: 'Instalación profesional' },
];
</script>

<template>
    <SeoHead :seo="props.seo" :preload-image="props.category.image" />

    <header
        class="relative isolate overflow-hidden bg-linen"
        data-journey="espacio"
        :data-journey-cta-href="requestHref"
    >
        <div
            class="tex-blueprint pointer-events-none absolute inset-0 -z-10 [mask-image:radial-gradient(ellipse_at_20%_40%,#000_10%,transparent_65%)]"
            aria-hidden="true"
        />
        <div
            class="container-site grid gap-10 pt-10 pb-12 lg:grid-cols-12 lg:items-center lg:gap-14 lg:pb-16"
        >
            <div class="lg:col-span-6">
                <PublicBreadcrumbs
                    :items="[
                        { label: 'Inicio', href: '/' },
                        { label: 'Catálogo', href: '/catalogo' },
                        { label: props.category.name },
                    ]"
                    class="mb-8"
                />
                <span
                    class="mb-5 inline-grid size-12 place-items-center rounded-2xl bg-primary text-primary-foreground shadow-md"
                    data-motion="zoom-in"
                    aria-hidden="true"
                >
                    <component
                        :is="icon"
                        class="size-6"
                        :stroke-width="ICON_STROKE"
                    />
                </span>
                <SplitText
                    as="h1"
                    class="block text-display-xl"
                    :text="props.category.name"
                />
                <p
                    v-if="props.category.tagline"
                    class="mt-5 text-lead text-foreground/80"
                    data-motion="fade-up"
                    data-motion-delay="200"
                >
                    {{ props.category.tagline }}
                </p>
                <p
                    v-if="props.category.description"
                    class="mt-4 max-w-prose whitespace-pre-line text-muted-foreground"
                    data-motion="fade-up"
                    data-motion-delay="280"
                >
                    {{ props.category.description }}
                </p>
                <ul
                    class="mt-6 flex flex-wrap gap-2"
                    data-motion-stagger="80"
                    data-motion-delay="320"
                >
                    <li
                        v-for="trait in traits"
                        :key="trait.label"
                        class="inline-flex items-center gap-2 rounded-full border border-border bg-card/80 py-1.5 pr-3.5 pl-2 text-sm"
                    >
                        <component
                            :is="trait.icon"
                            class="size-4 text-primary"
                            :stroke-width="ICON_STROKE"
                            aria-hidden="true"
                        />
                        {{ trait.label }}
                    </li>
                </ul>
                <div
                    class="mt-8 flex flex-col gap-3 sm:flex-row"
                    data-motion="fade-up"
                    data-motion-delay="400"
                >
                    <Link
                        :href="requestHref"
                        data-magnetic="0.2"
                        class="btn-nudge btn-sweep inline-flex h-12 items-center justify-center gap-2 rounded-full bg-primary px-7 text-base font-medium text-primary-foreground shadow-sm"
                    >
                        Solicita una asesoría
                        <ArrowRight class="nudge size-4" aria-hidden="true" />
                    </Link>
                    <a
                        v-if="props.products.length"
                        href="#productos"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-border bg-card px-7 text-base font-medium transition-colors hover:border-foreground/40"
                    >
                        Ver {{ props.products.length }}
                        {{ props.products.length === 1 ? 'modelo' : 'modelos' }}
                    </a>
                </div>
            </div>
            <div class="relative lg:col-span-6">
                <div
                    class="relative aspect-[4/3] overflow-hidden rounded-[2rem] bg-stone [--mask-radius:2rem]"
                    :data-motion="props.category.image ? 'settle' : undefined"
                    :data-hover-zoom="props.category.image ? '' : undefined"
                >
                    <ResponsiveImage
                        v-if="props.category.image"
                        :image="props.category.image"
                        priority
                        sizes="(min-width: 1024px) 50vw, 100vw"
                        class="absolute inset-0"
                    />
                    <div
                        v-else
                        class="tex-blueprint absolute inset-0 grid place-items-center bg-card p-10"
                    >
                        <LineIllustration
                            :name="illustration"
                            class="w-full max-w-md text-foreground/75"
                            :delay="200"
                        />
                    </div>
                </div>
                <MaterialChips class="-inset-4 hidden sm:block" />
            </div>
        </div>
    </header>

    <section
        id="productos"
        class="scroll-mt-20 section-y"
        aria-labelledby="productos-title"
    >
        <div class="container-site">
            <SectionHeading
                id="productos-title"
                eyebrow="Modelos"
                :title="`${props.category.name} a la medida`"
            />
            <div
                v-if="props.products.length"
                class="mt-10 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3"
                data-motion-stagger="90"
                data-motion="fade-up"
            >
                <ProductCard
                    v-for="product in props.products"
                    :key="product.id"
                    :product="product"
                />
            </div>
            <EmptyState
                v-else
                class="mt-10"
                :icon="Sofa"
                title="Pronto verás aquí nuestros modelos"
                description="Cada proyecto se diseña a la medida. Cuéntanos qué tienes en mente y te ayudamos a definirlo."
            >
                <Button as-child
                    ><Link :href="requestHref"
                        >Solicita una asesoría</Link
                    ></Button
                >
            </EmptyState>
        </div>
    </section>

    <section
        v-if="props.projects.length"
        class="bg-stone/60 section-y"
        aria-labelledby="inspiracion-title"
    >
        <div class="container-site">
            <SectionHeading
                id="inspiracion-title"
                eyebrow="Inspiración"
                title="Proyectos y propuestas"
                subtitle="Cada imagen indica si es un proyecto entregado o un render conceptual."
            />
            <div
                class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                data-motion-stagger="90"
                data-motion="fade-up"
            >
                <ShowcaseCard
                    v-for="project in props.projects"
                    :key="project.id"
                    :project="project"
                />
            </div>
        </div>
    </section>

    <section
        v-if="props.faqs.length"
        class="section-y"
        aria-labelledby="faq-categoria-title"
        data-journey="proceso"
    >
        <div class="container-site grid gap-10 lg:grid-cols-12">
            <SectionHeading
                id="faq-categoria-title"
                class="lg:col-span-4"
                eyebrow="Preguntas frecuentes"
                :title="`Sobre ${props.category.name.toLowerCase()}`"
            />
            <div class="lg:col-span-8" data-motion="fade-up">
                <FaqAccordion :faqs="props.faqs" />
            </div>
        </div>
    </section>

    <NextStepTeaser
        v-if="firstProduct"
        step="espacio"
        :title="`Conoce el modelo ${firstProduct.name}`"
        :body="firstProduct.summary"
        :href="firstProduct.href"
        :illustration="illustration"
    />

    <CtaBand :href="requestHref" :illustration="illustration" />
</template>
