<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, Phone } from '@lucide/vue';
import { useWindowScroll } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { defaultSiteNavigation, isActivePath } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { SiteData, SiteNavLink } from '@/types/public';

/**
 * Cabecera del sitio público. Con `overlay` es transparente sobre el hero
 * (texto claro) y se vuelve sólida con desenfoque al hacer scroll.
 */
const props = withDefaults(
    defineProps<{
        site?: Partial<SiteData> | null;
        navigation?: SiteNavLink[];
        overlay?: boolean;
        ctaLabel?: string;
        ctaHref?: string;
    }>(),
    { overlay: false, ctaLabel: 'Solicitar asesoría', ctaHref: '/solicitar' },
);

const page = usePage();
const { y } = useWindowScroll();
const menuOpen = ref(false);

const links = computed(() =>
    props.navigation?.length
        ? props.navigation
        : props.site?.navigation?.length
          ? props.site.navigation
          : defaultSiteNavigation,
);

const scrolled = computed(() => y.value > 24);
const transparent = computed(
    () => props.overlay && !scrolled.value && !menuOpen.value,
);
const phone = computed(() => props.site?.contact?.phone ?? null);

function active(href: string): boolean {
    return !href.includes('#') && isActivePath(page.url, { href });
}

watch(
    () => page.url,
    () => {
        menuOpen.value = false;
    },
);
</script>

<template>
    <header
        :class="
            cn(
                'inset-x-0 top-0 z-40 transition-[background-color,border-color,color,box-shadow] duration-300 ease-out',
                props.overlay ? 'fixed' : 'sticky',
                transparent
                    ? 'border-b border-transparent bg-transparent text-white'
                    : 'border-b border-border/70 bg-background/85 text-foreground backdrop-blur-lg supports-[backdrop-filter]:bg-background/70',
            )
        "
    >
        <div class="container-site flex h-16 items-center gap-6 md:h-[72px]">
            <Link
                href="/"
                class="shrink-0 rounded-md"
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
                <AppLogo v-else :inverted="transparent" />
            </Link>

            <nav
                aria-label="Principal"
                class="hidden flex-1 justify-center lg:flex"
            >
                <ul class="flex items-center gap-1">
                    <li v-for="link in links" :key="link.href">
                        <Link
                            :href="link.href"
                            :aria-current="
                                active(link.href) ? 'page' : undefined
                            "
                            :class="
                                cn(
                                    'inline-flex h-10 items-center rounded-md px-3.5 text-[0.9375rem] font-medium transition-colors',
                                    transparent
                                        ? 'text-white/85 hover:text-white'
                                        : 'text-muted-foreground hover:text-foreground',
                                    active(link.href) &&
                                        (transparent
                                            ? 'text-white'
                                            : 'text-foreground'),
                                )
                            "
                        >
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="ml-auto flex items-center gap-2 lg:ml-0">
                <a
                    v-if="phone"
                    :href="`tel:${phone.replace(/\s+/g, '')}`"
                    :class="
                        cn(
                            'hidden h-10 items-center gap-2 rounded-md px-3 text-sm font-medium xl:inline-flex',
                            transparent
                                ? 'text-white/85 hover:text-white'
                                : 'text-muted-foreground hover:text-foreground',
                        )
                    "
                >
                    <Phone class="size-4" aria-hidden="true" />
                    {{ phone }}
                </a>
                <Button
                    as-child
                    :class="
                        cn(
                            'hidden h-11 rounded-lg px-5 text-[0.9375rem] sm:inline-flex',
                            transparent &&
                                'bg-white text-espresso hover:bg-white/90',
                        )
                    "
                >
                    <Link :href="props.ctaHref">{{ props.ctaLabel }}</Link>
                </Button>
                <button
                    type="button"
                    :class="
                        cn(
                            'grid size-11 place-items-center rounded-lg lg:hidden',
                            transparent
                                ? 'text-white hover:bg-white/10'
                                : 'text-foreground hover:bg-accent',
                        )
                    "
                    aria-label="Abrir menú"
                    :aria-expanded="menuOpen"
                    @click="menuOpen = true"
                >
                    <Menu class="size-6" />
                </button>
            </div>
        </div>

        <Sheet v-model:open="menuOpen">
            <SheetContent
                side="right"
                class="flex w-full flex-col gap-0 p-0 text-foreground sm:max-w-sm"
            >
                <SheetHeader class="border-b px-5 py-4 text-left">
                    <SheetTitle><AppLogo /></SheetTitle>
                    <SheetDescription class="sr-only"
                        >Menú del sitio</SheetDescription
                    >
                </SheetHeader>
                <nav
                    aria-label="Menú móvil"
                    class="flex-1 overflow-y-auto px-3 py-4"
                >
                    <ul class="flex flex-col">
                        <li v-for="link in links" :key="link.href">
                            <Link
                                :href="link.href"
                                :aria-current="
                                    active(link.href) ? 'page' : undefined
                                "
                                class="flex min-h-14 items-center rounded-lg px-3 font-display text-xl font-medium tracking-tight hover:bg-accent aria-[current=page]:text-primary"
                                @click="menuOpen = false"
                            >
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>
                <div class="flex flex-col gap-2 border-t px-5 py-4 pb-safe">
                    <Button as-child class="h-12 rounded-lg text-base">
                        <Link :href="props.ctaHref" @click="menuOpen = false">{{
                            props.ctaLabel
                        }}</Link>
                    </Button>
                    <Button
                        v-if="phone"
                        as-child
                        variant="outline"
                        class="h-12 rounded-lg text-base"
                    >
                        <a :href="`tel:${phone.replace(/\s+/g, '')}`"
                            ><Phone /> Llamar al {{ phone }}</a
                        >
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    </header>
</template>
