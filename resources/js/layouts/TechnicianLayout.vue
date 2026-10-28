<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import '../../css/portal.css';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AccountMenu from '@/components/ds/AccountMenu.vue';
import NotificationsMenu from '@/components/platform/NotificationsMenu.vue';
import AppToaster from '@/components/ds/AppToaster.vue';
import TabBar from '@/components/ds/TabBar.vue';
import { isActivePath, normalizePath, technicianTabs } from '@/lib/navigation';
import { cn } from '@/lib/utils';

/**
 * App de campo para técnicos: barra superior compacta con el título de la
 * pantalla y pestañas inferiores (Hoy, Visitas, Instalaciones, Perfil).
 * En escritorio las pestañas suben a la barra superior.
 */
const props = withDefaults(
    defineProps<{ title?: string; backHref?: string }>(),
    {},
);

const page = usePage();

// Clave de pantalla: cada ruta entra con una cascada suave (portal.css).
const pageKey = computed(() => normalizePath(page.url));

// Barra de progreso durante navegaciones completas.
const navigating = ref(false);
let offStart: (() => void) | undefined;
let offFinish: (() => void) | undefined;

onMounted(() => {
    offStart = router.on('start', (event) => {
        const visit = event.detail.visit as {
            only?: string[];
            method?: string;
        };
        navigating.value =
            (visit.method ?? 'get') === 'get' &&
            !(visit.only && visit.only.length);
    });
    offFinish = router.on('finish', () => (navigating.value = false));
});

onBeforeUnmount(() => {
    offStart?.();
    offFinish?.();
});
</script>

<template>
    <div class="portal-shell flex min-h-svh flex-col bg-background">
        <a
            href="#contenido"
            class="sr-only z-50 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            Saltar al contenido
        </a>

        <header
            class="sticky top-0 z-30 border-b border-border/70 bg-background/90 pt-[env(safe-area-inset-top)] backdrop-blur-lg supports-[backdrop-filter]:bg-background/75"
        >
            <div
                class="flex h-14 w-full items-center gap-3 px-3 md:h-16 md:px-6 lg:px-8"
            >
                <Link
                    v-if="props.backHref"
                    :href="props.backHref"
                    class="-ml-1 grid size-11 place-items-center rounded-lg text-foreground hover:bg-accent md:hidden"
                    aria-label="Volver"
                >
                    <ChevronLeft class="size-5" />
                </Link>
                <Link
                    href="/tecnico"
                    :class="
                        cn(
                            'grid size-11 place-items-center rounded-lg',
                            props.backHref && 'hidden md:grid',
                        )
                    "
                    aria-label="ML Habitat, inicio"
                >
                    <AppLogoIcon class-name="size-8" />
                </Link>
                <p
                    class="min-w-0 flex-1 truncate font-display text-[17px] font-semibold tracking-tight md:hidden"
                >
                    {{ props.title ?? 'ML Habitat' }}
                </p>

                <nav aria-label="Secciones" class="hidden flex-1 md:block">
                    <ul class="flex items-center gap-1">
                        <li v-for="item in technicianTabs" :key="item.href">
                            <Link
                                :href="item.href"
                                :aria-current="
                                    isActivePath(page.url, item)
                                        ? 'page'
                                        : undefined
                                "
                                :class="
                                    cn(
                                        'inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-medium transition-colors',
                                        isActivePath(page.url, item)
                                            ? 'bg-primary-soft text-primary'
                                            : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                                    )
                                "
                            >
                                <component :is="item.icon" class="size-4" />
                                {{ item.title }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <NotificationsMenu />
                <AccountMenu />
            </div>

            <div
                v-if="navigating"
                class="portal-progress absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r from-primary via-oak to-primary"
                aria-hidden="true"
            />
        </header>

        <main
            id="contenido"
            class="portal-canvas w-full flex-1 px-4 pt-4 pb-[calc(5.5rem+env(safe-area-inset-bottom))] md:px-6 md:pt-8 md:pb-12 lg:px-8"
        >
            <div :key="pageKey" class="portal-enter mx-auto w-full max-w-7xl">
                <slot />
            </div>
        </main>

        <TabBar
            :items="technicianTabs"
            label="Navegación del técnico"
            class="md:hidden"
        />
        <AppToaster />
    </div>
</template>
