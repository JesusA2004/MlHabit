<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Camera, PenTool, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import DataPagination from '@/components/ds/DataPagination.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import { Button } from '@/components/ui/button';
import CtaBand from '@/components/web/public/CtaBand.vue';
import PageIntro from '@/components/web/public/PageIntro.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import ShowcaseCard from '@/components/web/public/ShowcaseCard.vue';
import { categoryIcon, ICON_STROKE } from '@/components/web/visual/icons';
import NextStepTeaser from '@/components/web/visual/journey/NextStepTeaser.vue';
import { cn } from '@/lib/utils';
import type {
    PublicSiteData,
    SeoData,
    ShowcaseCardData,
    SimplePaginator,
    ToneOption,
} from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    projects: SimplePaginator<ShowcaseCardData>;
    filters: { origen: string | null; categoria: string | null };
    categories: { name: string; slug: string }[];
    originOptions: ToneOption[];
}>();

const origin = computed(() => props.filters.origen ?? 'todos');
const category = computed(() => props.filters.categoria ?? 'todas');

function apply(next: { origen?: string | null; categoria?: string | null }) {
    const query: Record<string, string> = {};
    const o = next.origen !== undefined ? next.origen : props.filters.origen;
    const c =
        next.categoria !== undefined ? next.categoria : props.filters.categoria;
    if (o) query.origen = o;
    if (c) query.categoria = c;
    router.get('/inspiracion', query, {
        preserveScroll: true,
        preserveState: true,
        only: ['projects', 'filters', 'seo'],
    });
}

const originChoices = computed(() => [
    { value: 'todos', label: 'Todos', icon: Sparkles },
    ...props.originOptions.map((o) => ({
        value: o.value,
        label: o.label,
        icon: o.value === 'real_photo' ? Camera : PenTool,
    })),
]);

/**
 * Composición editorial en bloques de cinco: una pieza ancha con otra alta
 * a su lado, y tres piezas en fila. Mantiene el orden de lectura.
 */
function layout(i: number): {
    class: string;
    aspect: 'landscape' | 'auto';
    size: 'lg' | 'md';
} {
    const firstPage = props.projects.current_page === 1;

    if (i === 0 && firstPage) {
        return {
            class: 'sm:col-span-2 lg:col-span-4',
            aspect: 'landscape',
            size: 'lg',
        };
    }

    const slot = i % 5;

    if (slot === 0) {
        return {
            class: 'sm:col-span-2 lg:col-span-4',
            aspect: 'landscape',
            size: 'md',
        };
    }

    if (slot === 1) {
        return { class: 'lg:col-span-2', aspect: 'auto', size: 'md' };
    }

    return { class: 'lg:col-span-2', aspect: 'landscape', size: 'md' };
}

const filterKey = computed(
    () =>
        `${props.filters.origen}-${props.filters.categoria}-${props.projects.current_page}`,
);
</script>

<template>
    <SeoHead :seo="props.seo" />

    <PageIntro
        eyebrow="Inspiración"
        title="Proyectos y propuestas para imaginar tu espacio"
        lead="Distinguimos siempre los proyectos que ya entregamos de los renders conceptuales, para que sepas exactamente qué estás viendo."
        :breadcrumbs="[
            { label: 'Inicio', href: '/' },
            { label: 'Inspiración' },
        ]"
        journey="inspirate"
    >
        <div
            class="mt-10 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            data-motion="fade-up"
            data-motion-delay="300"
        >
            <div
                role="group"
                aria-label="Filtrar por origen"
                class="inline-flex w-full gap-1 overflow-x-auto rounded-full border border-border bg-card p-1 sm:w-auto"
            >
                <button
                    v-for="option in originChoices"
                    :key="option.value"
                    type="button"
                    :aria-pressed="origin === option.value"
                    :class="
                        cn(
                            'relative inline-flex h-10 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-medium transition-[background-color,color] duration-300',
                            origin === option.value
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:bg-stone hover:text-foreground',
                        )
                    "
                    @click="
                        apply({
                            origen:
                                option.value === 'todos' ? null : option.value,
                        })
                    "
                >
                    <component
                        :is="option.icon"
                        class="size-4"
                        :stroke-width="ICON_STROKE"
                        aria-hidden="true"
                    />
                    {{ option.label }}
                </button>
            </div>
            <nav
                v-if="props.categories.length"
                aria-label="Filtrar por categoría"
                class="-mx-4 scrollbar-none flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0"
            >
                <button
                    type="button"
                    :class="[
                        'inline-flex h-10 shrink-0 items-center rounded-full border px-4 text-sm transition-[background-color,border-color,color] duration-300',
                        category === 'todas'
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border bg-card hover:border-foreground/40',
                    ]"
                    :aria-pressed="category === 'todas'"
                    @click="apply({ categoria: null })"
                >
                    Todas
                </button>
                <button
                    v-for="item in props.categories"
                    :key="item.slug"
                    type="button"
                    :class="[
                        'group inline-flex h-10 shrink-0 items-center gap-2 rounded-full border px-4 text-sm transition-[background-color,border-color,color] duration-300',
                        category === item.slug
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border bg-card hover:border-foreground/40',
                    ]"
                    :aria-pressed="category === item.slug"
                    @click="apply({ categoria: item.slug })"
                >
                    <component
                        :is="categoryIcon(item.slug, item.name)"
                        class="size-4 transition-transform duration-300 group-hover:-rotate-12"
                        :stroke-width="ICON_STROKE"
                        aria-hidden="true"
                    />
                    {{ item.name }}
                </button>
            </nav>
        </div>
    </PageIntro>

    <section class="section-y" aria-label="Proyectos">
        <div class="container-site">
            <p
                class="mb-6 text-sm text-muted-foreground tabular-nums"
                aria-live="polite"
            >
                <template v-if="props.projects.total">
                    {{ props.projects.total }}
                    {{ props.projects.total === 1 ? 'proyecto' : 'proyectos' }}
                </template>
            </p>
            <div
                v-if="props.projects.data.length"
                :key="filterKey"
                class="grid gap-5 sm:grid-cols-2 lg:grid-cols-6"
                data-motion-stagger="80"
                data-motion="fade-up"
            >
                <ShowcaseCard
                    v-for="(project, i) in props.projects.data"
                    :key="project.id"
                    :project="project"
                    :size="layout(i).size"
                    :aspect="layout(i).aspect"
                    :class="layout(i).class"
                />
            </div>
            <EmptyState
                v-else
                :icon="Sparkles"
                title="Aún no hay proyectos con estos filtros"
                description="Prueba con otra categoría u origen, o cuéntanos tu idea y la diseñamos contigo."
            >
                <Button as-child variant="outline"
                    ><Link href="/inspiracion">Ver todos</Link></Button
                >
            </EmptyState>
            <DataPagination
                v-if="props.projects.last_page > 1"
                :paginator="props.projects"
                item-label="proyectos"
                class="mt-12"
            />
        </div>
    </section>

    <NextStepTeaser
        step="espacio"
        title="Elige el espacio que quieres renovar"
        body="Recorre el catálogo por línea: cocinas, clósets, recámaras y más."
        href="/catalogo"
        illustration="closet"
    />

    <CtaBand />
</template>
