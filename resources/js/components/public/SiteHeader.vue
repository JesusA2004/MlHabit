<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Menu } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import SocialIcon from '@/components/public/SocialIcon.vue';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { SiteIdentity, SocialLinkSummary } from '@/types';

const { site, social } = defineProps<{
    site: SiteIdentity;
    social: SocialLinkSummary[];
}>();

const mobileOpen = ref(false);
const scrolled = ref(false);
const page = usePage();

const navItems = [
    { label: 'Inicio', href: '/' },
    { label: 'Tips', href: '/tips' },
    { label: 'Mi historia', href: '/mi-historia' },
    { label: 'Mi proceso', href: '/mi-proceso' },
    { label: 'Redes', href: '/redes' },
];

function isActive(href: string): boolean {
    const path = page.url.split('?')[0];

    return href === '/' ? path === '/' : path.startsWith(href);
}

const instagram = computed(() => social.find((link) => link.platform === 'instagram'));

function onScroll() {
    scrolled.value = window.scrollY > 24;
}
onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <header
        class="sticky top-0 z-40 transition-[background-color,border-color,backdrop-filter] duration-300"
        :class="scrolled ? 'border-b border-line bg-ink/85 backdrop-blur-md' : 'border-b border-transparent bg-ink'"
        :data-scrolled="scrolled || undefined"
    >
        <div class="container-editorial flex h-[72px] items-center justify-between gap-6 lg:h-20">
            <Link href="/" class="group flex items-center gap-3" :aria-label="`${site.name}, inicio`">
                <img v-if="site.logoUrl" :src="site.logoUrl" :alt="site.name" class="size-9 shrink-0 object-contain" />
                <svg v-else viewBox="0 0 64 64" class="size-9 shrink-0 transition-transform duration-500 group-hover:rotate-[-8deg]" aria-hidden="true">
                    <circle cx="32" cy="29" r="14" fill="none" stroke="var(--color-gold)" stroke-width="3" />
                    <circle cx="32" cy="29" r="4" fill="var(--color-gold)" />
                    <line x1="17" y1="49" x2="47" y2="49" stroke="var(--color-gold)" stroke-width="3" stroke-linecap="round" />
                </svg>
                <span class="flex flex-col leading-tight">
                    <span class="font-display text-lg tracking-tight text-paper">{{ site.name }}</span>
                    <span v-if="site.subtitle" class="text-[10px] tracking-[0.22em] text-mist uppercase">{{ site.subtitle }}</span>
                </span>
            </Link>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Navegación principal">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="relative px-3.5 py-2 text-[13px] font-medium tracking-[0.12em] uppercase transition-colors"
                    :class="isActive(item.href) ? 'text-paper' : 'text-paper/65 hover:text-paper'"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                >
                    {{ item.label }}
                    <span
                        class="absolute inset-x-3.5 -bottom-0.5 h-px origin-left bg-gold transition-transform duration-300"
                        :class="isActive(item.href) ? 'scale-x-100' : 'scale-x-0'"
                        aria-hidden="true"
                    />
                </Link>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <a
                    v-if="instagram"
                    :href="instagram.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn-editorial btn-editorial--primary min-h-11"
                >
                    Sígueme en Instagram <ArrowUpRight class="size-4" aria-hidden="true" />
                </a>
            </div>

            <button
                type="button"
                class="-mr-2 flex size-11 items-center justify-center rounded-md text-paper hover:bg-white/5 lg:hidden"
                aria-label="Abrir menú"
                :aria-expanded="mobileOpen"
                @click="mobileOpen = true"
            >
                <Menu class="size-6" />
            </button>
        </div>
    </header>

    <Sheet v-model:open="mobileOpen">
        <SheetContent side="right" class="w-full border-line bg-ink text-paper sm:max-w-sm">
            <SheetHeader>
                <SheetTitle class="font-display text-xl text-paper">{{ site.name }}</SheetTitle>
                <SheetDescription class="text-mist">{{ site.subtitle }}</SheetDescription>
            </SheetHeader>
            <nav class="flex flex-col px-4" aria-label="Navegación móvil">
                <Link
                    v-for="(item, i) in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex min-h-14 items-baseline gap-4 border-b border-line font-display text-2xl transition-colors hover:text-gold"
                    :class="isActive(item.href) ? 'text-gold' : 'text-paper'"
                    @click="mobileOpen = false"
                >
                    <span class="text-xs text-mist tabular-nums">0{{ i + 1 }}</span>
                    {{ item.label }}
                </Link>
            </nav>
            <div class="mt-auto flex flex-col gap-4 px-4 pb-8">
                <a
                    v-if="instagram"
                    :href="instagram.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn-editorial btn-editorial--primary w-full"
                >
                    Sígueme en Instagram
                </a>
                <div v-if="social.length" class="flex items-center gap-2 text-mist">
                    <a
                        v-for="link in social"
                        :key="link.platform"
                        :href="link.url"
                        :target="link.open_new_tab ? '_blank' : undefined"
                        rel="noopener noreferrer"
                        class="flex size-11 items-center justify-center rounded-full border border-line hover:border-gold hover:text-gold"
                        :aria-label="link.platform"
                    >
                        <SocialIcon :platform="link.platform" />
                    </a>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
