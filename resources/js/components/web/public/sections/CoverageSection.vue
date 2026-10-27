<script setup lang="ts">
import { computed } from 'vue';
import type { HomeSection } from '@/types/web';
import CoverageChecker from '../CoverageChecker.vue';
import SectionHeading from '../SectionHeading.vue';
import { text } from './content';

/** Cobertura: texto del CMS y verificador de código postal. */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
</script>

<template>
    <section aria-labelledby="cobertura-title" class="section-y">
        <div class="container-site">
            <div
                class="relative overflow-hidden rounded-[2rem] bg-primary-soft px-6 py-12 sm:px-12 sm:py-16"
            >
                <svg
                    class="pointer-events-none absolute -top-10 -right-10 size-72 text-primary/10"
                    viewBox="0 0 200 200"
                    aria-hidden="true"
                >
                    <circle
                        cx="100"
                        cy="100"
                        r="96"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    />
                    <circle
                        cx="100"
                        cy="100"
                        r="64"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    />
                    <circle cx="100" cy="100" r="32" fill="currentColor" />
                </svg>
                <div
                    class="relative grid gap-10 lg:grid-cols-2 lg:items-center"
                >
                    <SectionHeading
                        id="cobertura-title"
                        :eyebrow="text(c, 'eyebrow')"
                        :title="text(c, 'title')"
                        :subtitle="text(c, 'body')"
                    />
                    <CoverageChecker
                        :cta-label="
                            text(c, 'cta_label') ||
                            'Revisa si llegamos a tu código postal'
                        "
                        :fallback="text(c, 'fallback')"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
