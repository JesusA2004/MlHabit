<script setup lang="ts">
import { ArrowDown, ArrowRight } from '@lucide/vue';
import { computed } from 'vue';

import { Button } from '@/components/ui/button';
import type { HomeSection } from '@/types/web';
import HeroElevation from '../HeroElevation.vue';
import ResponsiveImage from '../ResponsiveImage.vue';
import SmartLink from '../SmartLink.vue';
import { text } from './content';

/**
 * Portada. Con imagen protagonista: foto/render a sangre bajo la cabecera
 * transparente. Sin imagen: portada tipográfica sobre espresso con el
 * abanico de acabados de la marca (sin fotos de stock).
 */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const image = computed(() => props.section.image ?? null);
</script>

<template>
    <section
        aria-labelledby="hero-title"
        class="relative isolate overflow-hidden bg-espresso text-white"
    >
        <template v-if="image">
            <ResponsiveImage
                :image="image"
                priority
                sizes="100vw"
                class="absolute inset-0 -z-20"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-t from-black/75 via-black/35 to-black/30 md:bg-gradient-to-r md:from-black/70 md:via-black/35 md:to-transparent"
                aria-hidden="true"
            />
        </template>
        <div
            v-else
            class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(110%_75%_at_0%_0%,rgb(255_255_255/0.07),transparent_60%)]"
            aria-hidden="true"
        />

        <div
            class="container-site grid min-h-[min(92svh,860px)] items-end gap-12 pt-32 pb-14 md:pb-20 lg:grid-cols-12"
        >
            <div :class="image ? 'lg:col-span-8' : 'lg:col-span-7'">
                <p
                    v-if="text(c, 'eyebrow')"
                    class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3.5 py-1.5 text-xs font-medium tracking-wide backdrop-blur-sm"
                >
                    <span
                        class="size-1.5 rounded-full bg-oak"
                        aria-hidden="true"
                    />
                    {{ text(c, 'eyebrow') }}
                </p>
                <h1
                    id="hero-title"
                    class="max-w-[16ch] text-display-xl text-balance"
                >
                    {{ text(c, 'title') }}
                </h1>
                <p
                    v-if="text(c, 'subtitle')"
                    class="mt-6 max-w-xl text-lead text-white/85"
                >
                    {{ text(c, 'subtitle') }}
                </p>
                <div
                    class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center"
                >
                    <Button
                        as-child
                        size="lg"
                        class="h-12 rounded-full bg-white px-7 text-base text-espresso hover:bg-linen"
                    >
                        <SmartLink
                            :href="text(c, 'primary_cta_href') || '/solicitar'"
                        >
                            {{
                                text(c, 'primary_cta_label') ||
                                'Solicita una asesoría'
                            }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </SmartLink>
                    </Button>
                    <Button
                        v-if="
                            text(c, 'secondary_cta_label') &&
                            text(c, 'secondary_cta_href')
                        "
                        as-child
                        size="lg"
                        variant="outline"
                        class="h-12 rounded-full border-white/40 bg-transparent px-7 text-base text-white hover:bg-white/10 hover:text-white"
                    >
                        <SmartLink :href="text(c, 'secondary_cta_href')">{{
                            text(c, 'secondary_cta_label')
                        }}</SmartLink>
                    </Button>
                </div>
                <p v-if="text(c, 'note')" class="mt-5 text-sm text-white/70">
                    {{ text(c, 'note') }}
                </p>
            </div>

            <div
                v-if="!image"
                class="flex lg:col-span-5 lg:justify-end lg:self-center"
            >
                <HeroElevation
                    class="w-full max-w-[17rem] text-white sm:max-w-sm lg:max-w-md"
                />
            </div>
            <div
                v-else-if="image.originLabel"
                class="flex lg:col-span-4 lg:justify-end"
            >
                <span
                    class="rounded-full bg-black/40 px-3 py-1 text-xs text-white/85 backdrop-blur-sm"
                    >{{ image.originLabel }}</span
                >
            </div>
        </div>

        <a
            href="#contenido-portada"
            class="absolute bottom-6 left-1/2 hidden -translate-x-1/2 rounded-full p-2 text-white/70 hover:text-white md:block"
            aria-label="Ir al contenido"
        >
            <ArrowDown class="size-5" />
        </a>
    </section>
    <span id="contenido-portada" aria-hidden="true" />
</template>
