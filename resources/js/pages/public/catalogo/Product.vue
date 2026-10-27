<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check, ZoomIn } from '@lucide/vue';
import { computed, ref } from 'vue';
import Money from '@/components/ds/Money.vue';
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
import { featureIcon, ICON_STROKE } from '@/components/web/visual/icons';
import { illustrationFor } from '@/components/web/visual/illustrations';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import NextStepTeaser from '@/components/web/visual/journey/NextStepTeaser.vue';
import SplitText from '@/components/web/visual/SplitText.vue';
import type {
    EditorialItem,
    FaqData,
    FinishData,
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

const illustration = computed(() =>
    illustrationFor(props.product.category.slug, props.product.category.name),
);

/* ── Acabados: muestra activa ──────────────────────────────────── */
const activeFinishId = ref<number | null>(
    props.product.finishes[0]?.id ?? null,
);
const activeFinish = computed<FinishData | null>(
    () =>
        props.product.finishes.find((f) => f.id === activeFinishId.value) ??
        null,
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
        <header
            class="relative isolate bg-linen"
            data-journey="espacio"
            :data-journey-cta-href="props.ctaHref"
        >
            <div
                class="tex-blueprint pointer-events-none absolute inset-0 -z-10 [mask-image:linear-gradient(to_bottom,#000,transparent)]"
                aria-hidden="true"
            />
            <div
                class="container-site grid gap-10 pt-10 pb-12 lg:grid-cols-12 lg:gap-14 lg:pb-16"
            >
                <div class="lg:col-span-7">
                    <div class="lg:sticky lg:top-24">
                        <div
                            class="group relative aspect-[4/3] cursor-zoom-in overflow-hidden rounded-[2rem] bg-stone"
                            :data-motion="
                                props.product.image ? 'settle' : undefined
                            "
                            :data-hover-zoom="
                                props.product.image ? '' : undefined
                            "
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
                                class="tex-blueprint absolute inset-0 grid place-items-center bg-card p-10"
                            >
                                <LineIllustration
                                    :name="illustration"
                                    class="w-full max-w-md text-foreground/75"
                                />
                            </div>
                            <OriginBadge
                                v-if="props.product.image"
                                :origin="props.product.image.origin"
                                :label="props.product.image.originLabel"
                                class="absolute top-4 left-4"
                            />
                            <span
                                v-if="props.product.image"
                                class="pointer-events-none absolute right-4 bottom-4 hidden items-center gap-1.5 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-espresso shadow-sm backdrop-blur transition-opacity duration-300 group-hover:opacity-0 [@media(hover:hover)]:inline-flex"
                                aria-hidden="true"
                            >
                                <ZoomIn
                                    class="size-3.5"
                                    :stroke-width="ICON_STROKE"
                                />
                                Pasa el cursor para ver el detalle
                            </span>
                        </div>
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
                    <SplitText
                        as="h1"
                        class="block text-display-lg"
                        :text="props.product.name"
                    />
                    <p
                        v-if="props.product.summary"
                        class="mt-5 text-lead text-foreground/80"
                        data-motion="fade-up"
                        data-motion-delay="150"
                    >
                        {{ props.product.summary }}
                    </p>

                    <div
                        v-if="props.product.price"
                        class="mt-6 rounded-xl border border-border bg-card p-4"
                        data-motion="fade-up"
                        data-motion-delay="220"
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

                    <div
                        v-if="props.product.finishes.length"
                        class="mt-8"
                        data-motion="fade-up"
                        data-motion-delay="280"
                    >
                        <h2 id="acabados-title" class="text-sm font-semibold">
                            Acabados disponibles
                        </h2>
                        <div
                            class="mt-3 flex flex-wrap gap-2"
                            role="group"
                            aria-labelledby="acabados-title"
                        >
                            <button
                                v-for="finish in props.product.finishes"
                                :key="finish.id"
                                type="button"
                                :aria-pressed="finish.id === activeFinishId"
                                :class="[
                                    'group flex items-center gap-2 rounded-full border bg-card py-1 pr-3 pl-1 text-sm transition-[border-color,box-shadow,translate] duration-300 hover:-translate-y-0.5',
                                    finish.id === activeFinishId
                                        ? 'border-primary shadow-sm ring-1 ring-primary'
                                        : 'border-border hover:border-foreground/30',
                                ]"
                                @click="activeFinishId = finish.id"
                            >
                                <span
                                    class="relative size-7 overflow-hidden rounded-full ring-1 ring-black/10 transition-transform duration-300 group-hover:scale-110"
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
                                    <span
                                        v-if="finish.id === activeFinishId"
                                        class="check-in absolute inset-0 grid place-items-center bg-black/25 text-white"
                                    >
                                        <Check
                                            class="size-3.5"
                                            :stroke-width="2.5"
                                        />
                                    </span>
                                </span>
                                {{ finish.name }}
                            </button>
                        </div>
                        <Transition
                            mode="out-in"
                            enter-active-class="transition duration-300 ease-out"
                            enter-from-class="opacity-0 translate-y-1"
                            leave-active-class="transition duration-150 ease-in"
                            leave-to-class="opacity-0"
                        >
                            <div
                                v-if="activeFinish"
                                :key="activeFinish.id"
                                class="mt-4 flex items-center gap-4 rounded-xl border border-border bg-card p-3"
                                aria-live="polite"
                            >
                                <span
                                    class="tex-grain relative size-16 shrink-0 overflow-hidden rounded-lg ring-1 ring-black/10"
                                    :style="
                                        activeFinish.colorHex
                                            ? {
                                                  backgroundColor:
                                                      activeFinish.colorHex,
                                              }
                                            : undefined
                                    "
                                    aria-hidden="true"
                                >
                                    <ResponsiveImage
                                        v-if="activeFinish.image"
                                        :image="activeFinish.image"
                                        sizes="64px"
                                        class="absolute inset-0"
                                    />
                                </span>
                                <span class="min-w-0 text-sm">
                                    <span class="block font-medium">{{
                                        activeFinish.name
                                    }}</span>
                                    <span
                                        v-if="activeFinish.material"
                                        class="block text-muted-foreground"
                                        >{{ activeFinish.material }}</span
                                    >
                                    <span
                                        v-if="activeFinish.description"
                                        class="mt-0.5 line-clamp-2 block text-xs text-muted-foreground"
                                        >{{ activeFinish.description }}</span
                                    >
                                </span>
                            </div>
                        </Transition>
                    </div>

                    <div
                        class="mt-8 hidden lg:block"
                        data-motion="fade-up"
                        data-motion-delay="340"
                    >
                        <Link
                            :href="props.ctaHref"
                            data-magnetic="0.15"
                            class="btn-nudge btn-sweep inline-flex h-13 w-full items-center justify-center gap-2 rounded-full bg-primary text-base font-medium text-primary-foreground shadow-sm"
                        >
                            Solicita una asesoría
                            <ArrowRight
                                class="nudge size-4"
                                aria-hidden="true"
                            />
                        </Link>
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

        <section
            :class="['section-y', gallery.length && 'pt-0']"
            aria-label="Descripción"
        >
            <div class="container-site grid gap-12 lg:grid-cols-12">
                <div class="lg:col-span-7" data-motion="fade-up">
                    <RichText
                        v-if="props.product.description"
                        :html="props.product.description"
                    />
                    <dl
                        v-if="props.product.features.length"
                        class="mt-10 divide-y divide-border border-y border-border"
                        data-motion-stagger="70"
                        data-motion="fade-up"
                    >
                        <div
                            v-for="(feature, i) in props.product.features"
                            :key="i"
                            class="group grid gap-1 py-4 sm:grid-cols-[16rem_1fr] sm:gap-6"
                        >
                            <dt class="flex items-center gap-3 font-medium">
                                <span
                                    class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary transition-[background-color,color,rotate] duration-300 group-hover:-rotate-6 group-hover:bg-primary group-hover:text-primary-foreground"
                                    aria-hidden="true"
                                >
                                    <component
                                        :is="
                                            featureIcon(
                                                `${feature.title} ${feature.body ?? ''}`,
                                            )
                                        "
                                        class="size-[18px]"
                                        :stroke-width="ICON_STROKE"
                                    />
                                </span>
                                {{ feature.title }}
                            </dt>
                            <dd class="text-muted-foreground sm:pt-1.5">
                                {{ feature.body ?? '' }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <aside
                    v-if="props.product.benefits.length"
                    class="lg:col-span-5"
                >
                    <div
                        class="relative overflow-hidden rounded-2xl bg-primary-soft p-6 sm:p-8 lg:sticky lg:top-24"
                        data-motion="slide-left"
                    >
                        <LineIllustration
                            name="toolbox"
                            :animate="false"
                            class="pointer-events-none absolute -right-6 -bottom-4 w-40 text-primary/25"
                        />
                        <h2
                            class="font-display text-xl font-semibold tracking-tight"
                        >
                            Lo que ganas
                        </h2>
                        <ul
                            class="relative mt-5 grid gap-4"
                            data-motion-stagger="90"
                            data-motion="fade-up"
                        >
                            <li
                                v-for="(benefit, i) in props.product.benefits"
                                :key="i"
                                class="flex gap-3"
                            >
                                <span
                                    class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground"
                                    aria-hidden="true"
                                >
                                    <Check
                                        class="size-3.5"
                                        :stroke-width="2.25"
                                    />
                                </span>
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
        class="relative isolate overflow-hidden bg-espresso section-y text-espresso-foreground"
        aria-labelledby="proceso-producto-title"
        data-journey="proceso"
        data-spotlight
    >
        <div
            class="tex-blueprint-dark pointer-events-none absolute inset-0 -z-10 opacity-60"
            aria-hidden="true"
        />
        <div class="container-site">
            <SectionHeading
                id="proceso-producto-title"
                tone="dark"
                eyebrow="Cómo funciona"
                title="De la idea a tu casa"
            />
            <ol
                class="mt-12 grid gap-px overflow-hidden rounded-2xl bg-white/10 sm:grid-cols-2 lg:grid-cols-4"
                data-motion-stagger="110"
                data-motion="fade-up"
            >
                <li
                    v-for="(step, i) in props.process"
                    :key="i"
                    :class="[
                        'group relative bg-espresso p-6 transition-colors duration-500 hover:bg-[#342b26]',
                        stepClass(i, props.process.length),
                    ]"
                >
                    <span
                        class="font-display text-5xl font-semibold text-oak/30 tabular-nums transition-colors duration-500 group-hover:text-oak"
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
                    <span
                        class="absolute inset-x-6 bottom-0 h-px origin-left scale-x-0 bg-oak transition-transform duration-700 ease-out group-hover:scale-x-100"
                        aria-hidden="true"
                    />
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
            <div class="lg:col-span-8" data-motion="fade-up">
                <FaqAccordion :faqs="props.faqs" />
            </div>
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
                data-motion-stagger="90"
                data-motion="fade-up"
            >
                <ProductCard
                    v-for="item in props.related"
                    :key="item.id"
                    :product="item"
                />
            </div>
        </div>
    </section>

    <NextStepTeaser
        step="agenda"
        :title="`Pide tu propuesta de ${props.product.name}`"
        body="Un asesor revisa tus medidas y acabados y te explica los siguientes pasos, sin compromiso."
        :href="props.ctaHref"
        illustration="tape"
    />

    <div class="h-24 lg:hidden" aria-hidden="true" />
    <div
        data-journey-hide
        class="mobile-cta fixed inset-x-0 bottom-0 z-30 border-t border-border bg-background/95 px-4 pt-3 pb-safe backdrop-blur lg:hidden"
    >
        <Link
            :href="props.ctaHref"
            class="btn-nudge mb-3 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-primary text-base font-medium text-primary-foreground"
        >
            Solicita una asesoría
            <ArrowRight class="nudge size-4" aria-hidden="true" />
        </Link>
    </div>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .mobile-cta {
        animation: cta-rise 600ms var(--motion-ease, ease-out) 400ms both;
    }
}

@keyframes cta-rise {
    from {
        transform: translate3d(0, 100%, 0);
    }
}
</style>
