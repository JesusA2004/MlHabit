<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import DataPagination from '@/components/ds/DataPagination.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import CtaBand from '@/components/web/public/CtaBand.vue';
import PageIntro from '@/components/web/public/PageIntro.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import ShowcaseCard from '@/components/web/public/ShowcaseCard.vue';
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
    >
        <div
            class="mt-10 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <ToggleGroup
                type="single"
                :model-value="origin"
                variant="outline"
                class="w-full justify-start overflow-x-auto sm:w-auto"
                aria-label="Filtrar por origen"
                @update:model-value="
                    (v) =>
                        apply({ origen: v && v !== 'todos' ? String(v) : null })
                "
            >
                <ToggleGroupItem value="todos" class="px-4"
                    >Todos</ToggleGroupItem
                >
                <ToggleGroupItem
                    v-for="option in props.originOptions"
                    :key="option.value"
                    :value="option.value"
                    class="px-4"
                    >{{ option.label }}</ToggleGroupItem
                >
            </ToggleGroup>
            <nav
                v-if="props.categories.length"
                aria-label="Filtrar por categoría"
                class="-mx-4 scrollbar-none flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0"
            >
                <button
                    type="button"
                    :class="[
                        'shrink-0 rounded-full border px-4 py-1.5 text-sm transition-colors',
                        category === 'todas'
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border hover:border-foreground/40',
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
                        'shrink-0 rounded-full border px-4 py-1.5 text-sm transition-colors',
                        category === item.slug
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border hover:border-foreground/40',
                    ]"
                    :aria-pressed="category === item.slug"
                    @click="apply({ categoria: item.slug })"
                >
                    {{ item.name }}
                </button>
            </nav>
        </div>
    </PageIntro>

    <section class="section-y" aria-label="Proyectos">
        <div class="container-site">
            <div
                v-if="props.projects.data.length"
                class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                <ShowcaseCard
                    v-for="(project, i) in props.projects.data"
                    :key="project.id"
                    :project="project"
                    :size="
                        i === 0 && props.projects.current_page === 1
                            ? 'lg'
                            : 'md'
                    "
                    :class="
                        i === 0 && props.projects.current_page === 1
                            ? 'sm:col-span-2'
                            : ''
                    "
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

    <CtaBand />
</template>
