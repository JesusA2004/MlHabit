<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check } from '@lucide/vue';
import { computed } from 'vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import Money from '@/components/ds/Money.vue';
import { Button } from '@/components/ui/button';
import FaqAccordion from '@/components/web/public/FaqAccordion.vue';
import GalleryLightbox from '@/components/web/public/GalleryLightbox.vue';
import OriginBadge from '@/components/web/public/OriginBadge.vue';
import ProductCard from '@/components/web/public/ProductCard.vue';
import PublicBreadcrumbs from '@/components/web/public/PublicBreadcrumbs.vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import RichText from '@/components/web/public/RichText.vue';
import SectionHeading from '@/components/web/public/SectionHeading.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import ShowcaseCard from '@/components/web/public/ShowcaseCard.vue';
import type {
    EditorialItem,
    FaqData,
    ProductCardData,
    ProductDetailData,
    PublicSiteData,
    SeoData,
    ShowcaseCardData,
} from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    product: ProductDetailData;
    related: ProductCardData[];
    projects: ShowcaseCardData[];
    faqs: FaqData[];
    process: EditorialItem[];
    ctaHref: string;
}>();

/** La portada ya se muestra arriba: no repetirla en la galería. */
const gallery = computed(() =>
    props.product.gallery.filter(
        (image) => image.src !== props.product.image?.src,
    ),
);

/** Cierra la retícula del proceso sin huecos (el último paso se estira). */
function stepClass(i: number, total: number): string {
    if (i !== total - 1) {
        return '';
    }

    const lgSpan = ['', 'lg:col-span-4', 'lg:col-span-3', 'lg:col-span-2'][
        total % 4
    ];

    return [
        total % 2 === 1 ? 'sm:col-span-2' : '',
        lgSpan || (total % 2 === 1 ? 'lg:col-span-1' : ''),
    ].join(' ');
}
</script>

