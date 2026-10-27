<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Estado vacío: dice qué falta y ofrece la siguiente acción. `compact`
 * para usar dentro de tarjetas o tablas.
 */
const props = withDefaults(
    defineProps<{
        icon?: Component;
        title: string;
        description?: string;
        compact?: boolean;
        bordered?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { compact: false, bordered: true },
);
</script>

<template>
    <div
        data-slot="empty-state"
        role="status"
        :class="
            cn(
                'flex flex-col items-center justify-center text-center',
                props.compact ? 'gap-2 px-4 py-8' : 'gap-3 px-6 py-14',
                props.bordered &&
                    'rounded-xl border border-dashed border-border bg-surface/60',
                props.class,
            )
        "
    >
        <span
            v-if="props.icon"
            :class="
                cn(
                    'grid place-items-center rounded-xl bg-primary-soft text-primary',
                    props.compact ? 'size-10' : 'size-12',
                )
            "
            aria-hidden="true"
        >
            <component
                :is="props.icon"
                :class="props.compact ? 'size-5' : 'size-6'"
                stroke-width="1.75"
            />
        </span>
        <div class="max-w-md">
            <p
                class="font-medium text-foreground"
                :class="props.compact ? 'text-sm' : 'text-base'"
            >
                {{ props.title }}
            </p>
            <p
                v-if="props.description"
                class="mt-1 text-sm text-muted-foreground"
            >
                {{ props.description }}
            </p>
        </div>
        <div
            v-if="$slots.default || $slots.action"
            class="mt-2 flex flex-wrap justify-center gap-2"
        >
            <slot name="action" />
            <slot />
        </div>
    </div>
</template>
