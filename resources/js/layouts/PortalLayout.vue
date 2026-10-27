<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import AccountMenu from '@/components/ds/AccountMenu.vue';
import NotificationsMenu from '@/components/platform/NotificationsMenu.vue';
import AppToaster from '@/components/ds/AppToaster.vue';
import TabBar from '@/components/ds/TabBar.vue';
import { isActivePath, portalNavigation } from '@/lib/navigation';
import { cn } from '@/lib/utils';

/**
 * Portal del cliente: claro, tranquilo, centrado en su proyecto. Navegación
 * superior en escritorio y pestañas inferiores en móvil. Siempre en modo
 * claro (ver app.blade.php).
 */
const page = usePage();
const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
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
                class="mx-auto flex h-16 w-full max-w-7xl items-center gap-6 px-4 md:px-6 lg:px-8"
            >
                <Link
                    href="/portal"
                    class="rounded-md"
                    aria-label="ML Habitat, mi proyecto"
                >
                    <AppLogo subtitle="Portal de clientes" />
                </Link>

                <nav
                    aria-label="Portal"
                    class="hidden flex-1 justify-center md:flex"
                >
                    <ul class="flex items-center gap-1">
                        <li v-for="item in portalNavigation" :key="item.href">
                            <Link
                                :href="item.href"
                                :aria-current="
                                    isActivePath(page.url, item)
                                        ? 'page'
                                        : undefined
                                "
                                :class="
                                    cn(
                                        'relative inline-flex h-10 items-center rounded-md px-3.5 text-sm font-medium transition-colors',
                                        isActivePath(page.url, item)
                                            ? 'text-foreground after:absolute after:inset-x-3.5 after:-bottom-[13px] after:h-0.5 after:rounded-full after:bg-primary'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )
                                "
                            >
                                {{ item.title }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <div class="ml-auto flex items-center gap-1 md:ml-0">
                    <NotificationsMenu />
                    <AccountMenu :show-appearance="false" />
                </div>
            </div>
        </header>

        <main
            id="contenido"
            class="flex-1 pb-[calc(5.5rem+env(safe-area-inset-bottom))] md:pb-0"
        >
            <slot />
        </main>

        <footer class="hidden border-t md:block">
            <div
                class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 text-sm text-muted-foreground md:px-6 lg:px-8"
            >
                <span>© {{ year }} ML Habitat</span>
                <Link
                    href="/portal/ayuda"
                    class="rounded-md hover:text-foreground"
                    >¿Necesitas ayuda?</Link
                >
            </div>
        </footer>

        <TabBar
            :items="portalNavigation"
            label="Navegación del portal"
            class="md:hidden"
        />
        <AppToaster />
    </div>
</template>
