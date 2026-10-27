<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { cn } from '@/lib/utils';

/**
 * Marca ML Habitat: monograma + logotipo. `subtitle` añade una línea
 * secundaria (p. ej. "Panel"); `inverted` para fondos espresso.
 */
const props = withDefaults(
    defineProps<{
        subtitle?: string;
        inverted?: boolean;
        size?: 'sm' | 'md' | 'lg';
        class?: HTMLAttributes['class'];
    }>(),
    { inverted: false, size: 'md' },
);

const iconSize = { sm: 'size-7', md: 'size-8', lg: 'size-10' } as const;
const textSize = { sm: 'text-[15px]', md: 'text-base', lg: 'text-xl' } as const;
</script>

<template>
    <span :class="cn('flex min-w-0 items-center gap-2.5', props.class)">
        <AppLogoIcon :class-name="cn('shrink-0', iconSize[props.size])" />
        <span class="grid min-w-0 flex-1 text-left leading-none">
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
