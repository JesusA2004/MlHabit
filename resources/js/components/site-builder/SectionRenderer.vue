<script setup lang="ts">
import { computed, inject, provide } from 'vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import { usePage } from '@inertiajs/vue3';
import Marquee from '@/components/web/visual/Marquee.vue';
import type { MarqueeItem } from '@/components/web/visual/Marquee.vue';
import { categoryIcon } from '@/components/web/visual/icons';
import BlockRenderer from './BlockRenderer.vue';
import SectionDecor from './SectionDecor.vue';
import { composeForSite } from './compose';
import { editorBridgeKey, sectionContextKey } from './context';
import type { SectionContext } from './context';
import { computeResponsive } from './grid';
import { backgroundCss, isDarkSection, overlayCss } from './tokens';
import type { RenderSection } from './types';

/**
 * Una sección: fondo (color de marca, degradado o foto con velo), espaciado
 * y su lienzo de bloques. El acomodo de tableta y móvil se calcula aquí a
 * partir del de escritorio (ver grid.ts) y se aplica con container queries.
 */
const props = withDefaults(
    defineProps<{
        section: RenderSection;
        index: number;
        /** Filas extra al final (zona para soltar en el editor). */
        extraRows?: number;
    }>(),
    { extraRows: 0 },
);

const settings = computed(() => props.section.layout.settings);
/** La foto de fondo de la primera sección es la imagen LCP: se asienta, nunca se oculta. */
const bridge = inject(editorBridgeKey, null);
const settle = bridge === null && props.index === 0 ? 'settle' : undefined;
const dark = computed(() => isDarkSection(settings.value));
// En el sitio público los botones contiguos y la columna de texto junto a un
// bloque alto se componen (compose.ts); el editor ve los bloques sueltos.
const responsive = computed(() =>
    computeResponsive(
        bridge === null
            ? composeForSite(props.section.layout.blocks)
            : props.section.layout.blocks,
    ),
);
const rows = computed(() =>
    Math.max(1, responsive.value.rows + props.extraRows),
);

const backgroundImage = computed(() => {
    const bg = settings.value.background;
    if (bg.kind !== 'image' || bg.mediaId === null) return null;

    return props.section.images[String(bg.mediaId)] ?? null;
});

const PADDING: Record<string, [string, string]> = {
    none: ['0px', '0px'],
    sm: ['clamp(24px, 4cqi, 48px)', 'clamp(24px, 4cqi, 48px)'],
    md: ['clamp(40px, 6cqi, 80px)', 'clamp(40px, 6cqi, 80px)'],
    lg: ['clamp(64px, 10cqi, 128px)', 'clamp(64px, 10cqi, 128px)'],
    xl: ['clamp(88px, 13cqi, 168px)', 'clamp(88px, 13cqi, 168px)'],
    hero: ['clamp(112px, 12cqi, 168px)', 'clamp(48px, 6cqi, 88px)'],
};

const MIN_HEIGHT: Record<string, string> = {
    auto: 'auto',
    sm: '320px',
    md: '480px',
    lg: '640px',
    screen: 'min(92svh, 860px)',
};

const innerStyle = computed(() => {
    const [top, bottom] = PADDING[settings.value.padding] ?? PADDING.lg;

    return {
        '--sb-pad-top': top,
        '--sb-pad-bottom': bottom,
        '--sb-min-h': MIN_HEIGHT[settings.value.minHeight] ?? 'auto',
        '--sb-valign': {
            start: 'flex-start',
            center: 'center',
            end: 'flex-end',
        }[settings.value.verticalAlign],
    };
});

const panel = computed(() => settings.value.surface === 'panel');

/** Portada pública: banda de líneas (categorías publicadas) al pie del hero. */
const page = usePage();
const heroBand = computed<MarqueeItem[]>(() => {
    if (bridge !== null || props.index !== 0 || props.section.type !== 'hero') {
        return [];
    }
    const site = page.props.site as
        | { categories?: { label: string; href: string }[] }
        | undefined;

    return (site?.categories ?? []).map((c) => ({
        label: c.label,
        href: c.href,
        icon: categoryIcon(c.label, c.href),
    }));
});

