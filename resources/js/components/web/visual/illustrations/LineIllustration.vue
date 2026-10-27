<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { ILLUSTRATIONS } from './index';
import type { IllustrationName, IllustrationTone } from './types';

/**
 * Ilustración de línea de la marca. El trazo se dibuja al entrar en pantalla
 * (`data-motion="draw"`) y los planos de color aparecen al final. Decorativa
 * por defecto; con `labelled` se anuncia con su título.
 */
const props = withDefaults(
    defineProps<{
        name: IllustrationName;
        /** Grosor del trazo en unidades del lienzo (160×120). */
        stroke?: number;
        animate?: boolean;
        delay?: number;
        labelled?: boolean;
        /** Tono oscuro: trazos claros para bandas espresso. */
        tone?: 'light' | 'dark';
        class?: HTMLAttributes['class'];
    }>(),
    { stroke: 1.25, animate: true, delay: 0, labelled: false, tone: 'light' },
);

const def = computed(() => ILLUSTRATIONS[props.name]);

const STROKES: Record<IllustrationTone, string> = {
    ink: 'currentColor',
    muted: 'currentColor',
    oak: 'var(--oak)',
    eucalyptus: 'var(--primary)',
    stone: 'var(--stone)',
    linen: 'var(--linen)',
    espresso: 'var(--espresso)',
};

const FILLS: Record<IllustrationTone, string> = {
    ...STROKES,
    ink: 'currentColor',
    linen: '#FBF9F6',
    stone: '#D9CFC2',
    eucalyptus: props.tone === 'dark' ? '#8CC0AA' : '#2F5A4C',
    espresso: '#2B2420',
};

function strokeFor(tone: IllustrationTone | undefined): string {
    return STROKES[tone ?? 'ink'];
}
</script>

<template>
    <svg
        viewBox="0 0 160 120"
        fill="none"
        :class="cn('h-auto w-full overflow-visible', props.class)"
        :role="props.labelled ? 'img' : undefined"
        :aria-label="props.labelled ? def.title : undefined"
        :aria-hidden="props.labelled ? undefined : 'true'"
        focusable="false"
        :data-motion="props.animate ? 'draw' : undefined"
        :data-motion-delay="props.delay || undefined"
    >
        <g data-fill>
            <path
                v-for="(fill, i) in def.fills"
                :key="`f${i}`"
                :d="fill.d"
                :fill="FILLS[fill.tone]"
                :fill-opacity="fill.opacity ?? 1"
            />
        </g>
        <g
            :stroke-width="props.stroke"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path
                v-for="(line, i) in def.lines"
                :key="`l${i}`"
                :d="line.d"
                :stroke="strokeFor(line.tone)"
                :stroke-opacity="line.tone === 'muted' ? 0.4 : 1"
                pathLength="1"
                data-draw
                :style="{ '--d': i * 110 }"
            />
        </g>
    </svg>
</template>
