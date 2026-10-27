<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import EditorialCard from '@/components/blocks/shared/EditorialCard.vue';
import type { CategorySummary, PostSummary } from '@/types';

const props = defineProps<{
    categories: CategorySummary[];
    activeCategory: string;
    posts: {
        data: PostSummary[];
        currentPage: number;
        lastPage: number;
        nextPageUrl: string | null;
        prevPageUrl: string | null;
        total: number;
    };
}>();

const active = computed(() => props.categories.find((c) => c.slug === props.activeCategory) ?? null);
const featured = computed(() => (props.posts.currentPage === 1 && props.posts.data.length > 3 ? props.posts.data[0] : null));
const rest = computed(() => (featured.value ? props.posts.data.slice(1) : props.posts.data));

function filterHref(slug: string): string {
    return slug === 'todos' ? '/tips' : `/tips?categoria=${slug}`;
}
</script>

<template>
    <Head :title="active ? `Tips de ${active.name}` : 'Tips'">
        <meta head-key="description" name="description" :content="active?.description ?? 'Ideas prácticas sobre negocios, hábitos y vida. Sin tapujos.'" />
    </Head>

    <div class="tone-ink">
        <section class="pt-16 pb-10 lg:pt-24">
            <div class="container-editorial">
                <p class="sn-eyebrow type-eyebrow">Tips · {{ posts.total }} {{ posts.total === 1 ? 'idea' : 'ideas' }}</p>
                <h1 class="type-display-xl mt-5 max-w-4xl text-paper">
                    {{ active ? active.name.toUpperCase() + '.' : 'IDEAS QUE DEJAN HUELLA.' }}
                </h1>
                <p v-if="active?.description" class="type-lead mt-6 max-w-2xl text-mist">{{ active.description }}</p>

                <nav class="scrollbar-none -mx-5 mt-10 flex gap-2 overflow-x-auto px-5 pb-1" aria-label="Filtrar por categoría">
                    <Link
                        v-for="item in [{ slug: 'todos', name: 'Todos', color: '' }, ...categories]"
                        :key="item.slug"
                        :href="filterHref(item.slug)"
                        preserve-scroll
                        class="flex h-11 shrink-0 items-center gap-2 rounded-full border px-5 text-[13px] font-medium tracking-[0.08em] uppercase transition-colors"
                        :class="
                            activeCategory === item.slug
                                ? 'border-gold bg-gold text-ink'
                                : 'border-line text-paper/75 hover:border-gold hover:text-gold'
                        "
                        :aria-current="activeCategory === item.slug ? 'page' : undefined"
                    >
                        <span v-if="item.color" class="size-1.5 rounded-full" :style="{ background: activeCategory === item.slug ? 'var(--color-ink)' : item.color }" aria-hidden="true" />
                        {{ item.name }}
                    </Link>
                </nav>
            </div>
        </section>

        <section class="border-t border-line pt-12 pb-24">
            <div class="container-editorial">
                <p v-if="!posts.data.length" class="sn-empty">
                    Todavía no hay tips en esta categoría. Lo bueno se está escribiendo.
                </p>

                <template v-else>
                    <EditorialCard
                        v-if="featured"
                        :post="featured"
                        variant="overlay"
                        heading-level="h2"
                        class="mb-12"
                    />
                    <div class="sn-posts-grid">
                        <EditorialCard v-for="post in rest" :key="post.id" :post="post" heading-level="h2" />
                    </div>
                </template>

                <nav v-if="posts.lastPage > 1" class="mt-16 flex items-center justify-between gap-4 border-t border-line pt-8" aria-label="Paginación">
                    <Link
                        v-if="posts.prevPageUrl"
                        :href="posts.prevPageUrl"
                        class="btn-editorial btn-editorial--secondary"
                        rel="prev"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" /> Anteriores
                    </Link>
                    <span v-else />
                    <span class="type-caption text-mist">Página {{ posts.currentPage }} de {{ posts.lastPage }}</span>
                    <Link
                        v-if="posts.nextPageUrl"
                        :href="posts.nextPageUrl"
                        class="btn-editorial btn-editorial--secondary"
                        rel="next"
                    >
                        Siguientes <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <span v-else />
                </nav>
            </div>
        </section>
    </div>
</template>
