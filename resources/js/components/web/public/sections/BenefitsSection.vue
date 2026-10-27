<script setup lang="ts">
import { computed } from 'vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import type { EditorialItem, HomeSection } from '@/types/web';
import OriginBadge from '../OriginBadge.vue';
import ResponsiveImage from '../ResponsiveImage.vue';
import SectionHeading from '../SectionHeading.vue';
import { items, text } from './content';

/** Beneficios: imagen vertical (o muestras de acabados) y lista editorial. */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const benefits = computed(() => items<EditorialItem>(c.value, 'items'));
const image = computed(() => props.section.image ?? null);
</script>

<template>
    <section aria-labelledby="beneficios-title" class="section-y">
        <div
            class="container-site grid items-center gap-12 lg:grid-cols-12 lg:gap-16"
        >
            <div class="relative lg:col-span-5">
                <div
                    class="relative aspect-[4/5] overflow-hidden rounded-[2rem] bg-stone"
                >
                    <ResponsiveImage
                        v-if="image"
                        :image="image"
                        sizes="(min-width: 1024px) 40vw, 100vw"
                        class="absolute inset-0"
                    />
                    <div
                        v-else
                        class="absolute inset-0 grid place-items-center bg-gradient-to-br from-stone to-linen"
                    >
                        <FinishSwatches class="w-3/4" />
                    </div>
                    <OriginBadge
                        v-if="image"
                        :origin="image.origin"
                        :label="image.originLabel"
                        class="absolute bottom-4 left-4"
                    />
                </div>
                <div
                    class="absolute -right-4 -bottom-6 -z-10 hidden h-2/3 w-2/3 rounded-[2rem] bg-primary-soft sm:block"
                    aria-hidden="true"
                />
            </div>

            <div class="lg:col-span-7">
                <SectionHeading
                    id="beneficios-title"
                    :eyebrow="text(c, 'eyebrow')"
                    :title="text(c, 'title')"
                    :subtitle="text(c, 'subtitle')"
                />
                <dl class="mt-10 grid gap-x-10 gap-y-8 sm:grid-cols-2">
                    <div
                        v-for="(benefit, i) in benefits"
                        :key="i"
                        class="border-t border-border pt-5"
                    >
                        <dt
                            class="flex items-baseline gap-3 font-display text-lg font-semibold tracking-tight"
                        >
                            <span
                                class="text-sm text-oak-ink tabular-nums"
                                aria-hidden="true"
                                >{{ String(i + 1).padStart(2, '0') }}</span
                            >
                            {{ benefit.title }}
                        </dt>
                        <dd
                            v-if="benefit.body"
                            class="mt-2 text-muted-foreground"
                        >
                            {{ benefit.body }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
</template>
