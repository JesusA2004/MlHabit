<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import Money from '@/components/ds/Money.vue';
import type { ProductCardData } from '@/types/web';
import ResponsiveImage from './ResponsiveImage.vue';

/** Tarjeta de producto: imagen, nombre, resumen y precio "desde" solo si se publica. */
const props = defineProps<{ product: ProductCardData }>();
</script>

<template>
    <Link
        :href="props.product.href"
        class="group flex flex-col rounded-2xl outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
    >
        <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-stone">
            <ResponsiveImage
                v-if="props.product.image"
                :image="props.product.image"
                class="absolute inset-0"
                img-class="transition-transform duration-700 ease-out group-hover:scale-[1.03]"
            />
            <div v-else class="absolute inset-0 grid place-items-center">
                <FinishSwatches class="w-36" :labels="false" />
            </div>
        </div>
        <div class="flex items-start justify-between gap-3 pt-4">
            <div class="min-w-0">
                <h3 class="font-display text-lg font-semibold tracking-tight">
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
            <ArrowRight
                class="mt-1.5 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-primary"
                aria-hidden="true"
            />
        </div>
    </Link>
</template>
