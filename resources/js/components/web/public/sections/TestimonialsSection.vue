<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HomeSection, TestimonialData } from '@/types/web';
import ResponsiveImage from '../ResponsiveImage.vue';
import SectionHeading from '../SectionHeading.vue';
import { text } from './content';

/** Testimonios con consentimiento (la sección no se envía si no hay ninguno). */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const testimonials = computed(
    () => (props.section.items ?? []) as TestimonialData[],
);
</script>

<template>
    <section
        aria-labelledby="testimonios-title"
        class="overflow-hidden bg-stone/60 section-y"
    >
        <div class="container-site">
            <SectionHeading
                id="testimonios-title"
                :eyebrow="text(c, 'eyebrow')"
                :title="text(c, 'title')"
            />
        </div>
        <div class="container-site mt-12">
            <ul
                class="-mx-4 scrollbar-none flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0"
                aria-label="Testimonios"
            >
                <li
                    v-for="item in testimonials"
                    :key="item.id"
                    class="flex w-[85%] shrink-0 snap-start flex-col justify-between rounded-2xl bg-card p-7 shadow-xs ring-1 ring-border sm:w-[26rem]"
                >
                    <figure>
                        <span
                            class="font-display text-6xl leading-none text-oak"
                            aria-hidden="true"
                            >“</span
                        >
                        <blockquote
                            class="-mt-4 text-lg leading-relaxed text-foreground"
                        >
                            {{ item.quote }}
                        </blockquote>
                        <figcaption class="mt-6 flex items-center gap-3">
                            <span
                                v-if="item.image"
                                class="size-11 overflow-hidden rounded-full"
                            >
                                <ResponsiveImage
                                    :image="item.image"
                                    sizes="44px"
                                />
                            </span>
                            <span>
                                <span class="block font-medium">{{
                                    item.authorName
                                }}</span>
                                <span
                                    v-if="item.authorContext"
                                    class="block text-sm text-muted-foreground"
                                    >{{ item.authorContext }}</span
                                >
                            </span>
                        </figcaption>
                    </figure>
                    <Link
                        v-if="item.project"
                        :href="item.project.href"
                        class="mt-6 text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Ver proyecto: {{ item.project.title }}
                    </Link>
                </li>
            </ul>
        </div>
    </section>
</template>
