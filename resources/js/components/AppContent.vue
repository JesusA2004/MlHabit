<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { AppVariant } from '@/types';

const props = withDefaults(
    defineProps<{ variant?: AppVariant; class?: HTMLAttributes['class'] }>(),
    { variant: 'sidebar' },
);
</script>

<template>
    <SidebarInset
        v-if="props.variant === 'sidebar'"
        id="contenido"
        :class="props.class"
    >
        <slot />
    </SidebarInset>
    <main
        v-else
        id="contenido"
        :class="
            cn(
                'mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4',
                props.class,
            )
        "
    >
        <slot />
    </main>
</template>
