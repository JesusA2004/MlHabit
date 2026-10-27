<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import type { FaqData, HomeSection } from '@/types/web';
import FaqAccordion from '../FaqAccordion.vue';
import SectionHeading from '../SectionHeading.vue';
import { text } from './content';

/** Preguntas frecuentes en dos columnas: encabezado y acordeón. */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const faqs = computed(() => (props.section.items ?? []) as FaqData[]);
</script>

<template>
    <section aria-labelledby="faq-title" class="section-y">
        <div class="container-site grid gap-10 lg:grid-cols-12 lg:gap-16">
            <div class="lg:col-span-4">
                <SectionHeading
                    id="faq-title"
                    :eyebrow="text(c, 'eyebrow')"
                    :title="text(c, 'title')"
                    :subtitle="text(c, 'subtitle')"
                />
                <Link
                    v-if="text(c, 'cta_label')"
                    href="/preguntas-frecuentes"
                    class="group mt-8 inline-flex items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                >
                    {{ text(c, 'cta_label') }}
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    />
                </Link>
            </div>
            <div class="lg:col-span-8">
                <FaqAccordion :faqs="faqs" />
            </div>
        </div>
    </section>
</template>
