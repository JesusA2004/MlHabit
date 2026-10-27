<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import type { HomeSection, ShowcaseCardData } from '@/types/web';
import SectionHeading from '../SectionHeading.vue';
import ShowcaseCard from '../ShowcaseCard.vue';
import { text } from './content';

/** Proyectos destacados: composición asimétrica (uno grande + columna). */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const projects = computed(
    () => (props.section.items ?? []) as ShowcaseCardData[],
);
const lead = computed(() => projects.value[0] ?? null);
const rest = computed(() => projects.value.slice(1));
</script>

<template>
    <section aria-labelledby="proyectos-title" class="bg-stone/60 section-y">
        <div class="container-site">
            <SectionHeading
                id="proyectos-title"
                :eyebrow="text(c, 'eyebrow')"
                :title="text(c, 'title')"
                :subtitle="text(c, 'subtitle')"
            />

            <div class="mt-12 grid gap-5 lg:grid-cols-12">
                <ShowcaseCard
                    v-if="lead"
                    :project="lead"
                    size="lg"
                    :class="rest.length ? 'lg:col-span-7' : 'lg:col-span-12'"
                />
                <div
                    v-if="rest.length"
                    class="grid gap-5 sm:grid-cols-2 lg:col-span-5 lg:grid-cols-1"
                >
                    <ShowcaseCard
                        v-for="(project, i) in rest.slice(0, 2)"
                        :key="project.id"
                        :project="project"
                    />
                </div>
                <div
                    v-if="rest.length > 2"
                    class="grid gap-5 sm:grid-cols-2 lg:col-span-12 lg:grid-cols-3"
                >
                    <ShowcaseCard
                        v-for="project in rest.slice(2)"
                        :key="project.id"
                        :project="project"
                    />
                </div>
            </div>

            <div v-if="text(c, 'cta_label')" class="mt-10">
                <Link
                    href="/inspiracion"
                    class="group inline-flex items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                >
                    {{ text(c, 'cta_label') }}
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    />
                </Link>
            </div>
        </div>
    </section>
</template>
