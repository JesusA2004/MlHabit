<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    Clock,
    Mail,
    MapPin,
    MessageCircle,
    Phone,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import BackToTop from '@/components/web/visual/BackToTop.vue';
import GradientBlobs from '@/components/web/visual/GradientBlobs.vue';
import { categoryIcon, ICON_STROKE } from '@/components/web/visual/icons';
import LineIllustration from '@/components/web/visual/illustrations/LineIllustration.vue';
import type { IllustrationName } from '@/components/web/visual/illustrations/types';
import Marquee from '@/components/web/visual/Marquee.vue';
import type { MarqueeItem } from '@/components/web/visual/Marquee.vue';
import { defaultSiteNavigation } from '@/lib/navigation';
import type { SiteData } from '@/types/public';
import SocialIcon from './SocialIcon.vue';

/**
 * Pie del sitio público sobre banda espresso: marquesina de líneas, gran
 * declaración de marca, navegación, contacto con iconos, redes y volver
 * arriba con anillo de progreso. Sin formularios de boletín.
 */
const props = withDefaults(
    defineProps<{
        site?:
            | (Partial<SiteData> & {
                  categories?: { label: string; href: string }[];
              })
            | null;
        ctaLabel?: string;
        ctaHref?: string;
    }>(),
    { ctaLabel: 'Solicitar asesoría', ctaHref: '/solicitar' },
);

const year = new Date().getFullYear();
const name = computed(() => props.site?.name || 'ML Habitat');
const navigation = computed(() =>
    props.site?.navigation?.length
        ? props.site.navigation
        : defaultSiteNavigation,
);
const contact = computed(() => props.site?.contact ?? {});
const whatsappUrl = computed(() => {
    const digits = (contact.value.whatsapp ?? '').replace(/\D/g, '');

    return digits ? `https://wa.me/${digits}` : null;
});

type ContactItem = {
    icon: Component;
    label: string;
    value: string;
    href?: string;
    external?: boolean;
};

const contactItems = computed<ContactItem[]>(() => {
    const items: ContactItem[] = [];
    const c = contact.value;

    if (c.phone) {
        items.push({
            icon: Phone,
            label: 'Teléfono',
            value: c.phone,
            href: `tel:${c.phone.replace(/\s+/g, '')}`,
        });
    }

    if (whatsappUrl.value) {
        items.push({
            icon: MessageCircle,
            label: 'WhatsApp',
            value: 'Escríbenos',
            href: whatsappUrl.value,
            external: true,
        });
    }

    if (c.email) {
        items.push({
            icon: Mail,
            label: 'Correo',
            value: c.email,
            href: `mailto:${c.email}`,
        });
    }

    if (c.address) {
        items.push({ icon: MapPin, label: 'Dirección', value: c.address });
    }

    if (c.hours) {
        items.push({ icon: Clock, label: 'Horario', value: c.hours });
    }

    return items;
});

/** Marquesina: las líneas publicadas; si no hay, los acabados de la marca. */
const marqueeItems = computed<MarqueeItem[]>(() => {
    const categories = props.site?.categories ?? [];

    if (categories.length) {
        return categories.map((c) => ({
            label: c.label,
            href: c.href,
            icon: categoryIcon(c.label, c.href),
        }));
    }

    return [
        { label: 'Lino', swatch: '#F3EEE7' },
        { label: 'Piedra', swatch: '#D9CFC2' },
        { label: 'Roble', swatch: '#B07D4F' },
        { label: 'Eucalipto', swatch: '#2F5A4C' },
        { label: 'Espresso', swatch: '#4A3E36' },
    ];
});

const strip: IllustrationName[] = [
    'kitchen',
    'closet',
    'bedroom',
    'tv',
    'shoes',
];

const statement = ['Hecho a la medida', 'de tu casa.'];

