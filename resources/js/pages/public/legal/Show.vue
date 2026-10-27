<script setup lang="ts">
import DateText from '@/components/ds/DateText.vue';
import PageIntro from '@/components/web/public/PageIntro.vue';
import RichText from '@/components/web/public/RichText.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import type { PublicSiteData, SeoData } from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    page: {
        title: string;
        slug: string;
        summary: string | null;
        body: string;
        version: number;
        publishedAt: string | null;
    };
}>();
</script>

<template>
    <SeoHead :seo="props.seo" />

    <PageIntro
        :title="props.page.title"
        :lead="props.page.summary"
        :breadcrumbs="[
            { label: 'Inicio', href: '/' },
            { label: props.page.title },
        ]"
    />

    <section class="section-y">
        <div class="container-site grid gap-10 lg:grid-cols-12">
            <aside class="text-sm text-muted-foreground lg:col-span-3">
                <p>Versión {{ props.page.version }}</p>
                <p v-if="props.page.publishedAt">
                    Actualizado el <DateText :value="props.page.publishedAt" />
                </p>
            </aside>
            <RichText :html="props.page.body" class="lg:col-span-9" />
        </div>
    </section>
</template>
