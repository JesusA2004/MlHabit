<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sofa } from '@lucide/vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import { Button } from '@/components/ui/button';
import CtaBand from '@/components/web/public/CtaBand.vue';
import FaqAccordion from '@/components/web/public/FaqAccordion.vue';
import ProductCard from '@/components/web/public/ProductCard.vue';
import PublicBreadcrumbs from '@/components/web/public/PublicBreadcrumbs.vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import SectionHeading from '@/components/web/public/SectionHeading.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import ShowcaseCard from '@/components/web/public/ShowcaseCard.vue';
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
</script>

<template>
    <SeoHead :seo="props.seo" :preload-image="props.category.image" />

    <header class="bg-linen">
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
                <h1 class="text-display-xl">{{ props.category.name }}</h1>
                <p
                    v-if="props.category.tagline"
                    class="mt-5 text-lead text-foreground/80"
                >
                    {{ props.category.tagline }}
                </p>
                <p
                    v-if="props.category.description"
                    class="mt-4 max-w-prose whitespace-pre-line text-muted-foreground"
                >
                    {{ props.category.description }}
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Button as-child size="lg" class="h-12 rounded-full px-7">
                        <Link :href="requestHref"
                            >Solicita una asesoría
                            <ArrowRight class="size-4" aria-hidden="true"
                        /></Link>
                    </Button>
                    <Button
                        v-if="props.products.length"
                        as-child
                        size="lg"
                        variant="outline"
                        class="h-12 rounded-full px-7"
                    >
                        <a href="#productos">Ver modelos</a>
                    </Button>
                </div>
            </div>
            <div class="lg:col-span-6">
                <div
                    class="relative aspect-[4/3] overflow-hidden rounded-[2rem] bg-stone"
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
                        class="absolute inset-0 grid place-items-center"
                    >
                        <FinishSwatches class="w-2/3" />
                    </div>
                </div>
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
            >
                <ProductCard
                    v-for="(product, i) in props.products"
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
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
    >
        <div class="container-site grid gap-10 lg:grid-cols-12">
            <SectionHeading
                id="faq-categoria-title"
                class="lg:col-span-4"
                eyebrow="Preguntas frecuentes"
                :title="`Sobre ${props.category.name.toLowerCase()}`"
            />
            <div class="lg:col-span-8"><FaqAccordion :faqs="props.faqs" /></div>
        </div>
    </section>

    <CtaBand :href="requestHref" />
</template>
