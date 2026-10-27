<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarCheck,
    Hammer,
    LayoutGrid,
    MessageCircleQuestion,
    Ruler,
    Search,
    ShieldCheck,
    Wallet,
    X,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import { Button } from '@/components/ui/button';
import CtaBand from '@/components/web/public/CtaBand.vue';
import FaqAccordion from '@/components/web/public/FaqAccordion.vue';
import PageIntro from '@/components/web/public/PageIntro.vue';
import SeoHead from '@/components/web/public/SeoHead.vue';
import { ICON_STROKE } from '@/components/web/visual/icons';
import NextStepTeaser from '@/components/web/visual/journey/NextStepTeaser.vue';
import { cn } from '@/lib/utils';
import type { FaqData, PublicSiteData, SeoData } from '@/types/web';

const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    groups: { topic: string; label: string; items: FaqData[] }[];
}>();

const query = ref('');
const topic = ref<string>('todos');

const TOPIC_ICONS: [RegExp, Component][] = [
    [/visita|medic|medida/i, Ruler],
    [/pago|precio|costo|financ|cr[eé]dito|anticipo/i, Wallet],
    [/garant|calidad/i, ShieldCheck],
    [/fabric|instal|proceso|tiempo|entrega/i, Hammer],
    [/cita|asesor|agenda/i, CalendarCheck],
];

function topicIcon(group: { topic: string; label: string }): Component {
    const text = `${group.topic} ${group.label}`;

    return (
        TOPIC_ICONS.find(([p]) => p.test(text))?.[1] ?? MessageCircleQuestion
    );
}

function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

const filtered = computed(() => {
    const q = normalize(query.value.trim());

    return props.groups
        .filter((g) => topic.value === 'todos' || g.topic === topic.value)
        .map((g) => ({
            ...g,
            items:
                q.length < 2
                    ? g.items
                    : g.items.filter((item) =>
                          normalize(`${item.question} ${item.answer}`).includes(
                              q,
                          ),
                      ),
        }))
        .filter((g) => g.items.length > 0);
});

const total = computed(() =>
    filtered.value.reduce((sum, g) => sum + g.items.length, 0),
);
const searching = computed(() => query.value.trim().length >= 2);

/** Al buscar, las coincidencias se abren solas. */
function openIds(items: FaqData[]): string[] | undefined {
    return searching.value ? items.map((i) => String(i.id)) : undefined;
}

function clear() {
    query.value = '';
    topic.value = 'todos';
}
</script>

