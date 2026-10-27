<script setup lang="ts">
import { computed } from 'vue';
import type { EditorialItem, HomeSection } from '@/types/web';
import SectionHeading from '../SectionHeading.vue';
import { items, text } from './content';

/**
 * "Cómo funciona": línea de tiempo numerada. Encabezado fijo a la
 * izquierda en escritorio y pasos con numerales grandes a la derecha.
 */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const steps = computed(() => items<EditorialItem>(c.value, 'steps'));
</script>

<template>
    <section
        id="como-funciona"
        aria-labelledby="proceso-title"
        class="scroll-mt-20 section-y"
    >
        <div class="container-site grid gap-12 lg:grid-cols-12 lg:gap-16">
            <div class="lg:col-span-5">
                <div class="lg:sticky lg:top-28">
                    <SectionHeading
                        id="proceso-title"
                        :eyebrow="text(c, 'eyebrow')"
                        :title="text(c, 'title')"
                        :subtitle="text(c, 'subtitle')"
                    />
                </div>
            </div>

            <ol class="relative lg:col-span-7">
                <span
                    class="absolute top-2 bottom-2 left-[1.375rem] w-px bg-border sm:left-[1.875rem]"
                    aria-hidden="true"
                />
                <li
                    v-for="(step, i) in steps"
                    :key="i"
                    class="relative flex gap-5 pb-10 last:pb-0 sm:gap-8"
                >
                    <span
                        class="relative z-10 grid size-11 shrink-0 place-items-center rounded-full border border-border bg-background font-display text-sm font-semibold text-oak-ink tabular-nums sm:size-[3.75rem] sm:text-base"
                    >
                        {{ String(i + 1).padStart(2, '0') }}
                    </span>
                    <div class="pt-2 sm:pt-3.5">
                        <h3
                            class="font-display text-xl font-semibold tracking-tight sm:text-2xl"
                        >
                            {{ step.title }}
                        </h3>
                        <p
                            v-if="step.body"
                            class="mt-2 max-w-prose text-muted-foreground"
                        >
                            {{ step.body }}
                        </p>
                    </div>
                </li>
            </ol>
        </div>
    </section>
</template>
