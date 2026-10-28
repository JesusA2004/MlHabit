<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Plus } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import '../../css/portal.css';
import AppLogo from '@/components/AppLogo.vue';
import AccountMenu from '@/components/ds/AccountMenu.vue';
import AppToaster from '@/components/ds/AppToaster.vue';
import NotificationsMenu from '@/components/platform/NotificationsMenu.vue';
import {
    portalNavGroups,
    portalNavItems,
    portalSectionFor,
    portalTabHrefs,
} from '@/components/portal/nav';
import type { PortalNavItem } from '@/components/portal/nav';
import PortalSkeleton from '@/components/portal/PortalSkeleton.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { isActivePath, normalizePath } from '@/lib/navigation';
import { cn } from '@/lib/utils';

/**
 * Portal del cliente. Escritorio (≥1024 px): barra lateral agrupada con
 * indicador activo y lienzo a todo lo ancho. Móvil/tableta: cabecera fija
 * y pestañas inferiores con "Más" (hoja inferior). Las pantallas entran
 * con una cascada suave y, si una navegación tarda, se muestra un
 * esqueleto (todo se apaga con prefers-reduced-motion).
 */
const page = usePage();
const year = new Date().getFullYear();

function isActive(item: PortalNavItem): boolean {
    const section = portalSectionFor(page.url);

    if (section) {
        return normalizePath(item.href) === section;
    }

    return isActivePath(page.url, item);
}

const current = computed(
    () => portalNavItems.find((item) => isActive(item)) ?? null,
);
const tabs = computed(() =>
    portalTabHrefs
        .map((href) => portalNavItems.find((i) => i.href === href))
        .filter((i): i is PortalNavItem => !!i),
);
const moreActive = computed(
    () => !!current.value && !portalTabHrefs.includes(current.value.href),
);
const moreOpen = ref(false);

// Clave de la pantalla: al cambiar de ruta se vuelve a montar y entra.
const pageKey = computed(() => normalizePath(page.url));

// ── Navegación lenta → barra de progreso + esqueleto ───────────────────
const navigating = ref(false);
const showSkeleton = ref(false);
let skeletonTimer: number | undefined;
let offStart: (() => void) | undefined;
let offFinish: (() => void) | undefined;

onMounted(() => {
    offStart = router.on('start', (event) => {
        const visit = event.detail.visit as {
            url?: URL;
            only?: string[];
            method?: string;
            prefetch?: boolean;
        };
        const isGet = (visit.method ?? 'get') === 'get';
        const partial = !!(visit.only && visit.only.length);
        const target = visit.url ? normalizePath(visit.url.pathname) : null;

        if (!isGet || partial || visit.prefetch) {
            return;
        }

        navigating.value = true;

        if (target && target !== pageKey.value) {
            window.clearTimeout(skeletonTimer);
            skeletonTimer = window.setTimeout(
                () => (showSkeleton.value = true),
                320,
            );
        }
    });

    offFinish = router.on('finish', () => {
        window.clearTimeout(skeletonTimer);
        navigating.value = false;
        showSkeleton.value = false;
    });
});

onBeforeUnmount(() => {
    window.clearTimeout(skeletonTimer);
    offStart?.();
    offFinish?.();
});

watch(pageKey, () => (moreOpen.value = false));
</script>

