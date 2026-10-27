<script setup lang="ts">
import { watchEffect } from 'vue';
import SiteFooter from '@/components/public/SiteFooter.vue';
import SiteHeader from '@/components/public/SiteHeader.vue';
import { loadFonts } from '@/lib/fonts';
import type { CategorySummary, SiteIdentity, SocialLinkSummary, ThemePayload } from '@/types';

const { site, social, theme, categories } = defineProps<{
    site: SiteIdentity;
    social: SocialLinkSummary[];
    theme?: ThemePayload;
    categories?: CategorySummary[];
}>();

const RADIUS_REM: Record<string, number> = { none: 0, sm: 0.25, md: 0.5, lg: 0.75, xl: 1, full: 9999 };

/**
 * Aplica la apariencia publicada (colores, tipografías, radios) como
 * variables CSS en :root: /admin/apariencia se refleja en el sitio y en
 * todos los bloques de Studio sin recompilar Vite. Las familias que no van
 * en el bundle se cargan bajo demanda.
 */
watchEffect(() => {
    if (!theme) return;
    const root = document.documentElement.style;

    if (theme.colors?.background) root.setProperty('--color-ink', theme.colors.background);
    if (theme.colors?.foreground) root.setProperty('--color-paper', theme.colors.foreground);
    if (theme.colors?.accent) root.setProperty('--color-gold', theme.colors.accent);
    if (theme.colors?.muted) root.setProperty('--color-mist', theme.colors.muted);

    if (theme.fonts?.display) root.setProperty('--font-display', `"${theme.fonts.display}", ui-serif, Georgia, serif`);
    if (theme.fonts?.body) root.setProperty('--font-sans', `"${theme.fonts.body}", ui-sans-serif, system-ui, sans-serif`);
    loadFonts([theme.fonts?.display, theme.fonts?.body]);

    const buttonRem = theme.buttons?.radius ? RADIUS_REM[theme.buttons.radius] : undefined;
    if (buttonRem !== undefined) {
        root.setProperty('--editorial-radius-button', buttonRem >= 9999 ? '9999px' : `${buttonRem}rem`);
    }

    const cardRem = theme.cards?.radius ? RADIUS_REM[theme.cards.radius] : undefined;
    if (cardRem !== undefined) {
        root.setProperty('--editorial-radius-card', `${cardRem}rem`);
        root.setProperty('--editorial-radius-card-lg', `${cardRem * 1.33}rem`);
    }

    if (theme.faviconUrl) {
        let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            document.head.appendChild(link);
        }
        link.href = theme.faviconUrl;
    }
});
</script>

<template>
    <div class="min-h-screen bg-ink font-sans text-paper antialiased selection:bg-gold selection:text-ink">
        <a
            href="#contenido"
            class="sr-only z-[60] rounded-md bg-gold px-4 py-3 font-semibold text-ink focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >Saltar al contenido</a
        >
        <SiteHeader :site="site" :social="social" />
        <main id="contenido" tabindex="-1" class="outline-none">
            <slot />
        </main>
        <SiteFooter :site="site" :social="social" :categories="categories ?? []" />
    </div>
</template>
