<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/** Encabezado editorial de sección: antetítulo, título y texto de apoyo. */
const props = withDefaults(
    defineProps<{
        eyebrow?: string | null;
        title: string;
        subtitle?: string | null;
        align?: 'left' | 'center';
        tone?: 'light' | 'dark';
        as?: 'h1' | 'h2';
        id?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { align: 'left', tone: 'light', as: 'h2' },
);
</script>

<template>
    <div
        :class="
            cn(
                'max-w-2xl',
                props.align === 'center' && 'mx-auto text-center',
                props.class,
            )
        "
    >
        <p
            v-if="props.eyebrow"
            :class="
                cn(
                    'mb-4 inline-flex items-center gap-2 text-sm font-medium',
                    props.tone === 'dark' ? 'text-oak' : 'text-oak-ink',
                )
            "
        >
            <span aria-hidden="true" class="h-px w-6 bg-current opacity-70" />
            {{ props.eyebrow }}
        </p>
        <component :is="props.as" :id="props.id" class="text-display-lg">
            {{ props.title }}
        </component>
        <p
            v-if="props.subtitle"
            :class="
                cn(
                    'mt-4 text-lead',
                    props.tone === 'dark'
                        ? 'text-espresso-foreground/75'
                        : 'text-muted-foreground',
                )
            "
        >
            {{ props.subtitle }}
        </p>
        <slot />
    </div>
</template>
