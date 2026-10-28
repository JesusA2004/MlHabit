<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarCheck,
    ChevronsDownUp,
    ChevronsUpDown,
    Hammer,
    LayoutGrid,
    Mail,
    MessageCircle,
    MessageCircleQuestion,
    Phone,
    Ruler,
    Search,
    ShieldCheck,
    Wallet,
    X,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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

/**
 * Preguntas frecuentes: búsqueda (insensible a acentos, resalta y abre las
 * coincidencias), filtro por tema con conteo, "abrir/cerrar todas" y una
 * tarjeta de contacto fija en escritorio para cuando no hay respuesta.
 */
const props = defineProps<{
    site: PublicSiteData;
    seo: SeoData;
    groups: { topic: string; label: string; items: FaqData[] }[];
}>();

const query = ref('');
/** Búsqueda aplicada (con un respiro de 180 ms para no saltar al teclear). */
const applied = ref('');
const topic = ref<string>('todos');

let debounce = 0;
watch(query, (value) => {
    window.clearTimeout(debounce);
    debounce = window.setTimeout(
        () => (applied.value = value),
        value ? 180 : 0,
    );
});
onBeforeUnmount(() => window.clearTimeout(debounce));

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

const searching = computed(() => applied.value.trim().length >= 2);

const filtered = computed(() => {
    const q = normalize(applied.value.trim());

    return props.groups
        .filter((g) => topic.value === 'todos' || g.topic === topic.value)
        .map((g) => ({
            ...g,
            items: searching.value
                ? g.items.filter((item) =>
                      normalize(`${item.question} ${item.answer}`).includes(q),
                  )
                : g.items,
        }))
        .filter((g) => g.items.length > 0);
});

const total = computed(() =>
    filtered.value.reduce((sum, g) => sum + g.items.length, 0),
);
const allCount = computed(() =>
    props.groups.reduce((sum, g) => sum + g.items.length, 0),
);

/** Preguntas abiertas (controlado): coincidencias de búsqueda o "abrir todas". */
const open = ref<string[]>([]);
const visibleIds = computed(() =>
    filtered.value.flatMap((g) => g.items.map((i) => String(i.id))),
);
const allOpen = computed(
    () =>
        visibleIds.value.length > 0 &&
        visibleIds.value.every((id) => open.value.includes(id)),
);

watch(searching, (on) => {
    if (!on) open.value = [];
});
watch(applied, () => {
    if (searching.value) open.value = [...visibleIds.value];
});

function toggleAll() {
    open.value = allOpen.value ? [] : [...visibleIds.value];
}

function openFor(group: { items: FaqData[] }) {
    const ids = new Set(group.items.map((i) => String(i.id)));

    return open.value.filter((id) => ids.has(id));
}

function setOpenFor(group: { items: FaqData[] }, next: string[] | undefined) {
    const ids = new Set(group.items.map((i) => String(i.id)));
    open.value = [...open.value.filter((id) => !ids.has(id)), ...(next ?? [])];
}

function pickTopic(value: string) {
    topic.value = value;
}

function suggest(word: string) {
    query.value = word;
}

function clear() {
    query.value = '';
    applied.value = '';
    topic.value = 'todos';
}

const SUGGESTIONS = ['garantía', 'visita', 'anticipo', 'tiempo de entrega'];

