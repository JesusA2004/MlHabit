<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    ChevronDown,
    LogIn,
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
import NavAnchor from '@/components/ds/marketing/NavAnchor.vue';
import SiteAccount from '@/components/ds/marketing/SiteAccount.vue';
import { getInitials } from '@/composables/useInitials';
import { categoryIcon, ICON_STROKE } from '@/components/web/visual/icons';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import { illustrationFor } from '@/components/web/visual/illustrations';
import {
    accountArea,
    accountLinks,
    defaultSiteNavigation,
    isActivePath,
    loginPath,
    normalizePath,
} from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { SiteCta, SiteData, SiteNavItem } from '@/types/public';

/**
 * Cabecera del sitio público. Los enlaces vienen del menú editable
 * (Sitio web → Menú, `site.navigation` y `site.cta`).
 * - Con `overlay` es transparente sobre el hero y se vuelve sólida con
 *   desenfoque al hacer scroll; se encoge, se esconde al bajar y reaparece
 *   al subir (nunca con un menú abierto o con el foco dentro).
 * - Indicador (pastilla + subrayado de roble) que se desliza siguiendo el
 *   puntero o el foco y regresa al enlace activo.
 * - Submenús con apertura animada; el Catálogo sin submenú propio abre el
 *   mega-menú de categorías.
 * - Menú móvil a pantalla completa con entrada escalonada y submenús
 *   desplegables.
 * - `prefers-reduced-motion`: sin animaciones.
 */
const props = withDefaults(
    defineProps<{
        site?:
            | (Partial<SiteData> & {
                  categories?: { label: string; href: string }[];
              })
            | null;
        navigation?: SiteNavItem[];
        overlay?: boolean;
        ctaLabel?: string;
        ctaHref?: string;
    }>(),
    { overlay: false, ctaLabel: 'Solicitar asesoría', ctaHref: '/solicitar' },
);

const page = usePage();
const menuOpen = ref(false);
/** Submenú abierto en escritorio (clave del elemento) o null. */
const openKey = ref<string | null>(null);
const scrolled = ref(false);
const hidden = ref(false);
const focusWithin = ref(false);

const links = computed<SiteNavItem[]>(() =>
    props.navigation?.length
        ? props.navigation
        : props.site?.navigation?.length
          ? props.site.navigation
          : defaultSiteNavigation,
);

/** Botón destacado: el del menú; si el equipo lo ocultó (`null`), no se muestra. */
const cta = computed<SiteCta | null>(() => {
    if (props.site && props.site.cta === null) return null;

    return props.site?.cta ?? { label: props.ctaLabel, href: props.ctaHref };
});

const categories = computed(() => props.site?.categories ?? []);

const user = computed(() => page.props.auth?.user ?? null);
const area = computed(() => (user.value ? accountArea(user.value) : null));

function logout() {
    menuOpen.value = false;
    router.flushAll();
}

const transparent = computed(
    () =>
        props.overlay &&
        !scrolled.value &&
        !menuOpen.value &&
        openKey.value === null,
);
const phone = computed(() => props.site?.contact?.phone ?? null);
const phoneHref = computed(() =>
    phone.value ? `tel:${phone.value.replace(/\s+/g, '')}` : null,
);

function keyOf(item: SiteNavItem, index: number): string {
    return item.id ?? `${index}-${item.href ?? item.label}`;
}

function activeHref(href: string | null | undefined): boolean {
    return (
        !!href &&
        href.startsWith('/') &&
        !href.includes('#') &&
        isActivePath(page.url, { href })
    );
}

function active(item: SiteNavItem): boolean {
    return (
        activeHref(item.href) ||
        (item.children ?? []).some((child) => activeHref(child.href))
    );
}

type Kind = 'link' | 'dropdown' | 'mega';

function kindOf(item: SiteNavItem): Kind {
    if (item.children?.length) return 'dropdown';
    if (
        item.href &&
        normalizePath(item.href) === '/catalogo' &&
        categories.value.length > 0
    ) {
        return 'mega';
    }

    return 'link';
}

const megaOpen = computed(() => openKey.value === 'mega');

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
            openKey.value === null &&
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
    window.clearTimeout(menuTimer);
});

