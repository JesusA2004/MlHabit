<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AccountMenu from '@/components/ds/AccountMenu.vue';
import NotificationsMenu from '@/components/platform/NotificationsMenu.vue';
import AppToaster from '@/components/ds/AppToaster.vue';
import TabBar from '@/components/ds/TabBar.vue';
import { isActivePath, technicianTabs } from '@/lib/navigation';
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
                class="mx-auto flex h-14 max-w-5xl items-center gap-3 px-3 md:px-6"
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
        </header>

        <main
            id="contenido"
            class="mx-auto w-full max-w-5xl flex-1 px-4 pt-4 pb-[calc(5.5rem+env(safe-area-inset-bottom))] md:px-6 md:pt-8 md:pb-12"
        >
            <slot />
        </main>

        <TabBar
            :items="technicianTabs"
            label="Navegación del técnico"
            class="md:hidden"
        />
        <AppToaster />
    </div>
</template>
