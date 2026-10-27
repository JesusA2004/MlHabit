<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { JOURNEY_STEPS } from '@/lib/motion/journey';
import type { JourneyStepKey } from '@/lib/motion/journey';
import { cn } from '@/lib/utils';
import LineIllustration from '../illustrations/LineIllustration.vue';
import type { IllustrationName } from '../illustrations/types';

/**
 * "Siguiente paso": cierre editorial de cada página que lleva al paso
 * lógico siguiente del recorrido (catálogo → producto → solicitud).
 */
const props = withDefaults(
    defineProps<{
        title: string;
        href: string;
        body?: string | null;
        step?: JourneyStepKey;
        illustration?: IllustrationName;
        class?: HTMLAttributes['class'];
    }>(),
    { illustration: 'blueprint' },
);

const stepInfo = computed(() => {
    const index = JOURNEY_STEPS.findIndex((s) => s.key === props.step);

    return index >= 0
        ? {
              n: index + 1,
              total: JOURNEY_STEPS.length,
              label: JOURNEY_STEPS[index].label,
          }
        : null;
});
</script>

<template>
    <section
        :class="cn('pb-16 sm:pb-24', props.class)"
        aria-label="Siguiente paso"
    >
        <div class="container-site">
            <Link
                :href="props.href"
                data-motion="fade-up"
                class="group relative isolate grid items-center gap-6 overflow-hidden rounded-[2rem] border border-border bg-card p-6 transition-[border-color,box-shadow] duration-500 outline-none hover:border-primary/30 hover:shadow-lg focus-visible:ring-3 focus-visible:ring-ring/50 sm:p-10 md:grid-cols-[minmax(0,1fr)_14rem] lg:grid-cols-[minmax(0,1fr)_18rem]"
            >
                <span
                    class="tex-blueprint absolute inset-0 -z-10 opacity-0 transition-opacity duration-700 group-hover:opacity-100"
                    aria-hidden="true"
                />
                <span
                    class="absolute inset-y-0 left-0 -z-10 w-1.5 origin-bottom scale-y-0 bg-primary transition-transform duration-500 ease-out group-hover:scale-y-100"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <p
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                    >
                        <span class="font-medium text-oak-ink"
                            >Siguiente paso</span
                        >
                        <span
                            v-if="stepInfo"
                            class="rounded-full bg-primary-soft px-2.5 py-0.5 text-xs font-medium text-primary tabular-nums"
                        >
                            {{ stepInfo.n }} de {{ stepInfo.total }} ·
                            {{ stepInfo.label }}
                        </span>
                    </p>
                    <p
                        class="mt-4 flex items-center gap-4 font-display text-[clamp(1.75rem,1.2rem+2.4vw,3.25rem)] leading-[1.05] font-semibold tracking-tight text-foreground"
                    >
                        <span
                            class="link-underline [background-size:0%_2px] group-hover:[background-size:100%_2px]"
                            >{{ props.title }}</span
                        >
                    </p>
                    <p
                        v-if="props.body"
                        class="mt-3 max-w-xl text-muted-foreground"
                    >
                        {{ props.body }}
                    </p>
                    <span
                        class="mt-6 inline-grid size-14 place-items-center rounded-full bg-foreground text-background transition-[background-color,transform] duration-500 ease-out group-hover:translate-x-2 group-hover:bg-primary"
                        aria-hidden="true"
                    >
                        <ArrowRight
                            class="size-5 transition-transform duration-500 group-hover:-rotate-45"
                            :stroke-width="1.5"
                        />
                    </span>
                </div>
                <LineIllustration
                    :name="props.illustration"
                    class="hidden w-full text-foreground/80 transition-transform duration-700 ease-out group-hover:scale-[1.04] group-hover:-rotate-1 md:block"
                    :delay="200"
                />
            </Link>
        </div>
    </section>
</template>
