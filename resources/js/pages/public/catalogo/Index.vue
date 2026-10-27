<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Factory,
    LayoutGrid,
    PencilRuler,
    SwatchBook,
    Wrench,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import { Button } from '@/components/ui/button';
import CategoryTile from '@/components/web/public/CategoryTile.vue';
import CtaBand from '@/components/web/public/CtaBand.vue';
import PageIntro from '@/components/web/public/PageIntro.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import IconBadge from '@/components/web/visual/IconBadge.vue';
import { categoryIcon } from '@/components/web/visual/icons';
import { illustrationFor } from '@/components/web/visual/illustrations';
import NextStepTeaser from '@/components/web/visual/journey/NextStepTeaser.vue';
import Marquee from '@/components/web/visual/Marquee.vue';
import SwatchFan from '@/components/web/visual/SwatchFan.vue';
import type { CategoryCardData, PublicSiteData, SeoData } from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    categories: CategoryCardData[];
}>();

const marquee = computed(() =>
    props.categories.map((c) => ({
        label: c.name,
        href: c.href,
        icon: categoryIcon(c.slug, c.name),
    })),
);

const first = computed(() => props.categories[0] ?? null);

const promises = [
    {
        icon: PencilRuler,
        title: 'Diseño a la medida',
        body: 'Partimos de las medidas reales de tu espacio, no de un módulo de catálogo.',
    },
    {
        icon: SwatchBook,
        title: 'Acabados a elegir',
        body: 'Combinas colores, texturas y herrajes con tu asesor.',
    },
    {
        icon: Factory,
        title: 'Fabricación propia',
        body: 'Fabricamos cada pieza para tu proyecto.',
    },
    {
        icon: Wrench,
        title: 'Instalación profesional',
        body: 'Nuestro equipo instala y deja todo listo para usar.',
    },
];
</script>

<template>
    <SeoHead :seo="props.seo" />

    <PageIntro
        eyebrow="Catálogo"
        title="Muebles a la medida para cada espacio"
        lead="Explora nuestras líneas. Cada proyecto se diseña con las medidas reales de tu casa y los acabados que elijas."
        :breadcrumbs="[{ label: 'Inicio', href: '/' }, { label: 'Catálogo' }]"
        :visual="first ? illustrationFor(first.slug, first.name) : 'blueprint'"
        journey="espacio"
    />

    <div
        v-if="marquee.length > 1"
        class="border-b border-border/70 bg-background py-4"
    >
        <Marquee :items="marquee" label="Líneas del catálogo" :duration="38" />
    </div>

    <section class="section-y" aria-label="Categorías del catálogo">
        <div class="container-site">
            <div
                v-if="props.categories.length"
                class="grid auto-rows-[minmax(18rem,auto)] gap-4 sm:grid-cols-2 lg:grid-cols-3"
                data-motion-stagger="90"
                data-motion="fade-up"
            >
                <CategoryTile
                    v-for="(category, i) in props.categories"
                    :key="category.id"
                    :category="category"
                    :index="i"
                    :large="i === 0"
                    :class="
                        i === 0 ? 'min-h-96 sm:col-span-2 lg:row-span-2' : ''
                    "
                />
            </div>
            <EmptyState
                v-else
                :icon="LayoutGrid"
                title="Estamos preparando nuestro catálogo"
                description="Muy pronto podrás ver aquí nuestras líneas de muebles. Mientras tanto, cuéntanos qué necesitas y te orientamos."
            >
                <Button as-child
                    ><Link href="/solicitar"
                        >Solicita una asesoría</Link
                    ></Button
                >
            </EmptyState>
        </div>
    </section>

    <section
        class="border-y border-border/70 bg-linen py-16 sm:py-20"
        aria-labelledby="promesas-title"
    >
        <div class="container-site grid gap-12 lg:grid-cols-12 lg:items-center">
            <div class="lg:col-span-4" data-motion="fade-up">
                <h2 id="promesas-title" class="text-display-md">
                    Todo el catálogo se adapta a ti
                </h2>
                <p class="mt-3 text-muted-foreground">
                    Los modelos son un punto de partida: medidas, acabados y
                    distribución se ajustan a tu casa.
                </p>
                <SwatchFan class="mt-8 hidden w-44 lg:block" />
            </div>
            <ul
                class="grid gap-4 sm:grid-cols-2 lg:col-span-8"
                data-motion-stagger="90"
                data-motion="fade-up"
            >
                <li
                    v-for="item in promises"
                    :key="item.title"
                    class="group rounded-2xl border border-border bg-card p-6 transition-[translate,box-shadow,border-color] duration-500 ease-out hover:-translate-y-1 hover:border-primary/30 hover:shadow-md"
                >
                    <IconBadge :icon="item.icon" />
                    <h3
                        class="mt-5 font-display text-lg font-semibold tracking-tight"
                    >
                        {{ item.title }}
                    </h3>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        {{ item.body }}
                    </p>
                </li>
            </ul>
        </div>
    </section>

    <NextStepTeaser
        v-if="first"
        class="pt-16 sm:pt-24"
        step="espacio"
        :title="`Empieza por ${first.name.toLowerCase()}`"
        :body="first.tagline"
        :href="first.href"
        :illustration="illustrationFor(first.slug, first.name)"
    />
    <NextStepTeaser
        v-else
        class="pt-16 sm:pt-24"
        step="inspirate"
        title="Mira proyectos y propuestas"
        href="/inspiracion"
        illustration="kitchen"
    />

    <CtaBand />
</template>
