<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import { Button } from '@/components/ui/button';
import CategoryTile from '@/components/web/public/CategoryTile.vue';
import CtaBand from '@/components/web/public/CtaBand.vue';
import PageIntro from '@/components/web/public/PageIntro.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import type { CategoryCardData, PublicSiteData, SeoData } from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    categories: CategoryCardData[];
}>();
</script>

<template>
    <SeoHead :seo="props.seo" />

    <PageIntro
        eyebrow="Catálogo"
        title="Muebles a la medida para cada espacio"
        lead="Explora nuestras líneas. Cada proyecto se diseña con las medidas reales de tu casa y los acabados que elijas."
        :breadcrumbs="[{ label: 'Inicio', href: '/' }, { label: 'Catálogo' }]"
    />

    <section class="section-y" aria-label="Categorías del catálogo">
        <div class="container-site">
            <div
                v-if="props.categories.length"
                class="grid auto-rows-[minmax(18rem,auto)] gap-4 sm:grid-cols-2 lg:grid-cols-3"
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

    <CtaBand />
</template>
