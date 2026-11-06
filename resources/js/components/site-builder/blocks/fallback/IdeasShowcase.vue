<script setup lang="ts">
import { ArrowUpRight, MoveHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import SmartLink from '@/components/web/public/SmartLink.vue';
import HeroScene from '@/components/web/visual/HeroScene.vue';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import type { IllustrationName } from '@/components/web/visual/illustrations/types';

/**
 * Respaldo de «Proyectos destacados» mientras no hay proyectos publicados.
 * Se presenta como IDEAS de diseño (nunca como proyectos entregados):
 *
 * - Comparador «del plano a tu casa»: el alzado técnico contra la escena
 *   terminada. El control es un `range` nativo (teclado, lector, tacto).
 * - Dos ideas ilustradas que llevan a la solicitud.
 */
const props = defineProps<{ editing: boolean }>();

const position = ref(54);
const tag = computed(() => (props.editing ? 'div' : SmartLink));

const IDEAS: {
    title: string;
    body: string;
    art: IllustrationName;
    tone: string;
}[] = [
    {
        title: 'Clóset de piso a techo',
        body: 'Puertas en laca mate, cajones con cierre suave e iluminación interior.',
        art: 'closet',
        tone: 'bg-[linear-gradient(160deg,#c39062,var(--color-oak)_50%,#7d5534)]',
    },
    {
        title: 'Muro de TV con guardado',
        body: 'Listones de roble, repisas flotantes y cableado oculto.',
        art: 'tv',
        tone: 'bg-[radial-gradient(110%_80%_at_0%_0%,#463a32,var(--color-espresso)_62%)]',
    },
];
</script>

<template>
    <div class="grid gap-5 @5xl/sbpage:grid-cols-12">
        <figure class="@5xl/sbpage:col-span-7" data-motion="wipe-up">
            <div
                class="sb-compare relative aspect-[4/3] overflow-hidden rounded-[1.75rem] bg-[#1d3b32] select-none"
                :style="{ '--pos': `${position}%` }"
            >
                <!-- Antes: el plano -->
                <div
                    class="tex-blueprint-dark absolute inset-0 grid place-items-center bg-[#1b3a31] p-[10%] text-white/80"
                >
                    <LineIllustration
                        name="kitchen"
                        tone="dark"
                        :stroke="0.9"
                        :animate="false"
                        class="h-full w-full"
                    />
                </div>
                <!-- Después: la escena terminada -->
                <div
                    class="sb-compare-after absolute inset-0 bg-[radial-gradient(70%_60%_at_50%_30%,#3a6a5a,#1a3129)]"
                >
                    <HeroScene :chips="false" class="py-[4%]" />
                </div>
                <span
                    class="absolute top-4 left-4 rounded-full bg-black/45 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm"
                    >Plano</span
                >
                <span
                    class="absolute top-4 right-4 rounded-full bg-black/45 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm"
                    >Tu cocina</span
                >
                <span
                    class="sb-compare-handle pointer-events-none absolute inset-y-0 w-0.5 -translate-x-1/2 bg-white/90"
                    aria-hidden="true"
                >
                    <span
                        class="absolute top-1/2 left-1/2 grid size-11 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white text-espresso shadow-lg"
                    >
                        <MoveHorizontal class="size-5" />
                    </span>
                </span>
                <input
                    v-model.number="position"
                    type="range"
                    min="0"
                    max="100"
                    class="absolute inset-0 h-full w-full cursor-ew-resize opacity-0"
                    :disabled="props.editing"
                    aria-label="Comparar el plano con el espacio terminado"
                />
            </div>
            <figcaption
                class="mt-3 flex items-center justify-between gap-4 text-sm text-[color:var(--sb-ink-muted)]"
            >
                <span
                    >Del plano a tu casa: así se ve una idea antes de
                    fabricarla.</span
                >
                <span class="shrink-0 text-xs"
                    >Idea de diseño · ilustración</span
                >
            </figcaption>
        </figure>

        <ul
            class="grid gap-5 @2xl/sbpage:grid-cols-2 @5xl/sbpage:col-span-5 @5xl/sbpage:grid-cols-1"
            data-motion-stagger="120"
            data-motion="rise"
        >
            <li v-for="idea in IDEAS" :key="idea.title">
                <component
                    :is="tag"
                    :href="props.editing ? undefined : '/solicitar'"
                    class="group relative flex h-full min-h-60 flex-col justify-end overflow-hidden rounded-[1.75rem] p-6 text-white focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :class="idea.tone"
                    :data-tilt="props.editing ? undefined : '4'"
                >
                    <div
                        class="pointer-events-none absolute inset-x-[18%] top-[8%] bottom-[42%] text-white/80 transition-transform duration-700 ease-[var(--motion-ease)] group-hover:scale-[1.08]"
                        data-tilt-depth
                    >
                        <LineIllustration
                            :name="idea.art"
                            tone="dark"
                            :stroke="1.1"
                            class="h-full w-full"
                        />
                    </div>
                    <span
                        class="mb-2 inline-flex self-start rounded-full bg-black/30 px-2.5 py-0.5 text-xs backdrop-blur-sm"
                        >Idea de diseño</span
                    >
                    <h3
                        class="font-display text-xl font-semibold tracking-tight"
                    >
                        {{ idea.title }}
                    </h3>
                    <p class="mt-1 text-sm text-white/80">{{ idea.body }}</p>
                    <ArrowUpRight
                        class="absolute top-5 right-5 size-5 transition-transform duration-500 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                        aria-hidden="true"
                    />
                </component>
            </li>
        </ul>
    </div>
</template>
