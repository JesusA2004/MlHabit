<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import { cn } from '@/lib/utils';
import type { CategoryCardData } from '@/types/web';
import ResponsiveImage from './ResponsiveImage.vue';

/**
 * Mosaico de categoría para la cuadrícula tipo bento: imagen a sangre con
 * degradado y nombre; sin imagen, un bloque de color con las muestras de
 * acabados de la marca.
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
</script>

<template>
    <Link
        :href="props.category.href"
        :class="
            cn(
                'group relative isolate flex min-h-56 flex-col justify-end overflow-hidden rounded-2xl p-5 outline-none focus-visible:ring-3 focus-visible:ring-ring/50 sm:p-6',
                props.category.image
                    ? 'text-white'
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
                img-class="transition-transform duration-700 ease-out group-hover:scale-[1.04]"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-t from-black/70 via-black/20 to-transparent"
                aria-hidden="true"
            />
        </template>
        <FinishSwatches
            v-else
            :tone="props.index % fallbacks.length === 3 ? 'light' : 'dark'"
            :labels="false"
            class="absolute -top-4 -right-6 -z-10 w-44 rotate-6 opacity-80 transition-transform duration-700 group-hover:rotate-3"
        />

        <div class="flex items-end justify-between gap-4">
            <div class="min-w-0">
                <h3
                    :class="
                        cn(
                            'font-display font-semibold tracking-tight',
                            props.large
                                ? 'text-3xl sm:text-4xl'
                                : 'text-xl sm:text-2xl',
                        )
                    "
                >
                    {{ props.category.name }}
                </h3>
                <p
                    v-if="props.category.tagline"
                    :class="
                        cn(
                            'mt-1.5 max-w-sm text-sm opacity-85',
                            !props.large && 'line-clamp-2',
                        )
                    "
                >
                    {{ props.category.tagline }}
                </p>
            </div>
            <span
                class="grid size-10 shrink-0 place-items-center rounded-full bg-white/15 backdrop-blur-sm transition-colors group-hover:bg-white group-hover:text-espresso"
                aria-hidden="true"
            >
                <ArrowUpRight class="size-4" />
            </span>
        </div>
    </Link>
</template>