/** Nombre accesible de la sección (región): su primer título, en orden de lectura. */
const sectionLabel = computed(() => {
    const heading = [...props.section.layout.blocks]
        .filter((b) => b.type === 'heading')
        .sort((a, b) => a.y - b.y || a.x - b.x)[0];
    const text = heading?.props?.text;

    return typeof text === 'string' && text.trim() !== ''
        ? text.trim()
        : undefined;
});
const bgStyle = computed(() => ({ background: backgroundCss(settings.value) }));
const overlay = computed(() =>
    backgroundImage.value ? overlayCss(settings.value) : undefined,
);

const context = computed<SectionContext>(() => ({
    key: props.section.key,
    settings: settings.value,
    dark: dark.value,
    priority: props.index === 0,
    images: props.section.images ?? {},
    videos: props.section.videos ?? {},
    data: props.section.data ?? {},
}));

provide(sectionContextKey, context);
</script>

<template>
    <section
        :id="settings.anchor || undefined"
        class="sb-section"
        :class="[
            dark ? 'sb-tone-dark' : 'sb-tone-light',
            settings.anchor && 'scroll-mt-24',
        ]"
        :aria-label="sectionLabel"
        :data-section-key="props.section.key"
        :data-journey="settings.journey || undefined"
    >
        <div
            v-if="!panel && settings.background.kind !== 'none'"
            class="sb-bg"
            :style="bgStyle"
        >
            <ResponsiveImage
                v-if="backgroundImage"
                :image="backgroundImage"
                :priority="props.index === 0"
                sizes="100vw"
                :data-motion="settle"
            />
            <div
                v-if="overlay"
                class="sb-bg-overlay"
                :style="{ background: overlay }"
            />
        </div>
        <SectionDecor
            v-if="bridge === null"
            :type="props.section.type"
            :dark="dark"
            :has-image="backgroundImage !== null"
        />

        <div class="sb-inner" :style="innerStyle">
            <div
                class="sb-canvas"
                :class="settings.fullBleed && !panel && 'sb-canvas--bleed'"
            >
                <div v-if="panel" class="sb-panel">
                    <div class="sb-bg" :style="bgStyle">
                        <ResponsiveImage
                            v-if="backgroundImage"
                            :image="backgroundImage"
                            :priority="props.index === 0"
                            sizes="(min-width: 1280px) 1200px, 100vw"
                        />
                        <div
                            v-if="overlay"
                            class="sb-bg-overlay"
                            :style="{ background: overlay }"
                        />
                    </div>
                    <div class="sb-grid" :style="{ '--sb-rows': String(rows) }">
                        <slot name="grid-start" :rows="rows" />
                        <BlockRenderer
                            v-for="block in responsive.order"
                            :key="block.id"
                            :block="block"
                            :placement="responsive.placements[block.id]"
                            :section-key="props.section.key"
                        />
                        <slot name="grid-end" :rows="rows" />
                    </div>
                </div>
                <div
                    v-else
                    class="sb-grid"
                    :style="{ '--sb-rows': String(rows) }"
                >
                    <slot name="grid-start" :rows="rows" />
                    <BlockRenderer
                        v-for="block in responsive.order"
                        :key="block.id"
                        :block="block"
                        :placement="responsive.placements[block.id]"
                        :section-key="props.section.key"
                    />
                    <slot name="grid-end" :rows="rows" />
                </div>
            </div>
        </div>
        <div
            v-if="heroBand.length"
            class="relative border-t border-white/10 py-5"
            data-motion="fade-in"
            data-motion-delay="500"
        >
            <Marquee
                :items="heroBand"
                tone="dark"
                label="Líneas a la medida"
                :duration="46"
            />
        </div>
        <slot name="section-end" />
    </section>
</template>
