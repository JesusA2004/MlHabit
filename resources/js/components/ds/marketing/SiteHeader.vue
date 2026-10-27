<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    ChevronDown,
    Menu,
    Phone,
    X,
} from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { categoryIcon, ICON_STROKE } from '@/components/web/visual/icons';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import { illustrationFor } from '@/components/web/visual/illustrations';
import {
    defaultSiteNavigation,
    isActivePath,
    normalizePath,
} from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { SiteData, SiteNavLink } from '@/types/public';

/**
 * Cabecera del sitio público.
 * - Con `overlay` es transparente sobre el hero y se vuelve sólida con
 *   desenfoque al hacer scroll; se encoge, se esconde al bajar y reaparece
 *   al subir (nunca con un menú abierto o con el foco dentro).
 * - Indicador activo que se desliza entre enlaces.
 * - Mega-menú de Catálogo (escritorio) con categorías e iconos.
 * - Menú móvil a pantalla completa con entrada escalonada.
 */
const props = withDefaults(
    defineProps<{
        site?:
            | (Partial<SiteData> & {
                  categories?: { label: string; href: string }[];
              })
            | null;
        navigation?: SiteNavLink[];
        overlay?: boolean;
        ctaLabel?: string;
        ctaHref?: string;
    }>(),
    { overlay: false, ctaLabel: 'Solicitar asesoría', ctaHref: '/solicitar' },
);

const page = usePage();
const menuOpen = ref(false);
const megaOpen = ref(false);
const scrolled = ref(false);
const hidden = ref(false);
const focusWithin = ref(false);

const links = computed(() =>
    props.navigation?.length
        ? props.navigation
        : props.site?.navigation?.length
          ? props.site.navigation
          : defaultSiteNavigation,
);

const categories = computed(() => props.site?.categories ?? []);

const transparent = computed(
    () =>
        props.overlay && !scrolled.value && !menuOpen.value && !megaOpen.value,
);
const phone = computed(() => props.site?.contact?.phone ?? null);
const phoneHref = computed(() =>
    phone.value ? `tel:${phone.value.replace(/\s+/g, '')}` : null,
);

function active(href: string): boolean {
    return !href.includes('#') && isActivePath(page.url, { href });
}

function hasMega(link: SiteNavLink): boolean {
    return (
        normalizePath(link.href) === '/catalogo' && categories.value.length > 0
    );
}

/* ── Scroll: sólida, encogida y escondida al bajar ─────────────── */
let lastY = 0;
let frame = 0;

function onScroll() {
    if (frame) return;

    frame = requestAnimationFrame(() => {
        frame = 0;
        const y = window.scrollY;
        scrolled.value = y > 24;

        const goingDown = y > lastY + 4;
        const goingUp = y < lastY - 4;

        if (
            goingDown &&
            y > 180 &&
            !menuOpen.value &&
            !megaOpen.value &&
            !focusWithin.value
        ) {
            hidden.value = true;
        } else if (goingUp || y <= 180) {
            hidden.value = false;
        }

        if (Math.abs(y - lastY) > 4) lastY = y;
    });
}

onMounted(() => {
    lastY = window.scrollY;
    scrolled.value = lastY > 24;
    window.addEventListener('scroll', onScroll, { passive: true });
    void nextTick(moveIndicator);
    window.addEventListener('resize', moveIndicator, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', moveIndicator);
    document.documentElement.removeAttribute('data-menu-open');
    if (frame) cancelAnimationFrame(frame);
});

/* ── Indicador activo deslizante ───────────────────────────────── */
const navList = ref<HTMLElement | null>(null);
const hoverHref = ref<string | null>(null);
const indicator = ref({ x: 0, w: 0, visible: false });

function moveIndicator() {
    const list = navList.value;

    if (!list) return;

    const target =
        (hoverHref.value &&
            list.querySelector<HTMLElement>(
                `[data-nav-href="${CSS.escape(hoverHref.value)}"]`,
            )) ||
        list.querySelector<HTMLElement>('[aria-current="page"]');

    if (!target) {
        indicator.value = { ...indicator.value, visible: false };

        return;
    }

    indicator.value = {
        x: target.offsetLeft,
        w: target.offsetWidth,
        visible: true,
    };
}

watch([hoverHref, () => page.url], () => void nextTick(moveIndicator));

/* ── Mega-menú ─────────────────────────────────────────────────── */
let megaTimer = 0;
let megaTrigger: HTMLButtonElement | null = null;

function setMegaTrigger(el: unknown) {
    megaTrigger = el instanceof HTMLButtonElement ? el : megaTrigger;
}

function openMega(delay = 90) {
    window.clearTimeout(megaTimer);
    megaTimer = window.setTimeout(() => (megaOpen.value = true), delay);
}

function closeMega(delay = 160) {
    window.clearTimeout(megaTimer);
    megaTimer = window.setTimeout(() => (megaOpen.value = false), delay);
}

function toggleMega() {
    window.clearTimeout(megaTimer);
    megaOpen.value = !megaOpen.value;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && megaOpen.value) {
        megaOpen.value = false;
        megaTrigger?.focus();
    }
}

