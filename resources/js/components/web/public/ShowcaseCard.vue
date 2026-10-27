<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, MapPin } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { ICON_STROKE } from '@/components/web/visual/icons';
import { illustrationFor } from '@/components/web/visual/illustrations';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import { cn } from '@/lib/utils';
import type { ShowcaseCardData } from '@/types/web';
import OriginBadge from './OriginBadge.vue';
import ResponsiveImage from './ResponsiveImage.vue';

/**
 * Tarjeta de proyecto de inspiración con etiqueta de origen visible. Al
 * pasar el cursor la imagen se amplía y el resumen sube sobre la foto.
 * `aspect` permite composiciones editoriales (alto/ancho) sin romper el
 * uso anterior (`size`).
 */
const props = withDefaults(
    defineProps<{
        project: ShowcaseCardData;
        size?: 'lg' | 'md';
        aspect?: 'landscape' | 'portrait' | 'square' | 'auto';
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'md', aspect: 'landscape' },
);

const frame = computed(() => {
    if (props.size === 'lg') {
        return 'aspect-[4/3] lg:aspect-auto lg:min-h-[28rem] lg:flex-1';
    }

    return {
        landscape: 'aspect-[4/3]',
        portrait: 'aspect-[4/5]',
        square: 'aspect-square',
        auto: 'aspect-[4/3] lg:aspect-auto lg:flex-1 lg:min-h-[16rem]',
    }[props.aspect];
});

const illustration = computed(() =>
    illustrationFor(props.project.category?.slug, props.project.category?.name),
);
</script>

<template>
    <Link
        :href="props.project.href"
        :class="
            cn(
                'group relative flex flex-col overflow-hidden rounded-2xl bg-card shadow-xs ring-1 ring-border/60 transition-[translate,box-shadow] duration-500 ease-out outline-none hover:-translate-y-1 hover:shadow-lg focus-visible:ring-3 focus-visible:ring-ring/50',
                props.class,
            )
        "
    >
        <div :class="cn('relative overflow-hidden bg-stone', frame)">
            <ResponsiveImage
                v-if="props.project.image"
                :image="props.project.image"
                class="absolute inset-0"
                img-class="transition-[scale] duration-[1.2s] ease-out group-hover:scale-[1.06]"
            />
            <div
                v-else
                class="tex-blueprint absolute inset-0 grid place-items-center bg-linen p-8"
            >
                <LineIllustration
                    :name="illustration"
                    class="w-3/4 max-w-xs text-foreground/70"
                />
            </div>
            <div class="absolute top-3 left-3">
                <OriginBadge
                    :origin="props.project.origin"
                    :label="props.project.originLabel"
                />
            </div>
            <div
                v-if="props.project.image && props.project.summary"
                class="pointer-events-none absolute inset-x-0 bottom-0 hidden translate-y-full bg-gradient-to-t from-black/80 via-black/50 to-transparent p-5 pt-12 text-sm text-white/90 transition-transform duration-500 ease-out group-hover:translate-y-0 group-focus-visible:translate-y-0 [@media(hover:hover)]:block"
                aria-hidden="true"
            >
                <p class="line-clamp-3 max-w-md">{{ props.project.summary }}</p>
            </div>
        </div>
        <div class="flex items-start justify-between gap-4 p-5">
            <div class="min-w-0">
                <p
                    v-if="props.project.category || props.project.locationLabel"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-oak-ink"
                >
                    <span v-if="props.project.category">{{
                        props.project.category.name
                    }}</span>
                    <span
                        v-if="props.project.locationLabel"
                        class="inline-flex items-center gap-1 text-muted-foreground"
                    >
                        <MapPin
                            class="size-3"
                            :stroke-width="ICON_STROKE"
                            aria-hidden="true"
                        />{{ props.project.locationLabel }}
                    </span>
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
                class="mt-1 grid size-9 shrink-0 place-items-center rounded-full border border-border text-foreground transition-[background-color,border-color,color,rotate] duration-500 group-hover:rotate-45 group-hover:border-primary group-hover:bg-primary group-hover:text-primary-foreground"
                aria-hidden="true"
            >
                <ArrowUpRight class="size-4" :stroke-width="ICON_STROKE" />
            </span>
        </div>
    </Link>
</template>
