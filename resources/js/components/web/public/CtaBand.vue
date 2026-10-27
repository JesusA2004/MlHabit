<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarCheck, MessageCircle, Ruler } from '@lucide/vue';
import GradientBlobs from '@/components/web/visual/GradientBlobs.vue';
import { ICON_STROKE } from '@/components/web/visual/icons';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import type { IllustrationName } from '@/components/web/visual/illustrations/types';

/**
 * Banda de cierre para páginas interiores: invita a solicitar asesoría.
 * Foco de luz que sigue al cursor, botón magnético e ilustración de línea.
 * Es el ancla "Agenda" del recorrido guiado.
 */
const props = withDefaults(
    defineProps<{
        title?: string;
        body?: string;
        href?: string;
        label?: string;
        illustration?: IllustrationName;
    }>(),
    {
        title: '¿Te imaginas algo así en tu casa?',
        body: 'Cuéntanos qué necesitas. Un asesor te orienta y te explica los siguientes pasos.',
        href: '/solicitar',
        label: 'Solicita una asesoría',
        illustration: 'blueprint',
    },
);

const steps = [
    { icon: MessageCircle, label: 'Nos cuentas tu idea' },
    { icon: Ruler, label: 'Medimos tu espacio' },
    { icon: CalendarCheck, label: 'Recibes tu propuesta' },
];
</script>

<template>
    <section
        class="pb-16 sm:pb-24"
        aria-labelledby="cta-band-title"
        data-journey="agenda"
    >
        <div class="container-site">
            <div
                data-spotlight
                data-motion="zoom-in"
                class="relative isolate overflow-hidden rounded-[2rem] bg-espresso px-6 py-12 text-espresso-foreground sm:px-12 sm:py-14"
            >
                <GradientBlobs tone="dark" />
                <div
                    class="tex-blueprint-dark pointer-events-none absolute inset-0 -z-10 [mask-image:linear-gradient(to_left,#000,transparent_60%)] opacity-50"
                    aria-hidden="true"
                />
                <div
                    class="grid gap-10 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                >
                    <div class="max-w-xl">
                        <h2 id="cta-band-title" class="text-display-md">
                            {{ props.title }}
                        </h2>
                        <p class="mt-3 text-espresso-foreground/75">
                            {{ props.body }}
                        </p>
                        <ol
                            class="mt-7 flex flex-wrap gap-x-5 gap-y-3 text-sm text-espresso-foreground/80"
                            data-motion-stagger="110"
                            data-motion-delay="250"
                            aria-label="Cómo seguimos"
                        >
                            <li
                                v-for="step in steps"
                                :key="step.label"
                                class="inline-flex items-center gap-2"
                            >
                                <span
                                    class="grid size-8 place-items-center rounded-full bg-white/8 text-oak"
                                    aria-hidden="true"
                                >
                                    <component
                                        :is="step.icon"
                                        class="size-4"
                                        :stroke-width="ICON_STROKE"
                                    />
                                </span>
                                {{ step.label }}
                            </li>
                        </ol>
                    </div>
                    <div class="flex flex-col items-start gap-6 md:items-end">
                        <LineIllustration
                            :name="props.illustration"
                            tone="dark"
                            class="hidden w-52 text-espresso-foreground/60 md:block"
                            :delay="200"
                        />
                        <Link
                            :href="props.href"
                            data-magnetic="0.3"
                            class="btn-nudge btn-sweep inline-flex h-13 shrink-0 items-center gap-3 rounded-full bg-oak pr-2 pl-7 text-base font-medium text-white [--sweep:#8a5f38]"
                        >
                            {{ props.label }}
                            <span
                                class="grid size-9 place-items-center rounded-full bg-white/15"
                            >
                                <ArrowRight
                                    class="nudge size-4"
                                    :stroke-width="1.75"
                                    aria-hidden="true"
                                />
                            </span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