function onFocusOut(event: FocusEvent) {
    const header = event.currentTarget as HTMLElement;
    const next = event.relatedTarget as Node | null;

    if (!next || !header.contains(next)) {
        focusWithin.value = false;
        megaOpen.value = false;
    }
}

watch(
    () => page.url,
    () => {
        menuOpen.value = false;
        megaOpen.value = false;
        hidden.value = false;
    },
);

watch(menuOpen, (open) => {
    document.documentElement.toggleAttribute('data-menu-open', open);
});

const megaFeatures = [
    {
        title: 'Inspiración',
        body: 'Proyectos entregados y renders, siempre etiquetados.',
        href: '/inspiracion',
        illustration: 'kitchen' as const,
        tone: 'dark' as const,
    },
    {
        title: 'Así trabajamos',
        body: 'De la medición en tu casa a la instalación.',
        href: '/#como-funciona',
        illustration: 'tape' as const,
        tone: 'light' as const,
    },
];
</script>

<template>
    <header
        data-site-header
        :class="
            cn(
                'inset-x-0 top-0 z-40 transition-[background-color,border-color,color,box-shadow,translate] duration-500 ease-out',
                props.overlay ? 'fixed' : 'sticky',
                transparent
                    ? 'border-b border-transparent bg-transparent text-white'
                    : 'border-b border-border/70 bg-background/85 text-foreground backdrop-blur-xl supports-[backdrop-filter]:bg-background/72',
                scrolled &&
                    !transparent &&
                    'shadow-[0_8px_30px_-18px_rgb(35_30_26/0.35)]',
                hidden && '-translate-y-full',
            )
        "
        @keydown="onKeydown"
        @focusin="focusWithin = true"
        @focusout="onFocusOut"
        @mouseleave="closeMega()"
    >
        <div
            :class="
                cn(
                    'container-site flex items-center gap-6 transition-[height] duration-500 ease-out',
                    scrolled ? 'h-16' : 'h-16 md:h-[76px]',
                )
            "
        >
            <Link
                href="/"
                class="group shrink-0 rounded-md"
                :aria-label="`${props.site?.name ?? 'ML Habitat'}, inicio`"
            >
                <img
                    v-if="props.site?.logoUrl"
                    :src="props.site.logoUrl"
                    :alt="props.site?.name ?? 'ML Habitat'"
                    :class="
                        cn('h-8 w-auto', transparent && 'brightness-0 invert')
                    "
                />
                <AppLogo
                    v-else
                    :inverted="transparent"
                    class="transition-transform duration-500 ease-out group-hover:-rotate-2"
                />
            </Link>

            <nav
                aria-label="Principal"
                class="hidden flex-1 justify-center lg:flex"
            >
                <ul
                    ref="navList"
                    class="relative flex items-center gap-1"
                    @mouseleave="hoverHref = null"
                >
                    <span
                        :class="
                            cn(
                                'pointer-events-none absolute top-1/2 left-0 h-9 rounded-full transition-[transform,width,opacity] duration-500 ease-out',
                                transparent
                                    ? 'bg-white/14'
                                    : 'bg-foreground/[0.06]',
                                indicator.visible ? 'opacity-100' : 'opacity-0',
                            )
                        "
                        :style="{
                            width: `${indicator.w}px`,
                            transform: `translate3d(${indicator.x}px, -50%, 0)`,
                        }"
                        aria-hidden="true"
                    />
                    <li
                        v-for="link in links"
                        :key="link.href"
                        class="relative flex items-center"
                        @mouseenter="
                            hoverHref = link.href;
                            hasMega(link) ? openMega() : closeMega(60);
                        "
                    >
                        <Link
                            :href="link.href"
                            :data-nav-href="link.href"
                            :aria-current="
                                active(link.href) ? 'page' : undefined
                            "
                            :class="
                                cn(
                                    'relative inline-flex h-10 items-center rounded-full px-4 text-[0.9375rem] font-medium transition-colors',
                                    hasMega(link) && 'pr-9',
                                    transparent
                                        ? 'text-white/85 hover:text-white'
                                        : 'text-muted-foreground hover:text-foreground',
                                    active(link.href) &&
                                        (transparent
                                            ? 'text-white'
                                            : 'text-foreground'),
                                )
                            "
                            @focus="hoverHref = link.href"
                        >
                            {{ link.label }}
                            <span
                                v-if="active(link.href)"
                                class="absolute bottom-1 left-1/2 size-1 -translate-x-1/2 rounded-full bg-oak"
                                aria-hidden="true"
                            />
                        </Link>
                        <button
                            v-if="hasMega(link)"
                            :ref="setMegaTrigger"
                            type="button"
                            :class="
                                cn(
                                    'absolute right-1.5 grid size-7 place-items-center rounded-full transition-colors',
                                    transparent
                                        ? 'hover:bg-white/15'
                                        : 'hover:bg-foreground/8',
                                )
                            "
                            :aria-expanded="megaOpen"
                            aria-controls="mega-catalogo"
                            :aria-label="`${megaOpen ? 'Cerrar' : 'Abrir'} menú de ${link.label}`"
                            @click="toggleMega"
                        >
                            <ChevronDown
                                :class="
                                    cn(
                                        'size-4 transition-transform duration-300',
                                        megaOpen && 'rotate-180',
                                    )
                                "
                                :stroke-width="ICON_STROKE"
                                aria-hidden="true"
                            />
                        </button>
                    </li>
                </ul>
            </nav>

            <div class="ml-auto flex items-center gap-2 lg:ml-0">
                <a
                    v-if="phone && phoneHref"
                    :href="phoneHref"
                    :class="
                        cn(
                            'group hidden h-10 items-center gap-2 rounded-full px-3 text-sm font-medium xl:inline-flex',
                            transparent
                                ? 'text-white/85 hover:text-white'
                                : 'text-muted-foreground hover:text-foreground',
                        )
                    "
                >
                    <Phone
                        class="size-4 transition-transform duration-500 group-hover:-rotate-12"
                        :stroke-width="ICON_STROKE"
                        aria-hidden="true"
                    />
                    {{ phone }}
                </a>
                <Link
                    :href="props.ctaHref"
                    data-magnetic="0.25"
                    :class="
                        cn(
                            'btn-nudge btn-sweep hidden h-11 items-center gap-2 rounded-full px-5 text-[0.9375rem] font-medium shadow-xs transition-colors sm:inline-flex',
                            transparent
                                ? 'bg-white text-espresso [--sweep:var(--stone)]'
                                : 'bg-primary text-primary-foreground [--sweep:var(--primary-hover)]',
                        )
                    "
                >
                    {{ props.ctaLabel }}
                    <ArrowRight
                        class="nudge size-4"
                        :stroke-width="1.75"
                        aria-hidden="true"
                    />
                </Link>
                <button
                    type="button"
                    :class="
                        cn(
                            'grid size-11 place-items-center rounded-full lg:hidden',
                            transparent
                                ? 'text-white hover:bg-white/10'
                                : 'text-foreground hover:bg-accent',
                        )
                    "
                    aria-label="Abrir menú"
                    :aria-expanded="menuOpen"
                    @click="menuOpen = true"
                >
                    <Menu class="size-6" :stroke-width="ICON_STROKE" />
                </button>
            </div>
        </div>

        <!-- Mega-menú de Catálogo (escritorio) -->
        <Transition
            enter-active-class="transition duration-400 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition duration-200 ease-in"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-show="megaOpen"
                id="mega-catalogo"
                class="absolute inset-x-0 top-full hidden border-b border-border/70 bg-background text-foreground shadow-lg lg:block"
                @mouseenter="openMega(0)"
            >
                <div class="container-site grid grid-cols-12 gap-8 py-8">
                    <div class="col-span-6">
                        <p class="text-sm font-medium text-muted-foreground">
                            Líneas a la medida
                        </p>
                        <ul class="mt-4 grid grid-cols-2 gap-2">
                            <li
                                v-for="(category, i) in categories"
                                :key="category.href"
                                class="mega-item"
                                :style="{ '--i': i }"
                            >
                                <Link
                                    :href="category.href"
                                    class="group flex items-center gap-3 rounded-xl p-2.5 transition-colors hover:bg-stone/70 focus-visible:bg-stone/70"
                                >
                                    <span
                                        class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary transition-[background-color,color,transform] duration-300 group-hover:-rotate-6 group-hover:bg-primary group-hover:text-primary-foreground"
                                        aria-hidden="true"
                                    >
                                        <component
                                            :is="
                                                categoryIcon(
                                                    category.label,
                                                    category.href,
                                                )
                                            "
                                            class="size-5"
                                            :stroke-width="ICON_STROKE"
                                        />
                                    </span>
                                    <span
                                        class="min-w-0 flex-1 font-display text-[1.0625rem] font-semibold tracking-tight"
                                    >
                                        {{ category.label }}
                                    </span>
                                    <ArrowRight
                                        class="size-4 -translate-x-2 text-primary opacity-0 transition-all duration-300 group-hover:translate-x-0 group-hover:opacity-100"
                                        :stroke-width="ICON_STROKE"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </li>
                        </ul>
                        <Link
                            href="/catalogo"
                            class="link-underline mt-5 ml-2.5 inline-flex items-center gap-1.5 text-sm font-medium text-primary"
                        >
                            Ver todo el catálogo
                        </Link>
                    </div>
                    <Link
                        v-for="(feature, i) in megaFeatures"
                        :key="feature.href"
                        :href="feature.href"
                        :class="
                            cn(
                                'mega-item group relative col-span-3 flex flex-col overflow-hidden rounded-2xl p-5 transition-transform duration-500 ease-out hover:-translate-y-1',
                                feature.tone === 'dark'
                                    ? 'bg-espresso text-espresso-foreground'
                                    : 'tex-blueprint bg-linen text-foreground ring-1 ring-border',
                            )
                        "
                        :style="{ '--i': categories.length + i }"
                    >
                        <LineIllustration
                            :name="feature.illustration"
                            :animate="false"
                            :tone="feature.tone"
                            :class="
                                cn(
                                    'mb-4 w-full transition-transform duration-700 group-hover:scale-105',
                                    feature.tone === 'dark'
                                        ? 'text-espresso-foreground/80'
                                        : 'text-foreground/75',
                                )
                            "
                        />
                        <span
                            class="flex items-center justify-between gap-2 font-display text-lg font-semibold"
                        >
                            {{ feature.title }}
                            <ArrowUpRight
                                class="size-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                :stroke-width="ICON_STROKE"
                                aria-hidden="true"
                            />
                        </span>
                        <span
                            :class="
                                cn(
                                    'mt-1 text-sm',
                                    feature.tone === 'dark'
                                        ? 'text-espresso-foreground/70'
                                        : 'text-muted-foreground',
                                )
                            "
                            >{{ feature.body }}</span
                        >
                    </Link>
                </div>
            </div>
        </Transition>

        <!-- Menú móvil a pantalla completa -->
        <DialogRoot v-model:open="menuOpen">
            <DialogPortal>
                <DialogContent
                    class="mobile-menu fixed inset-0 z-50 flex flex-col overflow-y-auto bg-linen text-foreground outline-none"
                >
                    <div
                        class="tex-blueprint pointer-events-none absolute inset-0 opacity-70"
                        aria-hidden="true"
                    />
                    <div
                        class="relative container-site flex h-16 shrink-0 items-center justify-between"
                    >
                        <DialogTitle as="div"><AppLogo /></DialogTitle>
                        <DialogDescription class="sr-only"
                            >Menú del sitio</DialogDescription
                        >
                        <DialogClose
                            class="grid size-11 place-items-center rounded-full bg-foreground text-background transition-transform duration-300 hover:rotate-90"
                            aria-label="Cerrar menú"
                        >
                            <X class="size-5" :stroke-width="1.75" />
                        </DialogClose>
                    </div>

                    <nav
                        aria-label="Menú móvil"
                        class="relative container-site flex-1 pt-6"
                    >
                        <ul class="flex flex-col">
                            <li
                                v-for="(link, i) in links"
                                :key="link.href"
                                class="menu-item border-b border-border/80"
                                :style="{ '--i': i }"
                            >
                                <Link
                                    :href="link.href"
                                    :aria-current="
                                        active(link.href) ? 'page' : undefined
                                    "
                                    class="group flex min-h-16 items-center justify-between gap-4 py-3 font-display text-[2rem] leading-none font-semibold tracking-tight aria-[current=page]:text-primary"
                                    @click="menuOpen = false"
                                >
                                    {{ link.label }}
                                    <ArrowRight
                                        class="size-6 text-oak transition-transform duration-300 group-hover:translate-x-1"
                                        :stroke-width="1.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </li>
                        </ul>

                        <div
                            v-if="categories.length"
                            class="menu-item mt-8"
                            :style="{ '--i': links.length }"
                        >
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Líneas a la medida
                            </p>
                            <ul class="mt-3 flex flex-wrap gap-2">
                                <li
                                    v-for="category in categories"
                                    :key="category.href"
                                >
                                    <Link
                                        :href="category.href"
                                        class="inline-flex min-h-11 items-center gap-2 rounded-full border border-border bg-card px-4 text-sm font-medium transition-colors hover:border-primary hover:text-primary"
                                        @click="menuOpen = false"
                                    >
                                        <component
                                            :is="
                                                categoryIcon(
                                                    category.label,
                                                    category.href,
                                                )
                                            "
                                            class="size-4 text-primary"
                                            :stroke-width="ICON_STROKE"
                                            aria-hidden="true"
                                        />
                                        {{ category.label }}
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <LineIllustration
                            :name="
                                illustrationFor(categories[0]?.label) ===
                                'blueprint'
                                    ? 'closet'
                                    : illustrationFor(categories[0]?.label)
                            "
                            :animate="false"
                            class="menu-item mx-auto mt-10 w-56 text-foreground/50"
                            :style="{ '--i': links.length + 1 }"
                        />
                    </nav>

                    <div
                        class="menu-item relative container-site flex flex-col gap-2 border-t border-border bg-linen/90 py-4 pb-safe backdrop-blur"
                        :style="{ '--i': links.length + 2 }"
                    >
                        <Link
                            :href="props.ctaHref"
                            class="btn-nudge inline-flex h-13 items-center justify-center gap-2 rounded-full bg-primary text-base font-medium text-primary-foreground"
                            @click="menuOpen = false"
                        >
                            {{ props.ctaLabel }}
                            <ArrowRight
                                class="nudge size-4"
                                aria-hidden="true"
                            />
                        </Link>
                        <a
                            v-if="phone && phoneHref"
                            :href="phoneHref"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-border bg-card text-base font-medium"
                        >
                            <Phone
                                class="size-4"
                                :stroke-width="ICON_STROKE"
                                aria-hidden="true"
                            />
                            Llamar al {{ phone }}
                        </a>
                    </div>
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
    </header>
</template>

<style scoped>
.mega-item {
    animation: mega-in 520ms var(--motion-ease, ease-out) both;
    animation-delay: calc(var(--i, 0) * 35ms);
}

@keyframes mega-in {
    from {
        opacity: 0;
        transform: translate3d(0, 10px, 0);
    }
}

.mobile-menu[data-state='open'] {
    animation: menu-in 420ms var(--motion-ease, ease-out) both;
}

.mobile-menu[data-state='closed'] {
    animation: menu-out 240ms ease-in both;
}

.mobile-menu[data-state='open'] .menu-item {
    animation: menu-item-in 640ms var(--motion-ease, ease-out) both;
    animation-delay: calc(120ms + var(--i, 0) * 55ms);
}

@keyframes menu-in {
    from {
        clip-path: circle(0% at calc(100% - 40px) 32px);
    }
    to {
        clip-path: circle(150% at calc(100% - 40px) 32px);
    }
}

@keyframes menu-out {
    to {
        opacity: 0;
    }
}

@keyframes menu-item-in {
    from {
        opacity: 0;
        transform: translate3d(0, 24px, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .mega-item,
    .mobile-menu,
    .mobile-menu .menu-item {
        animation: none !important;
    }
}
</style>
