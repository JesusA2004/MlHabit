<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { HomeSection } from '@/types/web';
import ResponsiveImage from '../ResponsiveImage.vue';
import SmartLink from '../SmartLink.vue';
import { text } from './content';

/** Cierre con la invitación principal (imagen de fondo opcional). */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const image = computed(() => props.section.image ?? null);
</script>

<template>
    <section aria-labelledby="cta-final-title" class="pb-16 sm:pb-24">
        <div class="container-site">
            <div
                class="relative isolate overflow-hidden rounded-[2rem] bg-primary px-6 py-16 text-primary-foreground sm:px-14 sm:py-24"
            >
                <template v-if="image">
                    <ResponsiveImage
                        :image="image"
                        sizes="(min-width: 1200px) 1200px, 100vw"
                        class="absolute inset-0 -z-20"
                    />
                    <div
                        class="absolute inset-0 -z-10 bg-gradient-to-r from-primary via-primary/85 to-primary/40"
                        aria-hidden="true"
                    />
                </template>
                <div
                    v-else
                    class="pointer-events-none absolute -right-24 -bottom-24 -z-10 size-96 rounded-full border-[3rem] border-white/5"
                    aria-hidden="true"
                />
                <div class="max-w-2xl">
                    <p
                        v-if="text(c, 'eyebrow')"
                        class="mb-4 text-sm font-medium opacity-80"
                    >
                        {{ text(c, 'eyebrow') }}
                    </p>
                    <h2 id="cta-final-title" class="text-display-lg">
                        {{ text(c, 'title') }}
                    </h2>
                    <p v-if="text(c, 'body')" class="mt-4 text-lead opacity-85">
                        {{ text(c, 'body') }}
                    </p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <Button
                            as-child
                            size="lg"
                            class="h-12 rounded-full bg-white px-7 text-base text-primary hover:bg-linen"
                        >
                            <SmartLink
                                :href="
                                    text(c, 'primary_cta_href') || '/solicitar'
                                "
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
                </div>
            </div>
        </div>
    </section>
</template>
