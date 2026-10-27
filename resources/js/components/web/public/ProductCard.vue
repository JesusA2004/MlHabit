<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Eye } from '@lucide/vue';
import { computed } from 'vue';
import Money from '@/components/ds/Money.vue';
import { ICON_STROKE } from '@/components/web/visual/icons';
import { illustrationFor } from '@/components/web/visual/illustrations';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import type { ProductCardData } from '@/types/web';
import ResponsiveImage from './ResponsiveImage.vue';

/**
 * Tarjeta de producto: imagen que se eleva y amplía al pasar el cursor, una
 * pastilla "Ver modelo" que sube desde abajo, nombre, resumen y precio
 * "desde" solo si se publica.
 */
const props = defineProps<{ product: ProductCardData }>();

const illustration = computed(() =>
    illustrationFor(props.product.category?.slug, props.product.category?.name),
);
</script>

<template>
    <Link
        :href="props.product.href"
        class="group flex flex-col rounded-2xl outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
    >
        <div
            class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-stone shadow-xs transition-[translate,box-shadow] duration-500 ease-out group-hover:-translate-y-1.5 group-hover:shadow-lg"
        >
            <ResponsiveImage
                v-if="props.product.image"
                :image="props.product.image"
                class="absolute inset-0"
                img-class="transition-[scale] duration-[1.1s] ease-out group-hover:scale-[1.06]"
            />
            <div
                v-else
                class="tex-blueprint absolute inset-0 grid place-items-center bg-linen p-8"
            >
                <LineIllustration
                    :name="illustration"
                    class="w-full text-foreground/70"
                />
            </div>
            <div
                class="pointer-events-none absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/35 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                aria-hidden="true"
            />
            <span
                class="absolute bottom-4 left-4 inline-flex translate-y-3 items-center gap-2 rounded-full bg-white/95 px-3.5 py-2 text-sm font-medium text-espresso opacity-0 shadow-sm backdrop-blur transition-[translate,opacity] duration-500 ease-out group-hover:translate-y-0 group-hover:opacity-100 group-focus-visible:translate-y-0 group-focus-visible:opacity-100"
                aria-hidden="true"
            >
                <Eye class="size-4" :stroke-width="ICON_STROKE" />
                Ver modelo
            </span>
        </div>
        <div class="flex items-start justify-between gap-3 pt-4">
            <div class="min-w-0">
                <h3
                    class="font-display text-lg font-semibold tracking-tight transition-colors duration-300 group-hover:text-primary"
                >
                    {{ props.product.name }}
                </h3>
                <p
                    v-if="props.product.summary"
                    class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                >
                    {{ props.product.summary }}
                </p>
                <p
                    v-if="props.product.price"
                    class="mt-2 text-sm text-foreground"
                >
                    Desde
                    <Money
                        :value="props.product.price.from"
                        :decimals="false"
                        class="font-semibold"
                    />
                </p>
            </div>
            <span
                class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full border border-border text-muted-foreground transition-[background-color,border-color,color,translate] duration-300 group-hover:translate-x-1 group-hover:border-primary group-hover:bg-primary group-hover:text-primary-foreground"
                aria-hidden="true"
            >
                <ArrowRight class="size-4" :stroke-width="ICON_STROKE" />
            </span>
        </div>
    </Link>
</template>
