<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { categoryIcon, ICON_STROKE } from '@/components/web/visual/icons';
import { illustrationFor } from '@/components/web/visual/illustrations';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import { cn } from '@/lib/utils';
import type { CategoryCardData } from '@/types/web';
import ResponsiveImage from './ResponsiveImage.vue';

/**
 * Mosaico de categoría para la cuadrícula tipo bento: imagen a sangre con
 * degradado, icono de la línea y nombre; el texto de apoyo se descubre al
 * pasar el cursor o con el foco (en táctil siempre está visible). Sin
 * imagen, un bloque de color con la ilustración de línea de la categoría.
 */
const props = withDefaults(
    defineProps<{
        category: CategoryCardData;
        index?: number;
        large?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { index: 0, large: false },
);

const fallbacks = [
    'bg-espresso text-espresso-foreground',
    'bg-primary text-primary-foreground',
    'bg-oak text-white',
    'bg-stone text-foreground',
];

const icon = computed(() =>
    categoryIcon(props.category.slug, props.category.name),
);
const illustration = computed(() =>
    illustrationFor(props.category.slug, props.category.name),
);
const lightBlock = computed(
    () => !props.category.image && props.index % fallbacks.length === 3,
);
</script>

<template>
    <Link
        :href="props.category.href"
        data-tilt="4"
        :class="
            cn(
                'group relative isolate flex min-h-56 flex-col justify-end overflow-hidden rounded-2xl p-5 outline-none focus-visible:ring-3 focus-visible:ring-ring/50 sm:p-6',
                props.category.image
                    ? 'bg-espresso text-white'
                    : fallbacks[props.index % fallbacks.length],
                props.class,
            )
        "
    >
        <template v-if="props.category.image">
            <ResponsiveImage
                :image="props.category.image"
                :alt="''"
                class="absolute inset-0 -z-10"
                img-class="transition-[scale] duration-[1.2s] ease-out group-hover:scale-[1.07] group-focus-visible:scale-[1.07]"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-t from-black/75 via-black/25 to-black/0 transition-opacity duration-500 group-hover:opacity-90"
                aria-hidden="true"
            />
        </template>
        <template v-else>
            <div
                :class="
                    cn(
                        'pointer-events-none absolute inset-0 -z-10',
                        lightBlock ? 'tex-blueprint' : 'tex-blueprint-dark',
                    )
                "
                aria-hidden="true"
            />
            <LineIllustration
                :name="illustration"
                :tone="lightBlock ? 'light' : 'dark'"
                :class="
                    cn(
                        'absolute top-6 right-4 -z-10 transition-transform duration-700 ease-out group-hover:-translate-y-1 group-hover:scale-105',
                        props.large ? 'w-[min(70%,26rem)]' : 'w-[62%]',
                        lightBlock
                            ? 'text-foreground/70'
                            : 'text-current opacity-70',
                    )
                "
            />
        </template>

        <span
            :class="
                cn(
                    'absolute top-5 left-5 inline-flex items-center gap-2 rounded-full py-1 pr-3 pl-1 text-xs font-medium backdrop-blur-md transition-colors duration-300 sm:top-6 sm:left-6',
                    lightBlock
                        ? 'bg-white/70 text-foreground'
                        : props.category.image
                          ? 'bg-black/45 text-white group-hover:bg-black/60'
                          : 'bg-white/15 text-current group-hover:bg-white/25',
                )
            "
        >
            <span
                :class="
                    cn(
                        'grid size-7 place-items-center rounded-full transition-transform duration-500 group-hover:-rotate-12',
                        lightBlock
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-white text-espresso',
                    )
                "
                aria-hidden="true"
            >
                <component
                    :is="icon"
                    class="size-4"
                    :stroke-width="ICON_STROKE"
                />
            </span>
            <template v-if="props.category.productsCount">
                {{ props.category.productsCount }}
                {{ props.category.productsCount === 1 ? 'modelo' : 'modelos' }}
            </template>
            <template v-else>A la medida</template>
        </span>

        <div class="flex items-end justify-between gap-4">
            <div class="min-w-0">
                <h3
                    :class="
                        cn(
                            'font-display font-semibold tracking-tight transition-transform duration-500 ease-out',
                            props.large
                                ? 'text-3xl sm:text-5xl'
                                : 'text-xl sm:text-2xl',
                        )
                    "
                >
                    {{ props.category.name }}
                </h3>
                <div
                    v-if="props.category.tagline"
                    :class="
                        cn(
                            'grid transition-[grid-template-rows,opacity] duration-500 ease-out',
                            props.large
                                ? 'grid-rows-[1fr]'
                                : 'grid-rows-[1fr] group-hover:grid-rows-[1fr] group-hover:opacity-100 group-focus-visible:grid-rows-[1fr] group-focus-visible:opacity-100 [@media(hover:hover)]:grid-rows-[0fr] [@media(hover:hover)]:opacity-0',
                        )
                    "
                >
                    <p class="min-h-0 overflow-hidden">
                        <span
                            :class="
                                cn(
                                    'block max-w-sm pt-1.5 text-sm opacity-85',
                                    props.large && 'text-base',
                                    !props.large && 'line-clamp-2',
                                )
                            "
                            >{{ props.category.tagline }}</span
                        >
                    </p>
                </div>
            </div>
            <span
                :class="
                    cn(
                        'grid size-11 shrink-0 place-items-center rounded-full backdrop-blur-sm transition-[background-color,color,rotate,scale] duration-500 ease-out group-hover:scale-110 group-hover:rotate-45',
                        lightBlock
                            ? 'bg-foreground text-background group-hover:bg-primary'
                            : 'bg-white/15 group-hover:bg-white group-hover:text-espresso',
                    )
                "
                aria-hidden="true"
            >
                <ArrowUpRight class="size-4" :stroke-width="1.75" />
            </span>
        </div>
    </Link>
</template>