const contact = computed(() => props.site?.contact ?? {});
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
        visual="blueprint"
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
                    enterkeyhint="search"
                    :placeholder="`Busca entre ${allCount} respuestas…`"
                    class="h-14 w-full rounded-full border border-input bg-card pr-14 pl-13 text-base shadow-xs transition-[border-color,box-shadow] outline-none placeholder:text-muted-foreground/80 focus:border-primary focus:ring-4 focus:ring-primary/12 [&::-webkit-search-cancel-button]:hidden"
                    aria-describedby="faq-results"
                    @keydown.esc="query = ''"
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
            <p
                class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5 pl-1 text-sm text-muted-foreground"
            >
                <span>Lo más buscado:</span>
                <button
                    v-for="word in SUGGESTIONS"
                    :key="word"
                    type="button"
                    class="inline-flex min-h-8 items-center rounded-full border border-border bg-card/70 px-3 text-foreground transition-colors hover:border-primary hover:text-primary"
                    @click="suggest(word)"
                >
                    {{ word }}
                </button>
            </p>
        </div>
    </PageIntro>

    <section class="section-y" aria-label="Preguntas por tema">
        <div
            v-if="props.groups.length"
            class="container-site grid gap-10 lg:grid-cols-12 lg:gap-12"
        >
            <!-- Temas + contacto (fijos en escritorio) -->
            <aside class="lg:col-span-4">
                <div class="space-y-6 lg:sticky lg:top-28">
                    <nav
                        v-if="props.groups.length > 1"
                        aria-label="Filtrar por tema"
                    >
                        <p
                            class="mb-3 hidden text-sm font-medium text-muted-foreground lg:block"
                        >
                            Temas
                        </p>
                        <ul
                            class="-mx-4 scrollbar-none flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0 lg:flex-col lg:gap-1"
                            data-motion-stagger="50"
                            data-motion="fade-up"
                        >
                            <li
                                v-for="item in [
                                    {
                                        topic: 'todos',
                                        label: 'Todos los temas',
                                        count: allCount,
                                        icon: LayoutGrid,
                                    },
                                    ...props.groups.map((g) => ({
                                        topic: g.topic,
                                        label: g.label,
                                        count: g.items.length,
                                        icon: topicIcon(g),
                                    })),
                                ]"
                                :key="item.topic"
                                class="shrink-0"
                            >
                                <button
                                    type="button"
                                    :aria-pressed="topic === item.topic"
                                    :class="
                                        cn(
                                            'group flex h-11 w-full items-center gap-2.5 rounded-full border px-4 text-sm font-medium whitespace-nowrap transition-[background-color,border-color,color] duration-300 lg:rounded-xl lg:border-transparent lg:px-3',
                                            topic === item.topic
                                                ? 'border-foreground bg-foreground text-background lg:border-transparent lg:bg-primary-soft lg:text-primary'
                                                : 'border-border bg-card hover:border-foreground/40 lg:bg-transparent lg:hover:bg-stone/70',
                                        )
                                    "
                                    @click="pickTopic(item.topic)"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4 shrink-0 transition-transform duration-300 group-hover:-rotate-12"
                                        :stroke-width="ICON_STROKE"
                                        aria-hidden="true"
                                    />
                                    <span class="lg:flex-1 lg:text-left">{{
                                        item.label
                                    }}</span>
                                    <span
                                        class="rounded-full bg-current/10 px-1.5 text-xs tabular-nums opacity-70"
                                        >{{ item.count }}</span
                                    >
                                </button>
                            </li>
                        </ul>
                    </nav>

                    <div
                        class="hidden overflow-hidden rounded-2xl bg-espresso p-6 text-espresso-foreground lg:block"
                        data-motion="fade-up"
                        data-spotlight
                    >
                        <p class="font-display text-xl font-semibold">
                            ¿Tu duda es más específica?
                        </p>
                        <p class="mt-2 text-sm text-espresso-foreground/75">
                            Un asesor te responde sobre tu espacio, tiempos y
                            presupuesto.
                        </p>
                        <div class="mt-5 flex flex-col gap-2">
                            <Link
                                href="/solicitar"
                                class="btn-nudge inline-flex h-11 items-center justify-center gap-2 rounded-full bg-white px-5 text-sm font-medium text-espresso transition-colors hover:bg-linen"
                            >
                                Pregúntale a un asesor
                                <ArrowRight
                                    class="nudge size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                            <a
                                v-if="contact.whatsappHref"
                                :href="contact.whatsappHref"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-white/20 px-5 text-sm font-medium transition-colors hover:bg-white/10"
                            >
                                <MessageCircle
                                    class="size-4"
                                    :stroke-width="ICON_STROKE"
                                    aria-hidden="true"
                                />
                                WhatsApp
                            </a>
                            <a
                                v-else-if="contact.phoneHref"
                                :href="contact.phoneHref"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-full border border-white/20 px-5 text-sm font-medium transition-colors hover:bg-white/10"
                            >
                                <Phone
                                    class="size-4"
                                    :stroke-width="ICON_STROKE"
                                    aria-hidden="true"
                                />
                                {{ contact.phone }}
                            </a>
                            <a
                                v-if="contact.emailHref"
                                :href="contact.emailHref"
                                class="inline-flex min-h-10 items-center justify-center gap-2 text-sm text-espresso-foreground/80 underline-offset-4 hover:text-white hover:underline"
                            >
                                <Mail
                                    class="size-4"
                                    :stroke-width="ICON_STROKE"
                                    aria-hidden="true"
                                />
                                {{ contact.email }}
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Respuestas -->
            <div class="min-w-0 lg:col-span-8">
                <div
                    class="mb-8 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4"
                >
                    <p
                        id="faq-results"
                        class="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        <span class="font-medium text-foreground tabular-nums">
                            {{ total }}
                        </span>
                        {{ total === 1 ? 'respuesta' : 'respuestas' }}
                        <template v-if="searching">
                            para “{{ applied.trim() }}”</template
                        >
                    </p>
                    <button
                        v-if="total"
                        type="button"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-full px-3 text-sm font-medium text-primary transition-colors hover:bg-primary-soft"
                        @click="toggleAll"
                    >
                        <component
                            :is="allOpen ? ChevronsDownUp : ChevronsUpDown"
                            class="size-4"
                            :stroke-width="ICON_STROKE"
                            aria-hidden="true"
                        />
                        {{ allOpen ? 'Cerrar todas' : 'Abrir todas' }}
                    </button>
                </div>

                <div class="grid gap-12">
                    <section
                        v-for="group in filtered"
                        :id="`tema-${group.topic}`"
                        :key="group.topic"
                        class="faq-group scroll-mt-28"
                        :aria-labelledby="`tema-${group.topic}-titulo`"
                    >
                        <div class="mb-4 flex items-center gap-3">
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary"
                                aria-hidden="true"
                            >
                                <component
                                    :is="topicIcon(group)"
                                    class="size-5"
                                    :stroke-width="ICON_STROKE"
                                />
                            </span>
                            <h2
                                :id="`tema-${group.topic}-titulo`"
                                class="font-display text-2xl font-semibold tracking-tight"
                            >
                                {{ group.label }}
                            </h2>
                            <span
                                class="ml-auto text-sm text-muted-foreground tabular-nums"
                                >{{ group.items.length }}</span
                            >
                        </div>
                        <FaqAccordion
                            :faqs="group.items"
                            :highlight="searching ? applied : null"
                            :open="openFor(group)"
                            @update:open="(v) => setOpenFor(group, v)"
                        />
                    </section>
                </div>

                <div
                    v-if="!filtered.length"
                    class="rounded-2xl border border-dashed border-border bg-card px-6 py-14 text-center"
                    role="status"
                >
                    <MessageCircleQuestion
                        class="mx-auto size-8 text-oak"
                        :stroke-width="1.5"
                        aria-hidden="true"
                    />
                    <p class="mt-4 font-display text-xl font-semibold">
                        No encontramos “{{ applied.trim() }}”
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        Prueba con otra palabra o pregúntale directo a un
                        asesor.
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
                            <Link href="/solicitar"
                                >Pregúntale a un asesor</Link
                            >
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="container-site">
            <EmptyState
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

<style scoped>
/* Al filtrar, cada grupo entra suave (solo opacidad/transform, sin saltos). */
.faq-group {
    animation: faq-in 420ms var(--motion-ease, ease-out) both;
}

@keyframes faq-in {
    from {
        opacity: 0;
        transform: translate3d(0, 8px, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .faq-group {
        animation: none;
    }
}
</style>
