<script setup lang="ts">
import { ArrowUp, FileText, ListTree } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DateText from '@/components/ds/DateText.vue';
import PageIntro from '@/components/web/public/PageIntro.vue';
import RichText from '@/components/web/public/RichText.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import { ICON_STROKE } from '@/components/web/visual/icons';
import { prefersReducedMotion, scrollToElement } from '@/lib/motion';
import type { PublicSiteData, SeoData } from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    page: {
        title: string;
        slug: string;
        summary: string | null;
        body: string;
        version: number;
        publishedAt: string | null;
    };
}>();

/**
 * Índice generado a partir de los <h2> del documento (ya saneado en el
 * servidor). Se resalta la sección visible y una barra muestra el avance
 * de lectura. Sin títulos, el índice no aparece.
 */
type TocItem = { id: string; label: string };

const body = ref<HTMLElement | null>(null);
const toc = ref<TocItem[]>([]);
const activeId = ref<string | null>(null);
const readProgress = ref(0);
let observer: IntersectionObserver | null = null;
let frame = 0;

function slugify(text: string, used: Set<string>): string {
    const base =
        text
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '')
            .slice(0, 60) || 'seccion';
    let id = base;
    let n = 2;

    while (used.has(id) || document.getElementById(id)) {
        id = `${base}-${n++}`;
    }

    used.add(id);

    return id;
}

async function buildToc() {
    await nextTick();
    observer?.disconnect();

    const root = body.value;

    if (!root) return;

    const used = new Set<string>();
    const headings = Array.from(root.querySelectorAll<HTMLElement>('h2'));

    toc.value = headings.map((h) => {
        if (!h.id) h.id = slugify(h.textContent ?? '', used);
        h.classList.add('scroll-mt-28');

        return { id: h.id, label: (h.textContent ?? '').trim() };
    });

    activeId.value = toc.value[0]?.id ?? null;

    if (!('IntersectionObserver' in window)) return;

    observer = new IntersectionObserver(
        (entries) => {
            const visible = entries
                .filter((e) => e.isIntersecting)
                .sort(
                    (a, b) =>
                        a.boundingClientRect.top - b.boundingClientRect.top,
                );

            if (visible[0]) activeId.value = visible[0].target.id;
        },
        { rootMargin: '-20% 0px -65% 0px' },
    );

    headings.forEach((h) => observer?.observe(h));
}

function measure() {
    frame = 0;
    const root = body.value;

    if (!root) return;

    const rect = root.getBoundingClientRect();
    const total = Math.max(1, rect.height - window.innerHeight * 0.5);
    readProgress.value = Math.min(1, Math.max(0, -rect.top / total));
}

function onScroll() {
    if (!frame) frame = requestAnimationFrame(measure);
}

function go(item: TocItem, event: MouseEvent) {
    const target = document.getElementById(item.id);

    if (!target) return;

    event.preventDefault();
    history.replaceState(history.state, '', `#${item.id}`);
    scrollToElement(target);
    activeId.value = item.id;
}

function toTop() {
    window.scrollTo({
        top: 0,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
}

onMounted(() => {
    void buildToc();
    window.addEventListener('scroll', onScroll, { passive: true });
    measure();
});

watch(() => props.page.body, buildToc);

onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener('scroll', onScroll);
    if (frame) cancelAnimationFrame(frame);
});
</script>

<template>
    <SeoHead :seo="props.seo" />

    <div
        class="pointer-events-none fixed inset-x-0 top-0 z-50 h-[3px] origin-left bg-oak"
        :style="{ transform: `scaleX(${readProgress})` }"
        aria-hidden="true"
    />

    <PageIntro
        :title="props.page.title"
        :lead="props.page.summary"
        :breadcrumbs="[
            { label: 'Inicio', href: '/' },
            { label: props.page.title },
        ]"
    />

    <section class="section-y">
        <div class="container-site grid gap-10 lg:grid-cols-12">
            <aside class="lg:col-span-3">
                <div class="lg:sticky lg:top-28" data-motion="fade-up">
                    <div
                        class="flex items-center gap-3 rounded-xl border border-border bg-card p-4 text-sm"
                    >
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary"
                            aria-hidden="true"
                        >
                            <FileText
                                class="size-5"
                                :stroke-width="ICON_STROKE"
                            />
                        </span>
                        <span class="text-muted-foreground">
                            <span class="block font-medium text-foreground"
                                >Versión {{ props.page.version }}</span
                            >
                            <span v-if="props.page.publishedAt">
                                Actualizado el
                                <DateText :value="props.page.publishedAt" />
                            </span>
                        </span>
                    </div>

                    <nav
                        v-if="toc.length > 1"
                        aria-label="Contenido del documento"
                        class="mt-6"
                    >
                        <p
                            class="mb-3 flex items-center gap-2 text-sm font-medium text-muted-foreground"
                        >
                            <ListTree
                                class="size-4"
                                :stroke-width="ICON_STROKE"
                                aria-hidden="true"
                            />
                            En este documento
                        </p>
                        <ol class="relative border-l border-border">
                            <li v-for="item in toc" :key="item.id">
                                <a
                                    :href="`#${item.id}`"
                                    :aria-current="
                                        activeId === item.id
                                            ? 'location'
                                            : undefined
                                    "
                                    :class="[
                                        '-ml-px block border-l-2 py-1.5 pl-4 text-sm leading-snug transition-[border-color,color] duration-300',
                                        activeId === item.id
                                            ? 'border-primary font-medium text-foreground'
                                            : 'border-transparent text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                                    ]"
                                    @click="go(item, $event)"
                                    >{{ item.label }}</a
                                >
                            </li>
                        </ol>
                        <button
                            type="button"
                            class="group mt-6 inline-flex items-center gap-2 text-sm font-medium text-primary"
                            @click="toTop"
                        >
                            <ArrowUp
                                class="size-4 transition-transform group-hover:-translate-y-0.5"
                                :stroke-width="ICON_STROKE"
                                aria-hidden="true"
                            />
                            Volver al inicio
                        </button>
                    </nav>
                </div>
            </aside>
            <div ref="body" class="lg:col-span-9" data-motion="fade-up">
                <RichText
                    :html="props.page.body"
                    class="max-w-[68ch] text-[1.0625rem] leading-[1.75]"
                />
            </div>
        </div>
    </section>
</template>
