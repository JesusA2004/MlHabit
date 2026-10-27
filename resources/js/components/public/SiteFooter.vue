<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import SocialIcon from '@/components/public/SocialIcon.vue';
import type { CategorySummary, SiteIdentity, SocialLinkSummary } from '@/types';

defineProps<{
    site: SiteIdentity;
    social: SocialLinkSummary[];
    categories: CategorySummary[];
}>();

const year = new Date().getFullYear();
</script>

<template>
    <footer class="relative overflow-hidden border-t border-line bg-ink">
        <div class="container-editorial grid gap-12 pt-20 pb-10 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="font-display text-3xl tracking-tight text-paper">{{ site.name }}</p>
                <p v-if="site.footerTagline" class="mt-4 max-w-sm text-sm leading-relaxed text-mist">{{ site.footerTagline }}</p>
                <div v-if="social.length" class="mt-8 flex items-center gap-2">
                    <a
                        v-for="link in social"
                        :key="link.platform"
                        :href="link.url"
                        :target="link.open_new_tab ? '_blank' : undefined"
                        rel="noopener noreferrer"
                        class="flex size-11 items-center justify-center rounded-full border border-line text-mist transition-colors hover:border-gold hover:text-gold"
                        :aria-label="link.platform"
                    >
                        <SocialIcon :platform="link.platform" />
                    </a>
                </div>
            </div>

            <nav class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-7" aria-label="Pie de página">
                <div>
                    <p class="type-eyebrow text-gold">Explora</p>
                    <ul class="mt-4 flex flex-col gap-1">
                        <li><Link href="/tips" class="inline-flex min-h-10 items-center text-sm text-paper/80 hover:text-gold">Todos los tips</Link></li>
                        <li><Link href="/mi-historia" class="inline-flex min-h-10 items-center text-sm text-paper/80 hover:text-gold">Mi historia</Link></li>
                        <li><Link href="/mi-proceso" class="inline-flex min-h-10 items-center text-sm text-paper/80 hover:text-gold">Mi proceso</Link></li>
                        <li><Link href="/redes" class="inline-flex min-h-10 items-center text-sm text-paper/80 hover:text-gold">Redes</Link></li>
                    </ul>
                </div>
                <div v-if="categories.length">
                    <p class="type-eyebrow text-gold">Temas</p>
                    <ul class="mt-4 flex flex-col gap-1">
                        <li v-for="c in categories" :key="c.slug">
                            <Link :href="`/tips?categoria=${c.slug}`" class="inline-flex min-h-10 items-center gap-2 text-sm text-paper/80 hover:text-gold">
                                <span class="size-1.5 rounded-full" :style="{ background: c.color }" aria-hidden="true" />{{ c.name }}
                            </Link>
                        </li>
                    </ul>
                </div>
                <div>
                    <p class="type-eyebrow text-gold">Legal</p>
                    <ul class="mt-4 flex flex-col gap-1">
                        <li><Link href="/aviso-de-privacidad" class="inline-flex min-h-10 items-center text-sm text-paper/80 hover:text-gold">Aviso de privacidad</Link></li>
                    </ul>
                </div>
            </nav>
        </div>

        <p
            class="pointer-events-none container-editorial overflow-hidden font-display leading-[0.85] tracking-tighter whitespace-nowrap text-paper/[0.05] select-none"
            style="font-size: clamp(3rem, 10.5vw, 10rem)"
            aria-hidden="true"
        >
            {{ site.name }}
        </p>

        <div class="container-editorial flex flex-col gap-2 border-t border-line py-6 text-xs text-mist sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ year }} {{ site.name }}. Todos los derechos reservados.</p>
        </div>
    </footer>
</template>
