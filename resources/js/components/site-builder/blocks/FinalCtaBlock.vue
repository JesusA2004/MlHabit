<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import SmartLink from '@/components/web/public/SmartLink.vue';
import ParticleField from '@/components/web/visual/ParticleField.vue';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Cierre con la invitación principal: banda de bosque nocturno con luz
 * cálida, polvo y virutas flotando (`ParticleField`, solo sitio público),
 * listones de roble que se mueven con el scroll y titular grande. Imagen
 * de fondo opcional (con velo para contraste AA).
 */
const props = defineProps<{ block: Block }>();

const { section, str, editing } = useBlock(() => props.block);

const image = computed(
    () => section?.value.data[props.block.id]?.image ?? null,
);
const primaryHref = computed(() => str('primary_cta_href') || '/solicitar');
const linkTag = computed(() => (editing.value ? 'span' : SmartLink));
const SLATS = Array.from({ length: 9 }, (_, i) => i);
</script>

<template>
    <div
        data-journey-hide
        :data-scroll-progress="editing ? undefined : ''"
        :data-spotlight="editing ? undefined : ''"
        class="relative isolate overflow-hidden rounded-[2.25rem] bg-[#18261f] px-6 py-16 text-white @2xl/sbpage:px-14 @2xl/sbpage:py-24 @5xl/sbpage:px-20 @5xl/sbpage:py-28"
    >
        <div
            class="absolute inset-0 -z-30 bg-[radial-gradient(60%_80%_at_85%_20%,rgb(196_140_88/0.38),transparent_60%),radial-gradient(70%_90%_at_0%_100%,rgb(47_90_76/0.9),transparent_70%),linear-gradient(160deg,#1f3c33,#141f1b_60%,#231b16)]"
            aria-hidden="true"
        />
        <template v-if="image">
            <ResponsiveImage
                :image="image"
                :sizes="image.sizes"
                class="absolute inset-0 -z-20"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-r from-[#14201b] via-[#14201b]/85 to-[#14201b]/35"
                aria-hidden="true"
            />
        </template>
        <template v-else>
            <!-- Listones de roble que se separan con el scroll -->
            <div
                class="pointer-events-none absolute inset-y-0 right-0 -z-10 hidden w-[34%] items-stretch gap-[6%] [mask-image:linear-gradient(to_left,#000_40%,transparent)] px-[3%] @5xl/sbpage:flex"
                aria-hidden="true"
            >
                <span
                    v-for="i in SLATS"
                    :key="i"
                    class="scroll-move flex-1 rounded-full bg-gradient-to-b from-[#c39062]/70 via-[#b07d4f]/45 to-transparent"
                    :style="{ '--move-y': `${(i % 3) * 30 - 40}px` }"
                />
            </div>
            <div
                class="tex-blueprint-dark pointer-events-none absolute inset-0 -z-20 [mask-image:radial-gradient(ellipse_at_20%_40%,#000,transparent_70%)] opacity-60"
                aria-hidden="true"
            />
        </template>
        <ParticleField
            v-if="!editing"
            variant="mixed"
            tone="dark"
            :density="0.9"
            beam
            class="-z-10"
        />

        <div class="relative max-w-2xl">
            <p
                v-if="str('eyebrow')"
                class="mb-5 inline-flex items-center gap-2 text-sm font-medium text-oak"
            >
                <span class="h-px w-8 bg-current" aria-hidden="true" />
                {{ str('eyebrow') }}
            </p>
            <h2
                class="sb-heading sb-heading--xl"
                :data-motion="editing ? undefined : 'blur-up'"
            >
                {{ str('title') }}
            </h2>
            <p
                v-if="str('body')"
                class="sb-text sb-text--lead mt-5 max-w-xl text-white/80"
                :data-motion="editing ? undefined : 'fade-up'"
                data-motion-delay="120"
            >
                {{ str('body') }}
            </p>
            <div
                class="mt-10 flex flex-col gap-3 @2xl/sbpage:flex-row @2xl/sbpage:flex-wrap"
                :data-motion="editing ? undefined : 'fade-up'"
                data-motion-delay="220"
            >
                <component
                    :is="linkTag"
                    :href="editing ? undefined : primaryHref"
                    :data-magnetic="editing ? undefined : '0.25'"
                    class="group inline-flex h-13 items-center justify-center gap-2 rounded-full bg-white px-8 text-base font-medium text-espresso shadow-[0_14px_40px_-14px_rgb(0_0_0/0.6)] transition-colors hover:bg-linen focus-visible:ring-[3px] focus-visible:ring-white/50 focus-visible:outline-none"
                >
                    {{ str('primary_cta_label') || 'Agenda tu asesoría' }}
                    <ArrowRight
                        class="size-4 transition-transform duration-300 group-hover:translate-x-1"
                        aria-hidden="true"
                    />
                </component>
                <component
                    :is="linkTag"
                    v-if="
                        str('secondary_cta_label') && str('secondary_cta_href')
                    "
                    :href="editing ? undefined : str('secondary_cta_href')"
                    :data-magnetic="editing ? undefined : '0.2'"
                    class="inline-flex h-13 items-center justify-center rounded-full border border-white/35 bg-white/[0.04] px-8 text-base font-medium text-white backdrop-blur-sm transition-colors hover:border-white/70 hover:bg-white/10 focus-visible:ring-[3px] focus-visible:ring-white/50 focus-visible:outline-none"
                >
                    {{ str('secondary_cta_label') }}
                </component>
            </div>
        </div>
    </div>
</template>