<template>
    <div class="portal-shell min-h-svh bg-background">
        <a
            href="#contenido"
            class="sr-only z-[60] rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            Saltar al contenido
        </a>

        <!-- Barra lateral (escritorio) -->
        <aside
            class="fixed inset-y-0 left-0 z-30 hidden w-68 flex-col border-r border-border/70 bg-card/80 backdrop-blur-xl lg:flex"
            aria-label="Portal de clientes"
        >
            <div class="flex h-16 shrink-0 items-center px-5">
                <Link
                    href="/portal"
                    class="rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="ML Habitat, inicio del portal"
                >
                    <AppLogo subtitle="Portal de clientes" />
                </Link>
            </div>

            <nav
                aria-label="Portal"
                class="flex-1 overflow-y-auto px-4 pt-2 pb-4"
            >
                <div
                    v-for="(group, gi) in portalNavGroups"
                    :key="gi"
                    :class="gi > 0 ? 'mt-5' : ''"
                >
                    <p
                        v-if="group.label"
                        class="mb-1.5 px-3 text-xs font-medium text-muted-foreground"
                    >
                        {{ group.label }}
                    </p>
                    <ul class="flex flex-col gap-0.5">
                        <li v-for="item in group.items" :key="item.href">
                            <Link
                                :href="item.href"
                                :aria-current="
                                    isActive(item) ? 'page' : undefined
                                "
                                :class="
                                    cn(
                                        'portal-nav-link group flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        isActive(item)
                                            ? 'bg-primary-soft text-primary'
                                            : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground',
                                    )
                                "
                            >
                                <component
                                    :is="item.icon"
                                    class="size-[18px] shrink-0 transition-transform duration-200 group-hover:scale-110"
                                    :stroke-width="isActive(item) ? 2.2 : 1.8"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ item.title }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="shrink-0 p-4">
                <div
                    class="portal-hero relative overflow-hidden rounded-xl p-4 text-white"
                >
                    <div
                        class="portal-grid-lines pointer-events-none absolute inset-0"
                        aria-hidden="true"
                    />
                    <p class="relative text-sm font-semibold">
                        ¿Otro espacio en mente?
                    </p>
                    <p class="relative mt-1 text-xs text-white/75">
                        Cuéntanos qué necesitas y te asesoramos.
                    </p>
                    <a
                        href="/solicitar"
                        class="relative mt-3 inline-flex h-9 items-center gap-1.5 rounded-md bg-white px-3 text-sm font-medium text-espresso transition hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Nueva solicitud
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex min-h-svh flex-col lg:pl-68">
            <!-- Cabecera -->
            <header
                class="sticky top-0 z-20 border-b border-border/70 bg-background/85 pt-[env(safe-area-inset-top)] backdrop-blur-lg supports-[backdrop-filter]:bg-background/70"
            >
                <div
                    class="flex h-16 w-full items-center gap-3 px-4 md:px-6 lg:px-8"
                >
                    <Link
                        href="/portal"
                        class="min-w-0 rounded-md lg:hidden"
                        aria-label="ML Habitat, inicio del portal"
                    >
                        <AppLogo subtitle="Portal de clientes" />
                    </Link>

                    <Transition name="portal-fade" mode="out-in">
                        <div
                            v-if="current"
                            :key="current.href"
                            class="hidden min-w-0 items-center gap-2.5 lg:flex"
                        >
                            <component
                                :is="current.icon"
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <p
                                class="truncate text-sm font-medium text-foreground"
                            >
                                {{ current.title }}
                            </p>
                            <span
                                v-if="current.description"
                                class="truncate text-sm text-muted-foreground"
                                >· {{ current.description }}</span
                            >
                        </div>
                    </Transition>

                    <div class="ml-auto flex shrink-0 items-center gap-1">
                        <NotificationsMenu />
                        <AccountMenu />
                    </div>
                </div>

                <div
                    v-if="navigating"
                    class="portal-progress absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r from-primary via-oak to-primary"
                    aria-hidden="true"
                />
            </header>

            <main
                id="contenido"
                tabindex="-1"
                class="portal-canvas flex-1 pb-[calc(5.5rem+env(safe-area-inset-bottom))] focus:outline-none lg:pb-10"
                :aria-busy="showSkeleton ? 'true' : undefined"
            >
                <PortalSkeleton v-if="showSkeleton" />
                <div v-show="!showSkeleton" :key="pageKey" class="portal-enter">
                    <slot />
                </div>
            </main>

            <footer class="hidden border-t border-border/70 lg:block">
                <div
                    class="flex h-14 w-full items-center justify-between px-8 text-sm text-muted-foreground"
                >
                    <span>© {{ year }} ML Habitat</span>
                    <Link
                        href="/portal/ayuda"
                        class="rounded-md hover:text-foreground"
                        >¿Necesitas ayuda?</Link
                    >
                </div>
            </footer>
        </div>

        <!-- Pestañas inferiores (móvil y tableta) -->
        <nav
            aria-label="Navegación del portal"
            class="fixed inset-x-0 bottom-0 z-40 border-t border-border/80 bg-background/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-lg supports-[backdrop-filter]:bg-background/75 lg:hidden"
        >
            <ul class="mx-auto flex max-w-lg items-stretch justify-around px-1">
                <li
                    v-for="item in tabs"
                    :key="item.href"
                    class="min-w-0 flex-1"
                >
                    <Link
                        :href="item.href"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        :class="
                            cn(
                                'flex min-h-14 flex-col items-center justify-center gap-1 rounded-lg px-1 pt-2 pb-1.5 text-[11px] font-medium transition-colors',
                                isActive(item)
                                    ? 'text-primary'
                                    : 'text-muted-foreground hover:text-foreground',
                            )
                        "
                    >
                        <span
                            :class="
                                cn(
                                    'grid h-7 w-12 place-items-center rounded-full transition-all duration-300',
                                    isActive(item)
                                        ? 'scale-100 bg-primary-soft'
                                        : 'scale-90',
                                )
                            "
                        >
                            <component
                                :is="item.icon"
                                class="size-5"
                                :stroke-width="isActive(item) ? 2.2 : 1.8"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="max-w-full truncate">{{
                            item.title
                        }}</span>
                    </Link>
                </li>
                <li class="min-w-0 flex-1">
                    <button
                        type="button"
                        :aria-expanded="moreOpen"
                        aria-haspopup="dialog"
                        :class="
                            cn(
                                'flex min-h-14 w-full flex-col items-center justify-center gap-1 rounded-lg px-1 pt-2 pb-1.5 text-[11px] font-medium transition-colors',
                                moreActive || moreOpen
                                    ? 'text-primary'
                                    : 'text-muted-foreground hover:text-foreground',
                            )
                        "
                        @click="moreOpen = true"
                    >
                        <span
                            :class="
                                cn(
                                    'grid h-7 w-12 place-items-center rounded-full transition-all duration-300',
                                    moreActive ? 'bg-primary-soft' : '',
                                )
                            "
                        >
                            <LayoutGrid class="size-5" aria-hidden="true" />
                        </span>
                        <span>Más</span>
                    </button>
                </li>
            </ul>
        </nav>

        <Sheet v-model:open="moreOpen">
            <SheetContent
                side="bottom"
                class="max-h-[85svh] gap-0 overflow-y-auto rounded-t-2xl pb-[calc(1rem+env(safe-area-inset-bottom))] lg:hidden"
            >
                <SheetHeader class="pr-12 pb-3">
                    <SheetTitle>Tu portal</SheetTitle>
                    <SheetDescription
                        >Todo lo de tu proyecto en un solo
                        lugar.</SheetDescription
                    >
                </SheetHeader>
                <ul class="grid grid-cols-2 gap-2 px-4 sm:grid-cols-3">
                    <li v-for="item in portalNavItems" :key="item.href">
                        <Link
                            :href="item.href"
                            :aria-current="isActive(item) ? 'page' : undefined"
                            :class="
                                cn(
                                    'flex min-h-20 flex-col justify-between gap-2 rounded-xl border p-3 transition-colors',
                                    isActive(item)
                                        ? 'border-primary/30 bg-primary-soft text-primary'
                                        : 'bg-card text-foreground hover:bg-muted/60',
                                )
                            "
                        >
                            <component
                                :is="item.icon"
                                class="size-5"
                                aria-hidden="true"
                            />
                            <span>
                                <span class="block text-sm font-medium">{{
                                    item.title
                                }}</span>
                                <span
                                    v-if="item.description"
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ item.description }}</span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>
                <div class="px-4 pt-3">
                    <a
                        href="/solicitar"
                        class="flex h-11 w-full items-center justify-center gap-2 rounded-md bg-primary text-sm font-medium text-primary-foreground hover:bg-primary-hover"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Nueva solicitud
                    </a>
                </div>
            </SheetContent>
        </Sheet>

        <AppToaster />
    </div>
</template>
