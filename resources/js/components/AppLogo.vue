<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { cn } from '@/lib/utils';

/**
 * Marca ML Hábitat: insignia oficial + nombre. `subtitle` añade una línea
 * secundaria (p. ej. "Panel"); `inverted` para fondos espresso o fotografía;
 * `wordmark=false` deja solo la insignia (con texto alternativo).
 */
const props = withDefaults(
    defineProps<{
        subtitle?: string;
        inverted?: boolean;
        size?: 'sm' | 'md' | 'lg';
        wordmark?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { inverted: false, size: 'md', wordmark: true },
);

// En el riel colapsado del panel el botón mide 32 px: la insignia se ajusta.
const iconSize = {
    sm: 'size-8',
    md: 'size-10 group-data-[collapsible=icon]:size-8',
    lg: 'size-14',
} as const;
const iconSizes = { sm: '32px', md: '40px', lg: '56px' } as const;
const textSize = { sm: 'text-[15px]', md: 'text-base', lg: 'text-xl' } as const;
</script>

<template>
    <span :class="cn('flex min-w-0 items-center gap-2.5', props.class)">
        <AppLogoIcon
            :class-name="
                cn(
                    'shrink-0 shadow-[0_1px_3px_rgb(35_30_26/0.18)]',
                    iconSize[props.size],
                )
            "
            :sizes="iconSizes[props.size]"
            :title="props.wordmark ? undefined : 'ML Hábitat'"
        />
        <span
            v-if="props.wordmark"
            class="grid min-w-0 flex-1 text-left leading-none"
        >
            <span
                :class="
                    cn(
                        'truncate font-display font-semibold tracking-[-0.02em]',
                        textSize[props.size],
                        props.inverted
                            ? 'text-espresso-foreground'
                            : 'text-foreground',
                    )
                "
            >
                ML Habitat
            </span>
            <span
                v-if="props.subtitle"
                :class="
                    cn(
                        'mt-1 truncate text-xs',
                        props.inverted
                            ? 'text-espresso-foreground/70'
                            : 'text-muted-foreground',
                    )
                "
            >
                {{ props.subtitle }}
            </span>
        </span>
    </span>
</template>