/* ── Indicador deslizante ──────────────────────────────────────── */
const navList = ref<HTMLElement | null>(null);
const hoverKey = ref<string | null>(null);
const indicator = ref({ x: 0, w: 0, visible: false, hovering: false });

function moveIndicator() {
    const list = navList.value;

    if (!list) return;

    const hovered =
        hoverKey.value &&
        list.querySelector<HTMLElement>(
            `[data-nav-key="${CSS.escape(hoverKey.value)}"]`,
        );
    const target =
        hovered || list.querySelector<HTMLElement>('[data-nav-active]');

    if (!target) {
        indicator.value = { ...indicator.value, visible: false };

        return;
    }

    indicator.value = {
        x: target.offsetLeft,
        w: target.offsetWidth,
        visible: true,
        hovering: !!hovered,
    };
}

watch([hoverKey, () => page.url, links], () => void nextTick(moveIndicator));

/* ── Submenús de escritorio ────────────────────────────────────── */
let menuTimer = 0;
const triggers = new Map<string, HTMLElement>();

function setTrigger(key: string, el: unknown) {
    if (el instanceof HTMLElement) triggers.set(key, el);
}

function openMenu(key: string, delay = 90) {
    window.clearTimeout(menuTimer);
    menuTimer = window.setTimeout(() => (openKey.value = key), delay);
}

function closeMenu(delay = 160) {
    window.clearTimeout(menuTimer);
    menuTimer = window.setTimeout(() => (openKey.value = null), delay);
}

function toggleMenu(key: string) {
    window.clearTimeout(menuTimer);
    openKey.value = openKey.value === key ? null : key;
}

function onEnterItem(item: SiteNavItem, key: string) {
    hoverKey.value = key;
    const kind = kindOf(item);
    if (kind === 'dropdown') openMenu(key);
    else if (kind === 'mega') openMenu('mega');
    else closeMenu(60);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && openKey.value !== null) {
        const key = openKey.value;
        openKey.value = null;
        triggers.get(key)?.focus();
    }
}

function onFocusOut(event: FocusEvent) {
    const header = event.currentTarget as HTMLElement;
    const next = event.relatedTarget as Node | null;

    if (!next || !header.contains(next)) {
        focusWithin.value = false;
        openKey.value = null;
    }
}

/* ── Menú móvil ────────────────────────────────────────────────── */
const mobileExpanded = ref<Set<string>>(new Set());

function toggleMobile(key: string) {
    const next = new Set(mobileExpanded.value);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    mobileExpanded.value = next;
}

watch(
    () => page.url,
    () => {
        menuOpen.value = false;
        openKey.value = null;
        hidden.value = false;
    },
);

