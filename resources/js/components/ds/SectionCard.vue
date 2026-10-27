<script setup lang="ts">
import { useId } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Superficie con título para agrupar información relacionada (datos del
 * cliente, resumen de pagos…). Cabecera con acciones opcionales; el cuerpo
 * puede ir sin padding (`flush`) para tablas o listas a sangre.
 */
const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        icon?: Component;
        flush?: boolean;
        as?: string;
        class?: HTMLAttributes['class'];
        bodyClass?: HTMLAttributes['class'];
    }>(),
    { flush: false, as: 'section' },
);

const headingId = useId();
</script>

<template>
    <component
        :is="props.as"
        data-slot="section-card"
        :aria-labelledby="props.title ? headingId : undefined"
        :class="
            cn(
                'overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs',
                props.class,
            )
        "
    >
        <header
            v-if="props.title || $slots.header || $slots.actions"
            class="flex items-start justify-between gap-4 border-b px-4 py-3.5 sm:px-5"
        >
            <slot name="header">
                <div class="flex min-w-0 items-start gap-3">
                    <component
                        :is="props.icon"
                        v-if="props.icon"
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div class="min-w-0">
                        <h2
                            :id="headingId"
                            class="text-sm font-semibold text-foreground"
                        >
                            {{ props.title }}
                        </h2>
                        <p
                            v-if="props.description"
                            class="mt-0.5 text-sm text-muted-foreground"
                        >
                            {{ props.description }}
                        </p>
                    </div>
                </div>
            </slot>
            <div
                v-if="$slots.actions"
                class="-my-1 flex shrink-0 items-center gap-1.5"
            >
                <slot name="actions" />
            </div>
        </header>
        <div :class="cn(!props.flush && 'px-4 py-4 sm:px-5', props.bodyClass)">
            <slot />
        </div>
        <footer
            v-if="$slots.footer"
            class="flex items-center justify-end gap-2 border-t bg-muted/40 px-4 py-3 sm:px-5"
        >
            <slot name="footer" />
        </footer>
    </component>
</template>