<template>
    <SeoHead :seo="props.seo" :preload-image="props.product.image" />

    <article>
        <header class="bg-linen">
            <div
                class="container-site grid gap-10 pt-10 pb-12 lg:grid-cols-12 lg:gap-14 lg:pb-16"
            >
                <div class="lg:col-span-7">
                    <div
                        class="relative aspect-[4/3] overflow-hidden rounded-[2rem] bg-stone"
                    >
                        <ResponsiveImage
                            v-if="props.product.image"
                            :image="props.product.image"
                            priority
                            sizes="(min-width: 1024px) 58vw, 100vw"
                            class="absolute inset-0"
                        />
                        <div
                            v-else
                            class="absolute inset-0 grid place-items-center"
                        >
                            <FinishSwatches class="w-2/3" />
                        </div>
                        <OriginBadge
                            v-if="props.product.image"
                            :origin="props.product.image.origin"
                            :label="props.product.image.originLabel"
                            class="absolute top-4 left-4"
                        />
                    </div>
                </div>
                <div class="flex flex-col lg:col-span-5 lg:pt-4">
                    <PublicBreadcrumbs
                        :items="[
                            { label: 'Catálogo', href: '/catalogo' },
                            {
                                label: props.product.category.name,
                                href: `/catalogo/${props.product.category.slug}`,
                            },
                            { label: props.product.name },
                        ]"
                        class="mb-6"
                    />
                    <h1 class="text-display-lg">{{ props.product.name }}</h1>
                    <p
                        v-if="props.product.summary"
                        class="mt-5 text-lead text-foreground/80"
                    >
                        {{ props.product.summary }}
                    </p>

                    <div
                        v-if="props.product.price"
                        class="mt-6 rounded-xl border border-border bg-card p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            Precio de referencia
                        </p>
                        <p
                            class="mt-1 font-display text-2xl font-semibold tracking-tight"
                        >
                            Desde
                            <Money
                                :value="props.product.price.from"
                                :decimals="false"
                            />
                            <template v-if="props.product.price.to">
                                a
                                <Money
                                    :value="props.product.price.to"
                                    :decimals="false"
                            /></template>
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            El precio final depende de las medidas, materiales y
                            acabados de tu proyecto.
                        </p>
                    </div>

                    <div v-if="props.product.finishes.length" class="mt-8">
                        <h2 class="text-sm font-semibold">
                            Acabados disponibles
                        </h2>
                        <ul class="mt-3 flex flex-wrap gap-3">
                            <li
                                v-for="finish in props.product.finishes"
                                :key="finish.id"
                                class="flex items-center gap-2 rounded-full border border-border bg-card py-1 pr-3 pl-1"
                            >
                                <span
                                    class="size-7 overflow-hidden rounded-full ring-1 ring-black/10"
                                    :style="
                                        finish.colorHex
                                            ? {
                                                  backgroundColor:
                                                      finish.colorHex,
                                              }
                                            : undefined
                                    "
                                    aria-hidden="true"
                                >
                                    <ResponsiveImage
                                        v-if="finish.image"
                                        :image="finish.image"
                                        sizes="28px"
                                    />
                                </span>
                                <span class="text-sm"
                                    >{{ finish.name
                                    }}<span
                                        v-if="finish.material"
                                        class="text-muted-foreground"
                                    >
                                        · {{ finish.material }}</span
                                    ></span
                                >
                            </li>
                        </ul>
                    </div>

                    <div class="mt-8 hidden lg:block">
                        <Button
                            as-child
                            size="lg"
                            class="h-12 w-full rounded-full text-base"
                        >
                            <Link :href="props.ctaHref"
                                >Solicita una asesoría
                                <ArrowRight class="size-4" aria-hidden="true"
                            /></Link>
                        </Button>
                    </div>
                </div>
            </div>
        </header>

        <section
            v-if="gallery.length"
            class="section-y"
            aria-labelledby="galeria-title"
        >
            <div class="container-site">
                <h2 id="galeria-title" class="sr-only">Galería</h2>
                <GalleryLightbox
                    :images="gallery"
                    :title="props.product.name"
                />
            </div>
        </section>

        <section class="section-y pt-0" aria-label="Descripción">
            <div class="container-site grid gap-12 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <RichText
                        v-if="props.product.description"
                        :html="props.product.description"
                    />
                    <dl
                        v-if="props.product.features.length"
                        class="mt-10 divide-y divide-border border-y border-border"
                    >
                        <div
                            v-for="(feature, i) in props.product.features"
                            :key="i"
                            class="grid gap-1 py-4 sm:grid-cols-[14rem_1fr] sm:gap-6"
                        >
                            <dt class="font-medium">{{ feature.title }}</dt>
                            <dd class="text-muted-foreground">
                                {{ feature.body ?? '' }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <aside
                    v-if="props.product.benefits.length"
                    class="lg:col-span-5"
                >
                    <div class="rounded-2xl bg-primary-soft p-6 sm:p-8">
                        <h2
                            class="font-display text-xl font-semibold tracking-tight"
                        >
                            Lo que ganas
                        </h2>
                        <ul class="mt-5 grid gap-4">
                            <li
                                v-for="(benefit, i) in props.product.benefits"
                                :key="i"
                                class="flex gap-3"
                            >
                                <Check
                                    class="mt-1 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <span>
                                    <span class="font-medium">{{
                                        benefit.title
                                    }}</span>
                                    <span
                                        v-if="benefit.body"
                                        class="mt-0.5 block text-sm text-muted-foreground"
                                        >{{ benefit.body }}</span
                                    >
                                </span>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </section>
    </article>

    <section
        v-if="props.process.length"
        class="bg-espresso section-y text-espresso-foreground"
        aria-labelledby="proceso-producto-title"
    >
        <div class="container-site">
            <SectionHeading
                id="proceso-producto-title"
                tone="dark"
                eyebrow="Cómo funciona"
                title="De la idea a tu casa"
            />
            <ol
                class="mt-12 grid gap-px overflow-hidden rounded-2xl bg-white/10 sm:grid-cols-2 lg:grid-cols-4"
            >
                <li
                    v-for="(step, i) in props.process"
                    :key="i"
                    :class="[
                        'bg-espresso p-6',
                        stepClass(i, props.process.length),
                    ]"
                >
                    <span
                        class="font-display text-sm font-semibold text-oak tabular-nums"
                        >{{ String(i + 1).padStart(2, '0') }}</span
                    >
                    <h3 class="mt-3 font-display text-lg font-semibold">
                        {{ step.title }}
                    </h3>
                    <p
                        v-if="step.body"
                        class="mt-2 text-sm text-espresso-foreground/70"
                    >
                        {{ step.body }}
                    </p>
                </li>
            </ol>
        </div>
    </section>

    <section
        v-if="props.projects.length"
        class="section-y"
        aria-labelledby="inspiracion-producto-title"
    >
        <div class="container-site">
            <SectionHeading
                id="inspiracion-producto-title"
                eyebrow="Inspiración"
                title="Ideas relacionadas"
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
        class="bg-linen section-y"
        aria-labelledby="faq-producto-title"
    >
        <div class="container-site grid gap-10 lg:grid-cols-12">
            <SectionHeading
                id="faq-producto-title"
                class="lg:col-span-4"
                eyebrow="Preguntas frecuentes"
                title="Dudas comunes"
            />
            <div class="lg:col-span-8"><FaqAccordion :faqs="props.faqs" /></div>
        </div>
    </section>

    <section
        v-if="props.related.length"
        class="section-y"
        aria-labelledby="relacionados-title"
    >
        <div class="container-site">
            <SectionHeading
                id="relacionados-title"
                eyebrow="También te puede interesar"
                :title="`Más en ${props.product.category.name}`"
            />
            <div
                class="mt-10 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3"
            >
                <ProductCard
                    v-for="item in props.related"
                    :key="item.id"
                    :product="item"
                />
            </div>
        </div>
    </section>

    <div class="h-24 lg:hidden" aria-hidden="true" />
    <div
        class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-background/95 px-4 pt-3 pb-safe backdrop-blur lg:hidden"
    >
        <Button
            as-child
            size="lg"
            class="mb-3 h-12 w-full rounded-full text-base"
        >
            <Link :href="props.ctaHref"
                >Solicita una asesoría
                <ArrowRight class="size-4" aria-hidden="true"
            /></Link>
        </Button>
    </div>
</template>
