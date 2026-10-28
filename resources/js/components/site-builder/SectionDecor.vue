<script setup lang="ts">
import { computed } from 'vue';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import type { IllustrationName } from '@/components/web/visual/illustrations/types';

/**
 * Capa decorativa de una sección en el SITIO PÚBLICO (nunca en el editor):
 * dibujos de línea de la marca, aros, cotas y retícula de plano que se
 * mueven con el scroll (`data-scroll-progress` + `.scroll-move`, solo
 * transform). Siempre `aria-hidden`, sin eventos de puntero y recortada a
 * la sección: no altera el layout ni crea scroll horizontal. Con
 * movimiento reducido se queda quieta. En móvil solo queda lo más ligero.
 *
 * El contenido sigue siendo del editor: aquí solo hay ornamento según el
 * tipo de sección.
 */
const props = defineProps<{
    type: string;
    dark: boolean;
    hasImage: boolean;
}>();

type Drawing = {
    name: IllustrationName;
    /** Clases de posición/tamaño. */
    place: string;
    move: string;
};

const DRAWINGS: Record<string, Drawing> = {
    categories: {
        name: 'closet',
        place: '-right-10 top-10 w-72 opacity-[0.16] xl:right-[4%]',
        move: '--move-y:-90px;--spin:-6deg',
    },
    process: {
        name: 'tape',
        place: 'right-[3%] bottom-16 w-64 opacity-[0.2]',
        move: '--move-y:-140px;--spin:10deg',
    },
    faq: {
        name: 'blueprint',
        place: '-left-12 bottom-10 w-72 opacity-[0.14]',
        move: '--move-y:-100px;--move-x:40px',
    },
    testimonials: {
        name: 'bedroom',
        place: 'right-[2%] top-8 w-64 opacity-[0.14]',
        move: '--move-y:-80px',
    },
    payment_options: {
        name: 'toolbox',
        place: '-right-6 bottom-6 w-56 opacity-[0.14]',
        move: '--move-y:-90px;--spin:-8deg',
    },
};

const drawing = computed(() =>
    props.hasImage ? null : (DRAWINGS[props.type] ?? null),
);
const isHero = computed(() => props.type === 'hero' && !props.hasImage);
const RING_TYPES = ['featured_projects', 'home_visit', 'benefits'];
const ring = computed(() =>
    props.dark ? 'border-white/10' : 'border-foreground/[0.07]',
);
</script>

<template>
    <div
        class="sb-decor pointer-events-none absolute inset-0 -z-[1] overflow-hidden"
        data-scroll-progress
        aria-hidden="true"
    >
        <!-- Portada: plano, aros que giran y cotas que se separan. -->
        <template v-if="isHero">
            <div
                class="tex-blueprint-dark absolute inset-0 [mask-image:radial-gradient(ellipse_at_70%_40%,#000_10%,transparent_70%)]"
            />
            <span
                class="blob -top-24 right-[8%] size-[28rem]"
                style="background: rgb(176 125 79 / 0.28)"
            />
            <span
                class="blob bottom-0 -left-20 size-96"
                style="background: rgb(47 90 76 / 0.45); --blob-duration: 28s"
            />
            <span
                class="scroll-move absolute top-[14%] right-[30%] hidden size-40 rounded-full border md:block"
                :class="ring"
                style="--move-y: 220px; --spin: 90deg"
            >
                <span
                    class="absolute -top-1 left-1/2 size-2 -translate-x-1/2 rounded-full bg-oak"
                />
            </span>
            <span
                class="scroll-move absolute right-[6%] bottom-[22%] hidden size-72 rounded-full border border-dashed md:block"
                :class="ring"
                style="--move-y: -160px; --spin: -60deg"
            />
            <span
                class="scroll-move absolute top-[30%] left-[52%] hidden w-40 text-white/25 lg:block"
                style="--move-x: 160px"
            >
                <span class="dimension-line block" />
                <span
                    class="mt-2 block text-center font-mono text-[10px] tracking-widest"
                    >240 cm</span
                >
            </span>
            <span
                class="scroll-move absolute bottom-[34%] left-[4%] hidden h-32 text-white/20 lg:block"
                style="--move-y: -120px"
            >
                <span class="block h-full w-px bg-current" />
            </span>
        </template>

        <!-- Secciones claras: un dibujo de línea que flota con el scroll. -->
        <div
            v-if="drawing"
            class="scroll-move absolute hidden md:block"
            :class="drawing.place"
            :style="drawing.move"
        >
            <LineIllustration
                :name="drawing.name"
                :tone="props.dark ? 'dark' : 'light'"
                :stroke="1"
                :class="props.dark ? 'text-white' : 'text-foreground'"
            />
        </div>

        <!-- Aros de acento en secciones con fondo propio. -->
        <span
            v-if="
                !isHero &&
                !drawing &&
                !props.hasImage &&
                RING_TYPES.includes(props.type)
            "
            class="scroll-move absolute -top-24 -right-24 hidden size-[22rem] rounded-full border-[2.5rem] md:block"
            :class="props.dark ? 'border-white/[0.04]' : 'border-oak/[0.07]'"
            style="--move-y: 160px; --spin: 40deg"
        />
    </div>
</template>