<template>
    <SeoHead :seo="props.seo" />

    <PageIntro
        eyebrow="Ayuda"
        title="Preguntas frecuentes"
        lead="Respuestas claras sobre el proceso, la visita técnica, los pagos y la garantía."
        :breadcrumbs="[
            { label: 'Inicio', href: '/' },
            { label: 'Preguntas frecuentes' },
        ]"
        journey="proceso"
    >
        <div
            v-if="props.groups.length"
            class="mt-10 max-w-2xl"
            data-motion="fade-up"
            data-motion-delay="300"
            role="search"
        >
            <label for="faq-search" class="sr-only"
                >Buscar en las preguntas</label
            >
            <div class="group relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-5 size-5 -translate-y-1/2 text-muted-foreground transition-colors group-focus-within:text-primary"
                    :stroke-width="ICON_STROKE"
                    aria-hidden="true"
                />
                <input
                    id="faq-search"
                    v-model="query"
                    type="search"
                    autocomplete="off"
                    placeholder="Busca: garantía, visita, pagos…"
                    class="h-14 w-full rounded-full border border-input bg-card pr-14 pl-13 text-base shadow-xs transition-[border-color,box-shadow] outline-none placeholder:text-muted-foreground/80 focus:border-primary focus:ring-4 focus:ring-primary/12 [&::-webkit-search-cancel-button]:hidden"
                    aria-describedby="faq-results"
                />
                <button
                    v-if="query"
                    type="button"
                    class="absolute top-1/2 right-2.5 grid size-10 -translate-y-1/2 place-items-center rounded-full text-muted-foreground transition-colors hover:bg-stone hover:text-foreground"
                    aria-label="Borrar búsqueda"
                    @click="query = ''"
                >
                    <X class="size-4" :stroke-width="ICON_STROKE" />
                </button>
            </div>
        </div>
    </PageIntro>

    <section class="section-y" aria-label="Preguntas por tema">
        <div class="container-site">
            <div
                v-if="props.groups.length > 1"
                class="-mx-4 mb-10 scrollbar-none flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0"
                role="group"
                aria-label="Filtrar por tema"
                data-motion-stagger="60"
                data-motion="fade-up"
            >
                <button
                    type="button"
                    :aria-pressed="topic === 'todos'"
                    :class="
                        cn(
                            'inline-flex h-11 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-medium transition-[background-color,border-color,color] duration-300',
                            topic === 'todos'
                                ? 'border-foreground bg-foreground text-background'
                                : 'border-border bg-card hover:border-foreground/40',
                        )
                    "
                    @click="topic = 'todos'"
                >
                    <LayoutGrid
                        class="size-4"
                        :stroke-width="ICON_STROKE"
                        aria-hidden="true"
                    />
                    Todos los temas
                </button>
                <button
                    v-for="group in props.groups"
                    :key="group.topic"
                    type="button"
                    :aria-pressed="topic === group.topic"
                    :class="
                        cn(
                            'group inline-flex h-11 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-medium transition-[background-color,border-color,color] duration-300',
                            topic === group.topic
                                ? 'border-foreground bg-foreground text-background'
                                : 'border-border bg-card hover:border-foreground/40',
                        )
                    "
                    @click="topic = group.topic"
                >
                    <component
                        :is="topicIcon(group)"
                        class="size-4 transition-transform duration-300 group-hover:-rotate-12"
                        :stroke-width="ICON_STROKE"
                        aria-hidden="true"
                    />
                    {{ group.label }}
                    <span
                        class="rounded-full bg-current/10 px-1.5 text-xs tabular-nums opacity-70"
                        >{{ group.items.length }}</span
                    >
                </button>
            </div>

            <p id="faq-results" class="sr-only" aria-live="polite">
                {{ total }}
                {{
                    total === 1
                        ? 'pregunta encontrada'
                        : 'preguntas encontradas'
                }}
            </p>

            <TransitionGroup
                tag="div"
                class="grid gap-14"
                enter-active-class="transition duration-500 ease-out"
                enter-from-class="opacity-0 translate-y-3"
                leave-active-class="transition duration-200 ease-in absolute"
                leave-to-class="opacity-0"
            >
                <div
                    v-for="group in filtered"
                    :id="`tema-${group.topic}`"
                    :key="group.topic"
                    class="grid scroll-mt-24 gap-6 lg:grid-cols-12"
                >
                    <div class="lg:col-span-4">
                        <div
                            class="flex items-center gap-3 lg:sticky lg:top-28"
                            data-motion="fade-up"
                        >
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary"
                                aria-hidden="true"
                            >
                                <component
                                    :is="topicIcon(group)"
                                    class="size-5"
                                    :stroke-width="ICON_STROKE"
                                />
                            </span>
                            <h2 class="text-display-md">{{ group.label }}</h2>
                        </div>
                    </div>
                    <div class="lg:col-span-8" data-motion="fade-up">
                        <FaqAccordion
                            :key="`${group.topic}-${searching ? query : ''}`"
                            :faqs="group.items"
                            :highlight="query"
                            :default-open="openIds(group.items)"
                        />
                    </div>
                </div>
            </TransitionGroup>

            <div
                v-if="props.groups.length && !filtered.length"
                class="rounded-2xl border border-dashed border-border bg-card px-6 py-14 text-center"
                role="status"
            >
                <MessageCircleQuestion
                    class="mx-auto size-8 text-oak"
                    :stroke-width="1.5"
                    aria-hidden="true"
                />
                <p class="mt-4 font-display text-xl font-semibold">
                    No encontramos “{{ query }}”
                </p>
                <p class="mt-1 text-muted-foreground">
                    Prueba con otra palabra o pregúntale directo a un asesor.
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <Button
                        variant="outline"
                        class="rounded-full"
                        @click="clear"
                    >
                        Ver todas las preguntas
                    </Button>
                    <Button as-child class="rounded-full">
                        <Link href="/solicitar">Pregúntale a un asesor</Link>
                    </Button>
                </div>
            </div>

            <EmptyState
                v-if="!props.groups.length"
                :icon="MessageCircleQuestion"
                title="Pronto publicaremos las preguntas más comunes"
                description="Mientras tanto, escríbenos y con gusto resolvemos tus dudas."
            >
                <Button as-child
                    ><Link href="/solicitar"
                        >Solicita una asesoría</Link
                    ></Button
                >
            </EmptyState>
        </div>
    </section>

    <NextStepTeaser
        step="espacio"
        title="Ahora elige tu espacio"
        body="Recorre las líneas del catálogo y empieza a imaginar tu proyecto."
        href="/catalogo"
        illustration="blueprint"
    />

    <CtaBand
        title="¿No encontraste tu respuesta?"
        body="Cuéntanos tu caso y un asesor te orienta personalmente."
        illustration="tape"
    />
</template>
