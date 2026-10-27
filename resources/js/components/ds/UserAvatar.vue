<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';

/**
 * Avatar con iniciales. El color de fondo se deriva del nombre (estable)
 * dentro de una paleta de materiales, para distinguir personas en listas.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        src?: string | null;
        initials?: string | null;
        size?: 'xs' | 'sm' | 'md' | 'lg';
        shape?: 'circle' | 'rounded';
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'md', shape: 'circle' },
);

const PALETTE = [
    'bg-primary-soft text-primary',
    'bg-oak/15 text-oak-ink',
    'bg-info-soft text-info-ink',
    'bg-neutral-soft text-neutral-ink',
    'bg-success-soft text-success-ink',
];

const text = computed(() => props.initials || getInitials(props.name) || '?');

const tint = computed(() => {
    let hash = 0;

    for (const char of props.name) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    return PALETTE[hash % PALETTE.length];
});

const sizeClass = computed(
    () =>
        ({
            xs: 'size-6 text-[10px]',
            sm: 'size-8 text-xs',
            md: 'size-9 text-sm',
            lg: 'size-12 text-base',
        })[props.size],
);
</script>

<template>
    <Avatar
        :class="
            cn(
                'shrink-0 overflow-hidden',
                props.shape === 'circle' ? 'rounded-full' : 'rounded-lg',
                sizeClass,
                props.class,
            )
        "
    >
        <AvatarImage v-if="props.src" :src="props.src" :alt="props.name" />
        <AvatarFallback
            :class="
                cn(
                    'font-semibold',
                    props.shape === 'circle' ? 'rounded-full' : 'rounded-lg',
                    tint,
                )
            "
            :aria-label="props.name"
        >
            {{ text }}
        </AvatarFallback>
    </Avatar>
</template>
