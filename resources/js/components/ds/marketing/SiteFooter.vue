<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Mail, MapPin, MessageCircle, Phone } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { defaultSiteNavigation } from '@/lib/navigation';
import type { SiteData } from '@/types/public';
import SocialIcon from './SocialIcon.vue';

/**
 * Pie del sitio público sobre banda espresso: marca y llamada a la acción,
 * navegación, páginas legales/informativas, contacto y redes.
 */
const props = withDefaults(
    defineProps<{
        site?: Partial<SiteData> | null;
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
const hasContact = computed(() =>
    Boolean(
        contact.value.phone ||
        whatsappUrl.value ||
        contact.value.email ||
        contact.value.address ||
        contact.value.hours,
    ),
);

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
        class="bg-espresso text-espresso-foreground"
        aria-labelledby="footer-heading"
    >
        <h2 id="footer-heading" class="sr-only">Pie de página</h2>
        <div class="container-site pt-16 pb-10 md:pt-20">
            <div class="grid gap-12 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <div class="max-w-sm">
                    <img
                        v-if="props.site?.logoUrl"
                        :src="props.site.logoUrl"
                        :alt="name"
                        class="h-9 w-auto brightness-0 invert"
                    />
                    <AppLogo v-else inverted size="lg" />
                    <p
                        class="mt-5 text-[0.9375rem] leading-relaxed text-espresso-foreground/75"
                    >
                        {{
                            props.site?.tagline ||
                            'Diseñamos, fabricamos e instalamos muebles pensados para tu espacio.'
                        }}
                    </p>
                    <Button
                        as-child
                        class="mt-7 h-12 rounded-lg bg-white px-6 text-base text-espresso hover:bg-white/90"
                    >
                        <Link :href="props.ctaHref">{{ props.ctaLabel }}</Link>
                    </Button>
                </div>

                <div class="grid gap-10 sm:grid-cols-3">
                    <nav aria-label="Explora">
                        <h3
                            class="text-sm font-semibold text-espresso-foreground"
                        >
                            Explora
                        </h3>
                        <ul class="mt-4 flex flex-col gap-1">
                            <li v-for="link in navigation" :key="link.href">
                                <Link
                                    :href="link.href"
                                    class="inline-flex min-h-10 items-center text-[0.9375rem] text-espresso-foreground/70 transition-colors hover:text-white"
                                >
                                    {{ link.label }}
                                </Link>
                            </li>
                        </ul>
                    </nav>

                    <nav
                        v-if="props.site?.footerPages?.length"
                        aria-label="Información"
                    >
                        <h3
                            class="text-sm font-semibold text-espresso-foreground"
                        >
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
                                    {{ item.title }}
                                </Link>
                            </li>
                        </ul>
                    </nav>

                    <div v-if="hasContact">
                        <h3
                            class="text-sm font-semibold text-espresso-foreground"
                        >
                            Contacto
                        </h3>
                        <ul
                            class="mt-4 flex flex-col gap-3 text-[0.9375rem] text-espresso-foreground/70"
                        >
                            <li v-if="contact.phone">
                                <a
                                    :href="`tel:${contact.phone.replace(/\s+/g, '')}`"
                                    class="inline-flex items-start gap-2.5 hover:text-white"
                                >
                                    <Phone
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />{{ contact.phone }}
                                </a>
                            </li>
                            <li v-if="whatsappUrl">
                                <a
                                    :href="whatsappUrl"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-start gap-2.5 hover:text-white"
                                >
                                    <MessageCircle
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />WhatsApp
                                </a>
                            </li>
                            <li v-if="contact.email">
                                <a
                                    :href="`mailto:${contact.email}`"
                                    class="inline-flex items-start gap-2.5 break-all hover:text-white"
                                >
                                    <Mail
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />{{ contact.email }}
                                </a>
                            </li>
                            <li
                                v-if="contact.address"
                                class="flex items-start gap-2.5"
                            >
                                <MapPin
                                    class="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>{{ contact.address }}</span>
                            </li>
                            <li
                                v-if="contact.hours"
                                class="flex items-start gap-2.5"
                            >
                                <Clock
                                    class="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>{{ contact.hours }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div
                class="mt-16 flex flex-col-reverse gap-6 border-t border-white/10 pt-8 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-espresso-foreground/60">
                    © {{ year }} {{ name }}. Hecho a la medida en México.
                </p>
                <ul
                    v-if="props.site?.social?.length"
                    class="-ml-2.5 flex items-center gap-1 sm:ml-0"
                >
                    <li v-for="social in props.site.social" :key="social.url">
                        <a
                            :href="social.url"
                            target="_blank"
                            rel="noopener"
                            class="grid size-11 place-items-center rounded-full text-espresso-foreground/70 transition-colors hover:bg-white/10 hover:text-white"
                            :aria-label="
                                social.label || platformLabel(social.platform)
                            "
                        >
                            <SocialIcon
                                :platform="social.platform"
                                class="size-5"
                            />
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </footer>
</template>
