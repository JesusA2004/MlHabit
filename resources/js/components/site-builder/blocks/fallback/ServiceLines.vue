<script setup lang="ts">
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import SmartLink from '@/components/web/public/SmartLink.vue';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import type { IllustrationName } from '@/components/web/visual/illustrations/types';

/**
 * Respaldo de «Categorías» mientras no hay categorías publicadas: las
 * líneas de servicio de ML Hábitat en mosaico, con ilustraciones de la
 * marca. Sin cifras ni fotos inventadas; cada tarjeta lleva a la solicitud.
 * Hover/foco: la tarjeta se inclina, la ilustración se acerca y aparece la
 * invitación (en táctil está visible desde el inicio).
 */
const props = defineProps<{ editing: boolean }>();

type Line = {
    title: string;
    body: string;
    art: IllustrationName;
    tone: string;
    dark: boolean;
};

const LINES: Line[] = [
    {
        title: 'Cocinas integrales',
        body: 'Islas, alacenas a techo y cubiertas pensadas para cómo cocinas.',
        art: 'kitchen',
        tone: 'bg-[radial-gradient(120%_90%_at_100%_0%,#3c6d5d,var(--color-primary)_55%,#1f3d33)]',
        dark: true,
    },
    {
        title: 'Clósets y vestidores',
        body: 'Cada centímetro aprovechado, con iluminación y herrajes suaves.',
        art: 'closet',
        tone: 'bg-[linear-gradient(160deg,#c39062,var(--color-oak)_45%,#8a5f3a)]',
        dark: true,
    },
    {
        title: 'Recámaras',
        body: 'Cabeceras, burós y muebles que hacen juego.',
        art: 'bedroom',
        tone: 'bg-[linear-gradient(180deg,#f3eee6,#e3d9cb)]',
        dark: false,
    },
    {
        title: 'Centros de entretenimiento',
        body: 'Muros de TV con guardado oculto y cableado ordenado.',
        art: 'tv',
        tone: 'bg-[radial-gradient(110%_80%_at_0%_0%,#463a32,var(--color-espresso)_62%)]',
        dark: true,
    },
    {
        title: 'Zapateras y más',
        body: 'Recibidores, libreros y piezas a la medida de tu espacio.',
        art: 'shoes',
        tone: 'bg-[linear-gradient(180deg,#ffffff,#efe9e0)]',
        dark: false,
    },
];

const tag = computed(() => (props.editing ? 'div' : SmartLink));
</script>

<template>
    <ul
        class="grid h-full gap-4 @2xl/sbpage:grid-cols-2 @5xl/sbpage:grid-cols-4 @5xl/sbpage:grid-rows-[repeat(2,minmax(17rem,1fr))]"
        data-motion-stagger="90"
        data-motion="rise"
    >
        <li
            v-for="(line, i) in LINES"
            :key="line.title"
            class="min-h-64"
            :class="[
                i === 0 &&
                    'min-h-96 @2xl/sbpage:col-span-2 @5xl/sbpage:row-span-2',
            ]"
        >
            <component
                :is="tag"
                :href="props.editing ? undefined : '/solicitar'"
                class="group relative flex h-full flex-col overflow-hidden rounded-[1.75rem] p-6 ring-1 ring-black/5 transition-shadow duration-500 hover:shadow-[0_30px_60px_-30px_rgb(35_30_26/0.55)] focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none @2xl/sbpage:p-8"
                :class="[
                    line.tone,
                    line.dark ? 'text-white' : 'text-foreground',
                ]"
                :data-tilt="props.editing ? undefined : '4'"
            >
                <div
                    class="pointer-events-none relative mb-5 min-h-28 flex-1 transition-transform duration-700 ease-[var(--motion-ease)] group-hover:scale-[1.07] group-focus-visible:scale-[1.07]"
                    :class="[
                        line.dark ? 'text-white/85' : 'text-foreground/80',
                        i === 0 ? 'px-[6%]' : 'px-[4%]',
                    ]"
                    data-tilt-depth
                >
                    <LineIllustration
                        :name="line.art"
                        :tone="line.dark ? 'dark' : 'light'"
                        :stroke="i === 0 ? 1 : 1.2"
                        :delay="i * 120"
                        class="absolute inset-0 h-full w-full"
                    />
                </div>
                <span
                    class="font-display text-xs font-semibold tracking-wide tabular-nums opacity-70"
                    >{{ String(i + 1).padStart(2, '0') }}</span
                >
                <h3
                    class="mt-1 font-display font-semibold tracking-tight"
                    :class="
                        i === 0
                            ? 'text-3xl @5xl/sbpage:text-4xl'
                            : 'text-xl @5xl/sbpage:text-2xl'
                    "
                >
                    {{ line.title }}
                </h3>
                <p
                    class="mt-2 max-w-sm text-sm leading-relaxed"
                    :class="
                        line.dark ? 'text-white/80' : 'text-muted-foreground'
                    "
                >
                    {{ line.body }}
                </p>
                <span
                    class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium transition-[opacity,translate] duration-500 [@media(hover:hover)]:translate-y-2 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:translate-y-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:group-focus-visible:translate-y-0 [@media(hover:hover)]:group-focus-visible:opacity-100"
                    :class="line.dark ? 'text-white' : 'text-primary'"
                >
                    Diseña el tuyo
                    <ArrowUpRight class="size-4" aria-hidden="true" />
                </span>
            </component>
        </li>
    </ul>
</template>
