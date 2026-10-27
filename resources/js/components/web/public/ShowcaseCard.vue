<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import { cn } from '@/lib/utils';
import type { ShowcaseCardData } from '@/types/web';
import OriginBadge from './OriginBadge.vue';
import ResponsiveImage from './ResponsiveImage.vue';

/** Tarjeta de proyecto de inspiración con etiqueta de origen visible. */
const props = withDefaults(
    defineProps<{
        project: ShowcaseCardData;
        size?: 'lg' | 'md';
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'md' },
);
</script>

<template>
    <Link
        :href="props.project.href"
        :class="
            cn(
                'group relative flex flex-col overflow-hidden rounded-2xl bg-stone outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                props.class,
            )
        "
    >
        <div
            :class="
                cn(
                    'relative overflow-hidden',
                    props.size === 'lg'
                        ? 'aspect-[4/3] lg:aspect-auto lg:min-h-[28rem] lg:flex-1'
                        : 'aspect-[4/3]',
                )
            "
        >
            <ResponsiveImage
                v-if="props.project.image"
                :image="props.project.image"
                class="absolute inset-0"
                img-class="transition-transform duration-700 ease-out group-hover:scale-[1.03]"
            />
            <div
                v-else
                class="absolute inset-0 grid place-items-center bg-linen"
            >
                <FinishSwatches class="w-40" :labels="false" />
            </div>
            <div class="absolute top-3 left-3">
                <OriginBadge
                    :origin="props.project.origin"
                    :label="props.project.originLabel"
                />
            </div>
        </div>
        <div class="flex items-start justify-between gap-4 bg-card p-5">
            <div class="min-w-0">
                <p
                    v-if="props.project.category"
                    class="text-xs font-medium text-oak-ink"
                >
                    {{ props.project.category.name }}
                </p>
                <h3
                    :class="
                        cn(
                            'mt-1 font-display font-semibold tracking-tight text-foreground',
                            props.size === 'lg' ? 'text-2xl' : 'text-lg',
                        )
                    "
                >
                    {{ props.project.title }}
                </h3>
                <p
                    v-if="props.project.summary && props.size === 'lg'"
                    class="mt-2 line-clamp-2 text-sm text-muted-foreground"
                >
                    {{ props.project.summary }}
                </p>
            </div>
            <span
                class="mt-1 grid size-9 shrink-0 place-items-center rounded-full border border-border text-foreground transition-colors group-hover:border-primary group-hover:bg-primary group-hover:text-primary-foreground"
                aria-hidden="true"
            >
                <ArrowUpRight class="size-4" />
            </span>
        </div>
    </Link>
</template>
