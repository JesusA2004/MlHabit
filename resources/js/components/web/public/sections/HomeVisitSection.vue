<script setup lang="ts">
import { ArrowRight, Check } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { HomeSection } from '@/types/web';
import OriginBadge from '../OriginBadge.vue';
import ResponsiveImage from '../ResponsiveImage.vue';
import SectionHeading from '../SectionHeading.vue';
import SmartLink from '../SmartLink.vue';
import { items, text } from './content';

/**
 * Visita técnica en banda espresso. El costo solo aparece si existe una
 * política de visita activa; si no, la sección no muestra precio.
 */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const points = computed(() => items<{ text: string }>(c.value, 'points'));
const visit = computed(() => props.section.visit ?? null);
const image = computed(() => props.section.image ?? null);

const visitSteps = [
    {
        title: 'Eliges día y horario',
        body: 'Ves los horarios libres en tu zona y pagas la visita en línea.',
    },
    {
        title: 'Un técnico mide tu espacio',
        body: 'Toma medidas, fotos y revisa muros, pisos e instalaciones.',
    },
    {
        title: 'Recibes diseño y cotización',
        body: 'Con esas medidas preparamos tu propuesta a la medida.',
    },
];
</script>

<template>
    <section
        aria-labelledby="visita-title"
        class="bg-espresso text-espresso-foreground"
    >
        <div
            class="container-site grid items-center gap-12 section-y lg:grid-cols-12 lg:gap-16"
        >
            <div class="lg:col-span-6">
                <SectionHeading
                    id="visita-title"
                    tone="dark"
                    :eyebrow="text(c, 'eyebrow')"
                    :title="text(c, 'title')"
                    :subtitle="text(c, 'body')"
                />
                <ul v-if="points.length" class="mt-8 grid gap-3">
                    <li
                        v-for="(point, i) in points"
                        :key="i"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground"
                            aria-hidden="true"
                        >
                            <Check class="size-3.5" />
                        </span>
                        {{ point.text }}
                    </li>
                </ul>
                <Button
                    v-if="text(c, 'cta_label') && text(c, 'cta_href')"
                    as-child
                    size="lg"
                    class="mt-10 h-12 rounded-full bg-oak px-7 text-base text-white hover:bg-oak/90"
                >
                    <SmartLink :href="text(c, 'cta_href')">
                        {{ text(c, 'cta_label') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </SmartLink>
                </Button>
            </div>

            <div class="lg:col-span-6">
                <div
                    class="relative overflow-hidden rounded-[2rem] bg-white/5 ring-1 ring-white/10"
                >
                    <div v-if="image" class="relative aspect-[16/11]">
                        <ResponsiveImage
                            :image="image"
                            sizes="(min-width: 1024px) 45vw, 100vw"
                            class="absolute inset-0"
                        />
                        <OriginBadge
                            :origin="image.origin"
                            :label="image.originLabel"
                            class="absolute top-4 left-4"
                        />
                    </div>
                    <div
                        v-if="visit"
                        :class="[
                            'p-6 sm:p-8',
                            image ? 'border-t border-white/10' : '',
                        ]"
                    >
                        <p class="text-sm text-espresso-foreground/70">
                            Costo de la visita
                        </p>
                        <p
                            class="mt-1 font-display text-5xl font-semibold tracking-tight tabular-nums sm:text-6xl"
                        >
                            {{ visit.feeFormatted }}
                        </p>
                        <p
                            v-if="visit.isCreditable"
                            class="mt-4 inline-flex rounded-full bg-success-soft px-3 py-1 text-sm font-medium text-success-ink"
                        >
                            Acreditable a tu proyecto
                        </p>
                        <p
                            v-else
                            class="mt-4 text-sm text-espresso-foreground/70"
                        >
                            Este costo no se acredita al proyecto.
                        </p>
                        <p
                            v-if="visit.isCreditable && visit.creditConditions"
                            class="mt-3 text-sm text-espresso-foreground/70"
                        >
                            {{ visit.creditConditions }}
                        </p>
                    </div>
                    <div v-else-if="!image" class="p-6 sm:p-10">
                        <p class="text-sm text-espresso-foreground/70">
                            Así funciona la visita
                        </p>
                        <ol class="mt-6 grid gap-6">
                            <li
                                v-for="(step, i) in visitSteps"
                                :key="step.title"
                                class="grid grid-cols-[2.5rem_1fr] gap-4"
                            >
                                <span
                                    class="grid size-10 place-items-center rounded-full border border-white/15 font-display text-sm font-semibold text-oak tabular-nums"
                                    aria-hidden="true"
                                >
                                    {{ i + 1 }}
                                </span>
                                <div>
                                    <p class="font-medium">{{ step.title }}</p>
                                    <p
                                        class="mt-1 text-sm text-espresso-foreground/70"
                                    >
                                        {{ step.body }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                        <p
                            class="mt-8 border-t border-white/10 pt-5 text-sm text-espresso-foreground/70"
                        >
                            Te compartimos el costo y los horarios disponibles
                            al agendar.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
