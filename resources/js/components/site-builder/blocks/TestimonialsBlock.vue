<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    HandHeart,
    ReceiptText,
    ShieldCheck,
    Smartphone,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import { ICON_STROKE } from '@/components/web/visual/icons';
import type { TestimonialData } from '@/types/web';
import type { Block } from '../types';
import DynamicEmpty from './DynamicEmpty.vue';
import ExampleNote from './fallback/ExampleNote.vue';
import { useBlock } from './useBlock';

/**
 * Testimonios publicados con consentimiento registrado, en carrusel con
 * desplazamiento por arrastre/tacto (scroll-snap) y botones Anterior /
 * Siguiente (teclado y lectores). Sin avance automático.
 *
 * Respaldo («Mostrar contenido de ejemplo»): NUNCA testimonios inventados;
 * se muestran los compromisos de servicio de la marca.
 */
const props = defineProps<{ block: Block }>();

const { section, bridge, bool } = useBlock(() => props.block);

const testimonials = computed(
    () =>
        (section?.value.data[props.block.id]?.items ?? []) as TestimonialData[],
);

const PROMISES: { title: string; body: string; icon: Component }[] = [
    {
        title: 'Un solo responsable',
        body: 'El mismo equipo diseña, fabrica e instala: nadie se pasa la bolita.',
        icon: HandHeart,
    },
    {
        title: 'Cotización clara',
        body: 'Materiales, acabados y fechas por escrito antes de fabricar.',
        icon: ReceiptText,
    },
    {
        title: 'Seguimiento en línea',
        body: 'Consulta el avance, tus citas y tus pagos desde tu portal.',
        icon: Smartphone,
    },
    {
        title: 'Cuidamos tu casa',
        body: 'Instalación ordenada y limpia; revisamos contigo el resultado.',
        icon: ShieldCheck,
    },
];

const track = ref<HTMLElement | null>(null);
const atStart = ref(true);
const atEnd = ref(false);

function update() {
    const el = track.value;
    if (!el) return;
    atStart.value = el.scrollLeft <= 4;
    atEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
}

function go(direction: 1 | -1) {
    const el = track.value;
    if (!el) return;
    const card = el.firstElementChild as HTMLElement | null;
    const step = (card?.offsetWidth ?? el.clientWidth * 0.8) + 20;
    el.scrollBy({ left: direction * step });
}

onMounted(() => {
    update();
    track.value?.addEventListener('scroll', update, { passive: true });
});
onBeforeUnmount(() => track.value?.removeEventListener('scroll', update));
</script>

<template>
    <div v-if="testimonials.length" class="relative">
        <ul
            ref="track"
            class="sb-carousel -mx-4 flex gap-5 overflow-x-auto px-4 pt-2 pb-4 @2xl/sbpage:mx-0 @2xl/sbpage:px-0"
            aria-label="Testimonios"
            tabindex="0"
        >
            <li
                v-for="item in testimonials"
                :key="item.id"
                class="group flex w-[85%] shrink-0 flex-col justify-between rounded-[1.75rem] bg-card p-7 text-foreground shadow-xs ring-1 ring-border transition-[translate,box-shadow] duration-500 ease-out hover:-translate-y-1 hover:shadow-[0_24px_50px_-26px_rgb(35_30_26/0.4)] @2xl/sbpage:w-[26rem] @2xl/sbpage:p-9"
            >
                <figure>
                    <span
                        class="sb-quote-mark inline-block text-7xl text-oak transition-transform duration-500 ease-out group-hover:-translate-y-1 group-hover:-rotate-6"
                        aria-hidden="true"
                        >“</span
                    >
                    <blockquote class="mt-2 text-lg leading-relaxed">
                        {{ item.quote }}
                    </blockquote>
                    <figcaption class="mt-6 flex items-center gap-3">
                        <span
                            v-if="item.image"
                            class="size-11 overflow-hidden rounded-full"
                        >
                            <ResponsiveImage :image="item.image" sizes="44px" />
                        </span>
                        <span
                            v-else
                            class="grid size-11 place-items-center rounded-full bg-primary-soft font-display font-semibold text-primary"
                            aria-hidden="true"
                            >{{ item.authorName.slice(0, 1) }}</span
                        >
                        <span>
                            <span class="block font-medium">{{
                                item.authorName
                            }}</span>
                            <span
                                v-if="item.authorContext"
                                class="block text-sm text-muted-foreground"
                                >{{ item.authorContext }}</span
                            >
                        </span>
                    </figcaption>
                </figure>
                <Link
                    v-if="item.project && !bridge"
                    :href="item.project.href"
                    class="mt-6 text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver proyecto: {{ item.project.title }}
                </Link>
            </li>
        </ul>
        <div v-if="testimonials.length > 1" class="mt-4 flex justify-end gap-2">
            <button
                type="button"
                class="grid size-11 place-items-center rounded-full border border-border bg-card text-foreground transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none disabled:opacity-40"
                :disabled="atStart || bridge !== null"
                aria-label="Testimonio anterior"
                @click="go(-1)"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="grid size-11 place-items-center rounded-full bg-primary text-primary-foreground transition-colors hover:bg-primary-hover focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none disabled:opacity-40"
                :disabled="atEnd || bridge !== null"
                aria-label="Siguiente testimonio"
                @click="go(1)"
            >
                <ArrowRight class="size-4" aria-hidden="true" />
            </button>
        </div>
    </div>

    <div v-else-if="bool('show_example')" class="flex flex-col gap-3">
        <ExampleNote v-if="bridge">
            Ejemplo: al publicar testimonios con consentimiento aparecerán aquí.
        </ExampleNote>
        <ul
            class="grid gap-4 @2xl/sbpage:grid-cols-2 @5xl/sbpage:grid-cols-4"
            aria-label="Nuestros compromisos"
            data-motion-stagger="90"
            data-motion="rise"
        >
            <li
                v-for="(promise, i) in PROMISES"
                :key="promise.title"
                class="group relative flex flex-col overflow-hidden rounded-[1.75rem] bg-card p-7 text-foreground ring-1 ring-border transition-[translate,box-shadow] duration-500 hover:-translate-y-1 hover:shadow-[0_24px_50px_-26px_rgb(35_30_26/0.4)]"
            >
                <span
                    class="sb-quote-mark pointer-events-none absolute -top-2 right-5 text-8xl text-oak/15 transition-transform duration-700 group-hover:-rotate-6"
                    aria-hidden="true"
                    >“</span
                >
                <span
                    class="grid size-12 place-items-center rounded-2xl bg-primary-soft text-primary transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6"
                    aria-hidden="true"
                >
                    <component
                        :is="promise.icon"
                        class="size-5"
                        :stroke-width="ICON_STROKE"
                    />
                </span>
                <p
                    class="mt-8 font-display text-xs font-semibold tracking-wide text-oak-ink tabular-nums"
                >
                    Compromiso {{ String(i + 1).padStart(2, '0') }}
                </p>
                <h3
                    class="mt-1 font-display text-xl font-semibold tracking-tight"
                >
                    {{ promise.title }}
                </h3>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    {{ promise.body }}
                </p>
            </li>
        </ul>
    </div>

    <DynamicEmpty
        v-else-if="bridge"
        title="Aún no hay testimonios publicados"
        body="Publica testimonios con consentimiento en Sitio web → Testimonios. Mientras no haya, esta sección no se muestra (o activa «Mostrar contenido de ejemplo»)."
    />
</template>
