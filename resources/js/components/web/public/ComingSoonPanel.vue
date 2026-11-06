<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarCheck, House, Video } from '@lucide/vue';
import { ICON_STROKE } from '@/components/web/visual/icons';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import type { IllustrationName } from '@/components/web/visual/illustrations/types';
import { defaultSiteCta } from '@/lib/navigation';

/**
 * Estado vacío del sitio público para secciones que aún no tienen
 * contenido publicado (Catálogo, Inspiración): en vez de una página que
 * parece rota, una ficha de "plano en preparación" con ilustraciones de
 * línea, lo que sí podemos hacer hoy y los dos caminos para empezar.
 */
const props = withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        description: string;
        illustrations?: IllustrationName[];
        /** Líneas que fabricamos, como chips. */
        lines?: string[];
    }>(),
    {
        eyebrow: 'En preparación',
        illustrations: () => ['kitchen', 'closet', 'tv'],
        lines: () => [
            'Cocinas integrales',
            'Clósets y vestidores',
            'Recámaras',
            'Centros de entretenimiento',
        ],
    },
);

const paths = [
    {
        icon: Video,
        title: 'Asesoría por videollamada',
        body: 'Platicamos de tu espacio, estilo y presupuesto.',
        href: defaultSiteCta.href,
    },
    {
        icon: House,
        title: 'Visita a domicilio',
        body: 'Un técnico mide tu espacio y revisa las condiciones.',
        href: '/agendar-visita',
    },
];
</script>

<template>
    <div
        class="relative overflow-hidden rounded-[2rem] bg-linen ring-1 ring-border"
        data-motion="rise"
    >
        <div
            class="tex-blueprint pointer-events-none absolute inset-0 opacity-80"
            aria-hidden="true"
        />
        <div
            class="relative grid gap-10 p-6 sm:p-10 lg:grid-cols-12 lg:items-center lg:gap-12 lg:p-14"
        >
            <div class="lg:col-span-6">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-oak/40 bg-background/70 px-3 py-1 text-xs font-medium text-oak backdrop-blur"
                >
                    <span class="relative flex size-2" aria-hidden="true">
                        <span
                            class="coming-soon__ping absolute inline-flex size-full rounded-full bg-oak/60"
                        />
                        <span
                            class="relative inline-flex size-2 rounded-full bg-oak"
                        />
                    </span>
                    {{ props.eyebrow }}
                </span>
                <h2 class="mt-5 text-display-md text-balance">
                    {{ props.title }}
                </h2>
                <p class="mt-3 max-w-xl text-pretty text-muted-foreground">
                    {{ props.description }}
                </p>

                <ul
                    v-if="props.lines.length"
                    class="mt-6 flex flex-wrap gap-2"
                    aria-label="Lo que diseñamos y fabricamos"
                >
                    <li
                        v-for="line in props.lines"
                        :key="line"
                        class="rounded-full border border-border bg-card/80 px-3.5 py-1.5 text-sm"
                    >
                        {{ line }}
                    </li>
                </ul>

                <ul class="mt-8 grid gap-3 sm:grid-cols-2">
                    <li v-for="path in paths" :key="path.href">
                        <Link
                            :href="path.href"
                            class="group flex h-full items-start gap-3 rounded-2xl border border-border bg-card p-4 transition-[border-color,box-shadow,translate] duration-300 ease-out hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
                        >
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-primary-foreground"
                                aria-hidden="true"
                            >
                                <component
                                    :is="path.icon"
                                    class="size-5"
                                    :stroke-width="ICON_STROKE"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-center gap-1.5 font-medium"
                                >
                                    {{ path.title }}
                                    <ArrowRight
                                        class="size-4 text-primary transition-transform duration-300 group-hover:translate-x-0.5"
                                        :stroke-width="ICON_STROKE"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span
                                    class="mt-0.5 block text-sm text-muted-foreground"
                                    >{{ path.body }}</span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>

                <p
                    class="mt-5 flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <CalendarCheck
                        class="size-4 text-oak"
                        :stroke-width="ICON_STROKE"
                        aria-hidden="true"
                    />
                    <span>
                        ¿Ya sabes lo que quieres?
                        <Link
                            href="/solicitar"
                            class="font-medium text-primary underline-offset-4 hover:underline"
                            >Cotiza tu proyecto</Link
                        >
                    </span>
                </p>
            </div>

            <div
                class="grid grid-cols-6 gap-3 sm:gap-4 lg:col-span-6"
                aria-hidden="true"
            >
                <div
                    v-for="(name, i) in props.illustrations.slice(0, 3)"
                    :key="name"
                    :class="[
                        'coming-soon__card relative flex items-center justify-center rounded-2xl border border-border/80 bg-background/80 p-4 shadow-xs backdrop-blur sm:p-6',
                        i === 0
                            ? 'col-span-6 aspect-[16/9] sm:col-span-4 sm:row-span-2 sm:aspect-auto'
                            : 'col-span-3 aspect-[4/3] sm:col-span-2',
                    ]"
                    :style="{ '--i': i }"
                >
                    <span
                        class="absolute top-3 left-3 font-mono text-[0.625rem] tracking-[0.18em] text-muted-foreground/70 uppercase"
                        >Plano {{ String(i + 1).padStart(2, '0') }}</span
                    >
                    <LineIllustration
                        :name="name"
                        :delay="i * 180"
                        class="w-full max-w-72 text-foreground/70"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.coming-soon__ping {
    animation: coming-soon-ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes coming-soon-ping {
    75%,
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

.coming-soon__card {
    animation: coming-soon-float 7s ease-in-out infinite;
    animation-delay: calc(var(--i, 0) * -2.3s);
}

@keyframes coming-soon-float {
    50% {
        transform: translate3d(0, -6px, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .coming-soon__ping,
    .coming-soon__card {
        animation: none;
    }
}
</style>
