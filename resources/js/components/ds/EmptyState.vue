<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Estado vacío: dice qué falta y ofrece la siguiente acción. `compact`
 * para usar dentro de tarjetas o tablas. El icono se presenta sobre anillos
 * suaves de eucalipto y, con borde, sobre una retícula de puntos (como el
 * papel de un plano) que se desvanece hacia los lados.
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
                'relative isolate flex flex-col items-center justify-center text-center',
                props.compact ? 'gap-2 px-4 py-8' : 'gap-3 px-6 py-14',
                props.bordered &&
                    'overflow-hidden rounded-xl border border-dashed border-border bg-surface/60',
                props.class,
            )
        "
    >
        <span
            v-if="props.bordered"
            class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(var(--color-border)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_center,black_10%,transparent_70%)] bg-size-[18px_18px]"
            aria-hidden="true"
        />
        <span
            v-if="props.icon"
            :class="
                cn(
                    'admin-pop relative grid shrink-0 place-items-center',
                    props.compact ? 'mb-0.5 size-14' : 'mb-1 size-20',
                )
            "
            aria-hidden="true"
        >
            <span class="absolute inset-0 rounded-full bg-primary-soft/45" />
            <span
                :class="
                    cn(
                        'absolute rounded-full bg-primary-soft/80',
                        props.compact ? 'inset-1.5' : 'inset-2.5',
                    )
                "
            />
            <span
                :class="
                    cn(
                        'relative grid place-items-center rounded-xl border border-primary/10 bg-card text-primary shadow-sm',
                        props.compact ? 'size-9' : 'size-11',
                    )
                "
            >
                <component
                    :is="props.icon"
                    :class="props.compact ? 'size-4.5' : 'size-5.5'"
                    stroke-width="1.75"
                />
            </span>
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
