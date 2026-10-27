<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageCircleQuestion } from '@lucide/vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import { Button } from '@/components/ui/button';
import CtaBand from '@/components/web/public/CtaBand.vue';
import FaqAccordion from '@/components/web/public/FaqAccordion.vue';
import PageIntro from '@/components/web/public/PageIntro.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import type { FaqData, PublicSiteData, SeoData } from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    groups: { topic: string; label: string; items: FaqData[] }[];
}>();
</script>

<template>
    <SeoHead :seo="props.seo" />

    <PageIntro
        eyebrow="Ayuda"
        title="Preguntas frecuentes"
        lead="Respuestas claras sobre el proceso, la visita técnica, los pagos y la garantía."
        :breadcrumbs="[
            { label: 'Inicio', href: '/' },
            { label: 'Preguntas frecuentes' },
        ]"
    />

    <section class="section-y" aria-label="Preguntas por tema">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            <nav
                v-if="props.groups.length > 1"
                aria-label="Temas"
                class="lg:col-span-3"
            >
                <ul
                    class="-mx-4 scrollbar-none flex gap-2 overflow-x-auto px-4 lg:sticky lg:top-28 lg:mx-0 lg:flex-col lg:px-0"
                >
                    <li
                        v-for="group in props.groups"
                        :key="group.topic"
                        class="shrink-0"
                    >
                        <a
                            :href="`#tema-${group.topic}`"
                            class="block rounded-full border border-border px-4 py-1.5 text-sm hover:border-foreground/40 lg:rounded-lg lg:border-0 lg:px-3 lg:py-2 lg:hover:bg-stone"
                        >
                            {{ group.label }}
                        </a>
                    </li>
                </ul>
            </nav>
            <div
                :class="
                    props.groups.length > 1 ? 'lg:col-span-9' : 'lg:col-span-12'
                "
            >
                <div
                    v-for="group in props.groups"
                    :id="`tema-${group.topic}`"
                    :key="group.topic"
                    class="mb-14 scroll-mt-24 last:mb-0"
                >
                    <h2 class="mb-4 text-display-md">{{ group.label }}</h2>
                    <FaqAccordion :faqs="group.items" />
                </div>
                <EmptyState
                    v-if="!props.groups.length"
                    :icon="MessageCircleQuestion"
                    title="Pronto publicaremos las preguntas más comunes"
                    description="Mientras tanto, escríbenos y con gusto resolvemos tus dudas."
                >
                    <Button as-child
                        ><Link href="/solicitar"
                            >Solicita una asesoría</Link
                        ></Button
                    >
                </EmptyState>
            </div>
        </div>
    </section>

    <CtaBand
        title="¿No encontraste tu respuesta?"
        body="Cuéntanos tu caso y un asesor te orienta personalmente."
    />
</template>