watch(menuOpen, (open) => {
    document.documentElement.toggleAttribute('data-menu-open', open);
    if (open) {
        // Abre el submenú que contiene la página actual.
        mobileExpanded.value = new Set(
            links.value
                .map((item, i) => [item, keyOf(item, i)] as const)
                .filter(([item]) => item.children?.length && active(item))
                .map(([, key]) => key),
        );
    }
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

const linkClass = (item: SiteNavItem) =>
    cn(
        'relative inline-flex h-10 items-center gap-1 rounded-full px-3 text-sm font-medium whitespace-nowrap transition-colors duration-200 xl:px-4 xl:text-[0.9375rem]',
        transparent.value
            ? 'text-white/85 hover:text-white'
            : 'text-muted-foreground hover:text-foreground',
        active(item) && (transparent.value ? 'text-white' : 'text-foreground'),
    );
</script>

<template>
    <header
        data-site-header
        :class="
            cn(
                'site-header inset-x-0 top-0 z-40 transition-[background-color,border-color,color,box-shadow,translate] duration-500 ease-out',
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
        @mouseleave="closeMenu()"
    >
        <div
            :class="
                cn(
                    'container-site flex items-center gap-4 transition-[height] duration-500 ease-out xl:gap-6',
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
                    @mouseleave="hoverKey = null"
                >
                    <!-- Pastilla + subrayado que siguen al puntero -->
                    <span
                        :class="
                            cn(
                                'nav-indicator pointer-events-none absolute top-1/2 left-0 h-9 rounded-full',
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
                    >
                        <span
                            :class="
                                cn(
                                    'nav-indicator__bar absolute bottom-1 left-1/2 h-0.5 -translate-x-1/2 rounded-full bg-oak',
                                    indicator.hovering
                                        ? 'w-[calc(100%-1.5rem)] opacity-100'
                                        : 'w-1 opacity-100',
                                )
                            "
                        />
                    </span>

                    <li
                        v-for="(item, i) in links"
                        :key="keyOf(item, i)"
                        class="relative flex items-center"
                        @mouseenter="onEnterItem(item, keyOf(item, i))"
                    >
                        <!-- Grupo sin enlace propio: botón que abre el submenú -->
                        <button
                            v-if="!item.href"
                            :ref="(el) => setTrigger(keyOf(item, i), el)"
                            type="button"
                            :data-nav-key="keyOf(item, i)"
                            :data-nav-active="active(item) ? '' : undefined"
                            :class="linkClass(item)"
                            :aria-expanded="openKey === keyOf(item, i)"
                            :aria-controls="`submenu-${keyOf(item, i)}`"
                            @click="toggleMenu(keyOf(item, i))"
                            @focus="hoverKey = keyOf(item, i)"
                        >
                            {{ item.label }}
                            <ChevronDown
                                :class="
                                    cn(
                                        'chevron size-4',
                                        openKey === keyOf(item, i) &&
                                            'rotate-180',
                                    )
                                "
                                :stroke-width="ICON_STROKE"
                                aria-hidden="true"
                            />
                        </button>

                        <template v-else>
                            <NavAnchor
                                :href="item.href"
                                :external="item.external"
                                :new-tab="item.newTab"
                                :data-nav-key="keyOf(item, i)"
                                :data-nav-active="active(item) ? '' : undefined"
                                :aria-current="
                                    activeHref(item.href) ? 'page' : undefined
                                "
                                :class="
                                    cn(
                                        linkClass(item),
                                        kindOf(item) !== 'link' &&
                                            'pr-9 xl:pr-10',
                                    )
                                "
                                @focus="hoverKey = keyOf(item, i)"
                            >
                                {{ item.label }}
                                <ArrowUpRight
                                    v-if="item.newTab"
                                    class="size-3.5 opacity-70"
                                    :stroke-width="ICON_STROKE"
                                    aria-hidden="true"
                                />
                            </NavAnchor>
                            <button
                                v-if="kindOf(item) !== 'link'"
                                :ref="
                                    (el) =>
                                        setTrigger(
                                            kindOf(item) === 'mega'
                                                ? 'mega'
                                                : keyOf(item, i),
                                            el,
                                        )
                                "
                                type="button"
                                :class="
                                    cn(
                                        'absolute right-1.5 grid size-7 place-items-center rounded-full transition-colors',
                                        transparent
                                            ? 'hover:bg-white/15'
                                            : 'hover:bg-foreground/8',
                                    )
                                "
                                :aria-expanded="
                                    openKey ===
                                    (kindOf(item) === 'mega'
                                        ? 'mega'
                                        : keyOf(item, i))
                                "
                                :aria-controls="
                                    kindOf(item) === 'mega'
                                        ? 'mega-catalogo'
                                        : `submenu-${keyOf(item, i)}`
                                "
                                :aria-label="`${
                                    openKey ===
                                    (kindOf(item) === 'mega'
                                        ? 'mega'
                                        : keyOf(item, i))
                                        ? 'Cerrar'
                                        : 'Abrir'
                                } menú de ${item.label}`"
                                @click="
                                    toggleMenu(
                                        kindOf(item) === 'mega'
                                            ? 'mega'
                                            : keyOf(item, i),
                                    )
                                "
                            >
                                <ChevronDown
                                    :class="
                                        cn(
                                            'chevron size-4',
                                            openKey ===
                                                (kindOf(item) === 'mega'
                                                    ? 'mega'
                                                    : keyOf(item, i)) &&
                                                'rotate-180',
                                        )
                                    "
                                    :stroke-width="ICON_STROKE"
                                    aria-hidden="true"
                                />
                            </button>
                        </template>

                        <!-- Submenú -->
                        <Transition name="dropdown">
                            <div
                                v-if="
                                    kindOf(item) === 'dropdown' &&
                                    openKey === keyOf(item, i)
                                "
                                :id="`submenu-${keyOf(item, i)}`"
                                class="dropdown absolute top-full left-1/2 z-10 w-max min-w-60 -translate-x-1/2 pt-3"
                                @mouseenter="openMenu(keyOf(item, i), 0)"
                            >
                                <ul
                                    class="rounded-2xl border border-border/80 bg-background p-2 text-foreground shadow-lg"
                                >
                                    <li
                                        v-for="(child, j) in item.children"
                                        :key="child.href + j"
                                        class="dropdown-item"
                                        :style="{ '--i': j }"
                                    >
                                        <NavAnchor
                                            :href="child.href"
                                            :external="child.external"
                                            :new-tab="child.newTab"
                                            :aria-current="
                                                activeHref(child.href)
                                                    ? 'page'
                                                    : undefined
                                            "
                                            class="group flex min-h-11 items-center justify-between gap-6 rounded-xl px-3 py-2 text-[0.9375rem] font-medium transition-colors hover:bg-stone/70 focus-visible:bg-stone/70 aria-[current=page]:text-primary"
                                            @click="openKey = null"
                                        >
                                            <span>{{ child.label }}</span>
                                            <component
                                                :is="
                                                    child.newTab ||
                                                    child.external
                                                        ? ArrowUpRight
                                                        : ArrowRight
                                                "
                                                class="size-4 -translate-x-1 text-primary opacity-0 transition-all duration-300 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:translate-x-0 group-focus-visible:opacity-100"
                                                :stroke-width="ICON_STROKE"
                                                aria-hidden="true"
                                            />
                                        </NavAnchor>
                                    </li>
                                </ul>
                            </div>
                        </Transition>
                    </li>
                </ul>
            </nav>

            <!--
                Acciones: acceso (icono bajo xl) + botón destacado + menú.
                Sin teléfono en la barra: no cabe junto a la navegación sin
                encimarse; vive en el menú móvil y en el pie.
            -->
            <div class="ml-auto flex shrink-0 items-center gap-2 lg:ml-0">
                <div class="hidden sm:flex">
                    <SiteAccount :transparent="transparent" />
                </div>
                <NavAnchor
                    v-if="cta"
                    :href="cta.href"
                    :external="cta.external"
                    :new-tab="cta.newTab"
                    data-magnetic="0.25"
                    :class="
                        cn(
                            'btn-nudge btn-sweep hidden h-11 shrink-0 items-center gap-2 rounded-full px-4 text-[0.9375rem] font-medium whitespace-nowrap shadow-xs transition-colors sm:inline-flex xl:px-5',
                            transparent
                                ? 'bg-white text-espresso [--sweep:var(--stone)]'
                                : 'bg-primary text-primary-foreground [--sweep:var(--primary-hover)]',
                        )
                    "
                >
                    {{ cta.label }}
                    <ArrowRight
                        class="nudge size-4 lg:max-xl:hidden"
                        :stroke-width="1.75"
                        aria-hidden="true"
                    />
                </NavAnchor>
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
            enter-active-class="mega-enter transition duration-400 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="mega-leave transition duration-200 ease-in"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-show="megaOpen"
                id="mega-catalogo"
                class="absolute inset-x-0 top-full hidden border-b border-border/70 bg-background text-foreground shadow-lg lg:block"
                @mouseenter="openMenu('mega', 0)"
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
                                v-for="(item, i) in links"
                                :key="keyOf(item, i)"
                                class="menu-item border-b border-border/80"
                                :style="{ '--i': i }"
                            >
                                <div class="flex items-center gap-2">
                                    <NavAnchor
                                        v-if="item.href"
                                        :href="item.href"
                                        :external="item.external"
                                        :new-tab="item.newTab"
                                        :aria-current="
                                            activeHref(item.href)
                                                ? 'page'
                                                : undefined
                                        "
                                        class="group flex min-h-16 flex-1 items-center justify-between gap-4 py-3 font-display text-[2rem] leading-none font-semibold tracking-tight aria-[current=page]:text-primary"
                                        @click="menuOpen = false"
                                    >
                                        {{ item.label }}
                                        <component
                                            :is="
                                                item.newTab || item.external
                                                    ? ArrowUpRight
                                                    : ArrowRight
                                            "
                                            v-if="!item.children?.length"
                                            class="size-6 text-oak transition-transform duration-300 group-hover:translate-x-1"
                                            :stroke-width="1.5"
                                            aria-hidden="true"
                                        />
                                    </NavAnchor>
                                    <button
                                        v-else
                                        type="button"
                                        :class="
                                            cn(
                                                'flex min-h-16 flex-1 items-center py-3 text-left font-display text-[2rem] leading-none font-semibold tracking-tight',
                                                active(item) && 'text-primary',
                                            )
                                        "
                                        :aria-expanded="
                                            mobileExpanded.has(keyOf(item, i))
                                        "
                                        :aria-controls="`m-submenu-${keyOf(item, i)}`"
                                        @click="toggleMobile(keyOf(item, i))"
                                    >
                                        {{ item.label }}
                                    </button>
                                    <button
                                        v-if="item.children?.length"
                                        type="button"
                                        class="grid size-11 shrink-0 place-items-center rounded-full border border-border bg-card"
                                        :aria-expanded="
                                            mobileExpanded.has(keyOf(item, i))
                                        "
                                        :aria-controls="`m-submenu-${keyOf(item, i)}`"
                                        :aria-label="`${mobileExpanded.has(keyOf(item, i)) ? 'Cerrar' : 'Abrir'} submenú de ${item.label}`"
                                        @click="toggleMobile(keyOf(item, i))"
                                    >
                                        <ChevronDown
                                            :class="
                                                cn(
                                                    'chevron size-5 text-oak',
                                                    mobileExpanded.has(
                                                        keyOf(item, i),
                                                    ) && 'rotate-180',
                                                )
                                            "
                                            :stroke-width="1.75"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                                <div
                                    v-if="item.children?.length"
                                    :id="`m-submenu-${keyOf(item, i)}`"
                                    class="m-submenu"
                                    :data-open="
                                        mobileExpanded.has(keyOf(item, i))
                                            ? ''
                                            : undefined
                                    "
                                >
                                    <ul class="m-submenu__inner">
                                        <li
                                            v-for="(child, j) in item.children"
                                            :key="child.href + j"
                                            class="m-submenu__item"
                                            :style="{ '--j': j }"
                                        >
                                            <NavAnchor
                                                :href="child.href"
                                                :external="child.external"
                                                :new-tab="child.newTab"
                                                :aria-current="
                                                    activeHref(child.href)
                                                        ? 'page'
                                                        : undefined
                                                "
                                                :tabindex="
                                                    mobileExpanded.has(
                                                        keyOf(item, i),
                                                    )
                                                        ? undefined
                                                        : -1
                                                "
                                                class="flex min-h-12 items-center justify-between gap-3 pl-1 text-lg font-medium text-foreground/80 aria-[current=page]:text-primary"
                                                @click="menuOpen = false"
                                            >
                                                {{ child.label }}
                                                <ArrowRight
                                                    class="size-4 text-oak"
                                                    :stroke-width="1.5"
                                                    aria-hidden="true"
                                                />
                                            </NavAnchor>
                                        </li>
                                    </ul>
                                </div>
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
                        <NavAnchor
                            v-if="cta"
                            :href="cta.href"
                            :external="cta.external"
                            :new-tab="cta.newTab"
                            class="btn-nudge inline-flex h-13 items-center justify-center gap-2 rounded-full bg-primary text-base font-medium text-primary-foreground"
                            @click="menuOpen = false"
                        >
                            {{ cta.label }}
                            <ArrowRight
                                class="nudge size-4"
                                aria-hidden="true"
                            />
                        </NavAnchor>
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
                        <!-- Acceso único: cada rol llega a su área -->
                        <div
                            v-if="user && area"
                            class="mt-2 flex items-center gap-3 rounded-2xl border border-border bg-card p-2 pl-3"
                        >
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground"
                                aria-hidden="true"
                                >{{ getInitials(user.name) }}</span
                            >
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium"
                                    >{{ user.name }}</span
                                >
                                <Link
                                    :href="accountLinks.logout"
                                    method="post"
                                    as="button"
                                    class="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                    @click="logout"
                                    >Cerrar sesión</Link
                                >
                            </span>
                            <Link
                                :href="area.href"
                                class="inline-flex h-11 shrink-0 items-center gap-1.5 rounded-full bg-espresso px-4 text-sm font-medium text-espresso-foreground"
                                @click="menuOpen = false"
                            >
                                {{ area.short }}
                                <ArrowUpRight
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        </div>
                        <Link
                            v-else
                            :href="loginPath"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-full text-base font-medium text-foreground underline-offset-4 hover:underline"
                            @click="menuOpen = false"
                        >
                            <LogIn
                                class="size-4"
                                :stroke-width="ICON_STROKE"
                                aria-hidden="true"
                            />
                            Iniciar sesión
                        </Link>
                    </div>
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
    </header>
</template>

<style scoped>
/* Indicador: pastilla que se desliza y subrayado de roble que se estira. */
.nav-indicator {
    transition:
        transform 460ms cubic-bezier(0.22, 1, 0.36, 1),
        width 460ms cubic-bezier(0.22, 1, 0.36, 1),
        opacity 240ms ease-out,
        background-color 300ms ease-out;
}

.nav-indicator__bar {
    transition:
        width 420ms cubic-bezier(0.22, 1, 0.36, 1),
        opacity 200ms ease-out;
}

.chevron {
    transition: transform 300ms cubic-bezier(0.22, 1, 0.36, 1);
}

/* Submenú de escritorio */
.dropdown-enter-active {
    transition:
        opacity 260ms cubic-bezier(0.22, 1, 0.36, 1),
        transform 320ms cubic-bezier(0.22, 1, 0.36, 1),
        filter 260ms ease-out;
}

.dropdown-leave-active {
    transition:
        opacity 160ms ease-in,
        transform 160ms ease-in;
}

.dropdown-enter-from,
.dropdown-leave-to {
    /* El centrado lo da `translate` (Tailwind 4); aquí solo `transform`. */
    opacity: 0;
    transform: translate3d(0, -6px, 0) scale(0.97);
    filter: blur(2px);
}

.dropdown {
    transform-origin: top center;
}

.dropdown-enter-active .dropdown-item {
    animation: mega-in 420ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(60ms + var(--i, 0) * 40ms);
}

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

/* Menú móvil */
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

/* Submenú móvil: se despliega con grid-rows (sin medir alturas). */
.m-submenu {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 380ms cubic-bezier(0.22, 1, 0.36, 1);
}

.m-submenu[data-open] {
    grid-template-rows: 1fr;
}

.m-submenu__inner {
    min-height: 0;
    overflow: hidden;
    padding-left: 0.25rem;
}

.m-submenu[data-open] .m-submenu__inner {
    padding-bottom: 0.75rem;
}

.m-submenu__item {
    opacity: 0;
    transform: translate3d(0, 8px, 0);
    transition:
        opacity 300ms ease-out,
        transform 360ms cubic-bezier(0.22, 1, 0.36, 1);
}

.m-submenu[data-open] .m-submenu__item {
    opacity: 1;
    transform: none;
    transition-delay: calc(80ms + var(--j, 0) * 45ms);
}

@media (prefers-reduced-motion: reduce) {
    .mega-item,
    .mobile-menu,
    .mobile-menu .menu-item,
    .dropdown-enter-active .dropdown-item {
        animation: none !important;
    }

    .nav-indicator,
    .nav-indicator__bar,
    .chevron,
    .dropdown-enter-active,
    .dropdown-leave-active,
    .m-submenu,
    .m-submenu__item {
        transition: none !important;
    }

    .dropdown-enter-from,
    .dropdown-leave-to {
        filter: none;
        transform: none;
    }
}
</style>