const platformLabel = (platform: string) =>
    ({
        whatsapp: 'WhatsApp',
        instagram: 'Instagram',
        facebook: 'Facebook',
        youtube: 'YouTube',
        tiktok: 'TikTok',
        linkedin: 'LinkedIn',
        x: 'X',
    })[platform.toLowerCase()] ?? platform;
</script>

<template>
    <footer
        data-site-footer
        class="relative isolate overflow-hidden bg-espresso text-espresso-foreground"
        aria-labelledby="footer-heading"
    >
        <h2 id="footer-heading" class="sr-only">Pie de página</h2>
        <GradientBlobs tone="dark" class="opacity-70" />
        <div
            class="tex-blueprint-dark pointer-events-none absolute inset-0 -z-10 [mask-image:linear-gradient(to_bottom,#000,transparent_70%)] opacity-40"
            aria-hidden="true"
        />

        <div class="border-b border-white/10 py-5">
            <Marquee
                :items="marqueeItems"
                tone="dark"
                :duration="46"
                label="Nuestras líneas"
            />
        </div>

        <div class="container-site pt-16 pb-10 md:pt-24">
            <div class="grid gap-12 lg:grid-cols-12 lg:items-end">
                <p
                    class="font-display text-[clamp(2.5rem,1.4rem+4.6vw,5.25rem)] leading-[0.95] font-semibold tracking-[-0.035em] lg:col-span-8"
                    aria-label="Hecho a la medida de tu casa."
                >
                    <span
                        v-for="(line, i) in statement"
                        :key="i"
                        class="block overflow-hidden pb-[0.06em]"
                        aria-hidden="true"
                    >
                        <span
                            class="block"
                            data-motion="fade-up"
                            :data-motion-delay="i * 140"
                            :class="i === 1 && 'text-oak'"
                            >{{ line }}</span
                        >
                    </span>
                </p>
                <div
                    class="flex flex-col items-start gap-6 lg:col-span-4 lg:items-end"
                >
                    <p
                        class="max-w-sm text-[0.9375rem] leading-relaxed text-espresso-foreground/75 lg:text-right"
                    >
                        {{
                            props.site?.tagline ||
                            'Diseñamos, fabricamos e instalamos muebles pensados para tu espacio.'
                        }}
                    </p>
                    <Link
                        :href="props.ctaHref"
                        data-magnetic="0.3"
                        class="btn-nudge btn-sweep inline-flex h-14 items-center gap-3 rounded-full bg-oak pr-2 pl-7 text-base font-medium text-white [--sweep:#8a5f38]"
                    >
                        {{ props.ctaLabel }}
                        <span
                            class="grid size-10 place-items-center rounded-full bg-white/15"
                        >
                            <ArrowRight
                                class="nudge size-4"
                                :stroke-width="1.75"
                                aria-hidden="true"
                            />
                        </span>
                    </Link>
                </div>
            </div>

            <div
                class="mt-14 grid grid-cols-3 gap-4 border-y border-white/10 py-8 sm:grid-cols-5"
                data-motion-stagger="90"
                data-motion="fade-up"
                aria-hidden="true"
            >
                <LineIllustration
                    v-for="(item, i) in strip"
                    :key="item"
                    :name="item"
                    tone="dark"
                    :class="[
                        'text-espresso-foreground/55 transition-[color,scale] duration-500 hover:scale-105 hover:text-espresso-foreground/90',
                        i > 2 && 'hidden sm:block',
                    ]"
                />
            </div>

            <div class="mt-14 grid gap-12 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <img
                        v-if="props.site?.logoUrl"
                        :src="props.site.logoUrl"
                        :alt="name"
                        class="h-9 w-auto brightness-0 invert"
                    />
                    <AppLogo v-else inverted size="lg" />
                    <p
                        class="mt-4 max-w-xs text-sm text-espresso-foreground/60"
                    >
                        Diseño, fabricación e instalación de cocinas, clósets y
                        muebles a la medida en México.
                    </p>
                </div>

                <nav aria-label="Explora" class="lg:col-span-2">
                    <h3 class="text-sm font-semibold text-espresso-foreground">
                        Explora
                    </h3>
                    <ul class="mt-4 flex flex-col gap-1">
                        <li v-for="link in navigation" :key="link.href">
                            <Link
                                :href="link.href"
                                class="group inline-flex min-h-10 items-center gap-1 text-[0.9375rem] text-espresso-foreground/70 transition-colors hover:text-white"
                            >
                                <span class="link-underline">{{
                                    link.label
                                }}</span>
                            </Link>
                        </li>
                    </ul>
                </nav>

                <nav
                    v-if="props.site?.footerPages?.length"
                    aria-label="Información"
                    class="lg:col-span-2"
                >
                    <h3 class="text-sm font-semibold text-espresso-foreground">
                        Información
                    </h3>
                    <ul class="mt-4 flex flex-col gap-1">
                        <li
                            v-for="item in props.site.footerPages"
                            :key="item.href"
                        >
                            <Link
                                :href="item.href"
                                class="inline-flex min-h-10 items-center text-[0.9375rem] text-espresso-foreground/70 transition-colors hover:text-white"
                            >
                                <span class="link-underline">{{
                                    item.title
                                }}</span>
                            </Link>
                        </li>
                    </ul>
                </nav>

                <div v-if="contactItems.length" class="lg:col-span-4">
                    <h3 class="text-sm font-semibold text-espresso-foreground">
                        Contacto
                    </h3>
                    <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                        <li v-for="item in contactItems" :key="item.label">
                            <component
                                :is="item.href ? 'a' : 'div'"
                                :href="item.href"
                                :target="item.external ? '_blank' : undefined"
                                :rel="item.external ? 'noopener' : undefined"
                                :class="[
                                    'group -m-2 flex items-start gap-3 rounded-xl p-2 transition-colors',
                                    item.href && 'hover:bg-white/5',
                                ]"
                            >
                                <span
                                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/8 text-oak transition-[background-color,color,rotate] duration-300 group-hover:-rotate-6 group-hover:bg-oak group-hover:text-white"
                                    aria-hidden="true"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-[18px]"
                                        :stroke-width="ICON_STROKE"
                                    />
                                </span>
                                <span class="min-w-0 text-[0.9375rem]">
                                    <span
                                        class="block text-xs text-espresso-foreground/55"
                                        >{{ item.label }}</span
                                    >
                                    <span
                                        class="flex items-center gap-1 break-words text-espresso-foreground/90"
                                    >
                                        {{ item.value }}
                                        <ArrowUpRight
                                            v-if="item.external"
                                            class="size-3.5 opacity-60"
                                            :stroke-width="ICON_STROKE"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </span>
                            </component>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="mt-16 flex flex-col-reverse gap-6 border-t border-white/10 pt-8 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-espresso-foreground/60">
                    © {{ year }} {{ name }}. Hecho a la medida en México.
                </p>
                <div
                    class="flex items-center justify-between gap-4 sm:justify-end"
                >
                    <ul
                        v-if="props.site?.social?.length"
                        class="-ml-2.5 flex items-center gap-1 sm:ml-0"
                    >
                        <li
                            v-for="social in props.site.social"
                            :key="social.url"
                        >
                            <a
                                :href="social.url"
                                target="_blank"
                                rel="noopener"
                                class="grid size-11 place-items-center rounded-full text-espresso-foreground/70 transition-[background-color,color,translate] duration-300 hover:-translate-y-0.5 hover:bg-white/10 hover:text-white"
                                :aria-label="
                                    social.label ||
                                    platformLabel(social.platform)
                                "
                            >
                                <SocialIcon
                                    :platform="social.platform"
                                    class="size-5"
                                />
                            </a>
                        </li>
                    </ul>
                    <BackToTop />
                </div>
            </div>
        </div>
    </footer>
</template>
