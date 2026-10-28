<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import type { TestimonialData } from '@/types/web';
import type { Block } from '../types';
import DynamicEmpty from './DynamicEmpty.vue';
import { useBlock } from './useBlock';

/** Testimonios publicados con consentimiento registrado. */
const props = defineProps<{ block: Block }>();

const { section, bridge } = useBlock(() => props.block);

const testimonials = computed(
    () =>
        (section?.value.data[props.block.id]?.items ?? []) as TestimonialData[],
);
</script>

<template>
    <ul
        v-if="testimonials.length"
        class="-mx-4 scrollbar-none flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pt-2 pb-4 @2xl/sbpage:mx-0 @2xl/sbpage:px-0"
        aria-label="Testimonios"
    >
        <li
            v-for="item in testimonials"
            :key="item.id"
            class="group flex w-[85%] shrink-0 snap-start flex-col justify-between rounded-2xl bg-card p-7 text-foreground shadow-xs ring-1 ring-border transition-[transform,box-shadow] duration-500 ease-out hover:-translate-y-1 hover:shadow-[0_18px_40px_-24px_rgb(35_30_26/0.35)] @2xl/sbpage:w-[26rem]"
        >
            <figure>
                <span
                    class="inline-block font-display text-6xl leading-none text-oak transition-transform duration-500 ease-out group-hover:-translate-y-1 group-hover:-rotate-6"
                    aria-hidden="true"
                    >“</span
                >
                <blockquote class="-mt-4 text-lg leading-relaxed">
                    {{ item.quote }}
                </blockquote>
                <figcaption class="mt-6 flex items-center gap-3">
                    <span
                        v-if="item.image"
                        class="size-11 overflow-hidden rounded-full"
                    >
                        <ResponsiveImage :image="item.image" sizes="44px" />
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
                v-if="item.project && !bridge"
                :href="item.project.href"
                class="mt-6 text-sm font-medium text-primary underline-offset-4 hover:underline"
            >
                Ver proyecto: {{ item.project.title }}
            </Link>
        </li>
    </ul>
    <DynamicEmpty
        v-else-if="bridge"
        title="Aún no hay testimonios publicados"
        body="Publica testimonios con consentimiento en Sitio web → Testimonios. Mientras no haya, esta sección no se muestra."
    />
</template>
