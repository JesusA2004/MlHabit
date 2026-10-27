<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { ICON_STROKE } from './icons';

/**
 * Marquesina continua de categorías o materiales. Se pausa con hover/foco;
 * con movimiento reducido se muestra como una fila estática. La copia
 * duplicada que permite el bucle es `aria-hidden`.
 */
export type MarqueeItem = {
    label: string;
    href?: string;
    icon?: Component;
    swatch?: string;
};

const props = withDefaults(
    defineProps<{
        items: MarqueeItem[];
        duration?: number;
        tone?: 'light' | 'dark';
        label?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { duration: 40, tone: 'light', label: 'Líneas y materiales' },
);

// Repite la lista hasta llenar pantallas anchas antes de duplicarla.
const filled = computed(() => {
    if (!props.items.length) return [];
    const times = Math.max(1, Math.ceil(10 / props.items.length));

    return Array.from({ length: times }, () => props.items).flat();
});
</script>

<template>
    <div
        v-if="props.items.length"
        :class="
            cn(
                'marquee relative overflow-hidden [mask-image:linear-gradient(to_right,transparent,#000_8%,#000_92%,transparent)]',
                props.class,
            )
        "
        :aria-label="props.label"
        role="region"
        :style="{ '--marquee-duration': `${props.duration}s` }"
    >
        <div class="marquee-track">
            <ul
                v-for="copy in 2"
                :key="copy"
                class="flex shrink-0 items-center"
                :aria-hidden="copy === 2 ? 'true' : undefined"
            >
                <li
                    v-for="(item, i) in filled"
                    :key="`${copy}-${i}`"
                    :class="[
                        'flex items-center',
                        i >= props.items.length && 'marquee-repeat',
                    ]"
                >
                    <component
                        :is="item.href ? 'a' : 'span'"
                        :href="item.href"
                        :tabindex="
                            copy === 2 || i >= props.items.length
                                ? -1
                                : undefined
                        "
                        :class="
                            cn(
                                'group inline-flex items-center gap-3 px-6 py-2 font-display text-2xl font-medium tracking-tight whitespace-nowrap transition-colors sm:text-3xl',
                                props.tone === 'dark'
                                    ? 'text-espresso-foreground/80 hover:text-white'
                                    : 'text-foreground/80 hover:text-primary',
                            )
                        "
                    >
                        <span
                            v-if="item.swatch"
                            class="size-5 rounded-full ring-1 ring-black/10 transition-transform duration-500 group-hover:scale-125"
                            :style="{ backgroundColor: item.swatch }"
                            aria-hidden="true"
                        />
                        <component
                            :is="item.icon"
                            v-else-if="item.icon"
                            class="size-6 text-oak transition-transform duration-500 group-hover:rotate-12"
                            :stroke-width="ICON_STROKE"
                            aria-hidden="true"
                        />
                        {{ item.label }}
                    </component>
                    <span
                        class="size-1.5 rounded-full bg-oak/60"
                        aria-hidden="true"
                    />
                </li>
            </ul>
        </div>
    </div>
</template>
