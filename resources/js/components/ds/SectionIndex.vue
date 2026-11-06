<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export type SectionIndexItem = {
    /** `id` del elemento de la sección en la página. */
    id: string;
    label: string;
    icon?: Component;
    hint?: string;
};

/**
 * Índice de secciones para pantallas de configuración largas. En ≥1280 px
 * es un riel fijo a la izquierda que resalta la sección visible; en
 * pantallas menores, una tira de accesos horizontal con scroll.
 *
 * Uso: `grid xl:grid-cols-[15rem_minmax(0,1fr)]` con este componente en la
 * primera columna; cada sección lleva `id` y `scroll-mt-24`.
 */
const props = withDefaults(
    defineProps<{
        items: SectionIndexItem[];
        label?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { label: 'Secciones de esta página' },
);

const active = ref(props.items[0]?.id ?? '');
let observer: IntersectionObserver | null = null;
const visible = new Map<string, number>();

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined') {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                visible.set(
                    entry.target.id,
                    entry.isIntersecting ? entry.intersectionRatio : 0,
                );
            }

            // La primera sección (en orden) que esté a la vista.
            const current = props.items.find(
                (item) => (visible.get(item.id) ?? 0) > 0,
            );

            if (current) {
                active.value = current.id;
            }
        },
        { rootMargin: '-80px 0px -45% 0px', threshold: [0, 0.01, 0.5, 1] },
    );

    for (const item of props.items) {
        const el = document.getElementById(item.id);

        if (el) {
            observer.observe(el);
        }
    }
});

onBeforeUnmount(() => observer?.disconnect());

function go(event: MouseEvent, id: string) {
    const el = document.getElementById(id);

    if (!el) {
        return;
    }

    event.preventDefault();
    active.value = id;
    const reduce = window.matchMedia?.(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    el.scrollIntoView({
        behavior: reduce ? 'auto' : 'smooth',
        block: 'start',
    });
    history.replaceState(history.state, '', `#${id}`);
}
</script>

<template>
    <nav
        data-slot="section-index"
        :aria-label="props.label"
        :class="cn('min-w-0 xl:sticky xl:top-20 xl:self-start', props.class)"
    >
        <p
            class="mb-2 hidden px-3 text-xs font-medium text-muted-foreground xl:block"
        >
            En esta página
        </p>
        <ul
            class="-mx-1 flex [scrollbar-width:none] gap-1 overflow-x-auto px-1 pb-1 xl:mx-0 xl:flex-col xl:gap-0.5 xl:overflow-visible xl:px-0 xl:pb-0"
        >
            <li v-for="item in props.items" :key="item.id" class="shrink-0">
                <a
                    :href="`#${item.id}`"
                    :aria-current="active === item.id ? 'location' : undefined"
                    :class="
                        cn(
                            'group flex min-h-10 items-center gap-2.5 rounded-lg border px-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none xl:min-h-11 xl:border-transparent xl:whitespace-normal',
                            active === item.id
                                ? 'border-primary/20 bg-primary-soft text-primary'
                                : 'bg-card text-muted-foreground hover:bg-accent hover:text-foreground xl:bg-transparent',
                        )
                    "
                    @click="go($event, item.id)"
                >
                    <component
                        :is="item.icon"
                        v-if="item.icon"
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="flex min-w-0 flex-col leading-tight">
                        <span>{{ item.label }}</span>
                        <span
                            v-if="item.hint"
                            class="hidden text-xs font-normal text-muted-foreground xl:block"
                            >{{ item.hint }}</span
                        >
                    </span>
                </a>
            </li>
        </ul>
        <div v-if="$slots.default" class="mt-6 hidden xl:block">
            <slot />
        </div>
    </nav>
</template>
