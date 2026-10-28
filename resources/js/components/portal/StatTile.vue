<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import type { Component, HTMLAttributes } from 'vue';
import { toneClasses } from '@/components/ds';
import type { Tone } from '@/components/ds';
import { cn } from '@/lib/utils';

/** Indicador del resumen del portal (enlaza a su sección). */
const props = withDefaults(
    defineProps<{
        label: string;
        hint?: string;
        href: string;
        icon: Component;
        tone?: Tone;
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'brand' },
);
</script>

<template>
    <Link
        :href="href"
        :class="
            cn(
                'group portal-lift relative flex min-h-[7.5rem] flex-col justify-between gap-3 overflow-hidden rounded-xl border bg-card p-4 shadow-xs hover:border-primary/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:p-5',
                props.class,
            )
        "
    >
        <div class="flex items-start justify-between gap-2">
            <span
                :class="
                    cn(
                        'grid size-9 place-items-center rounded-lg',
                        toneClasses[tone].soft,
                    )
                "
                aria-hidden="true"
            >
                <component :is="icon" class="size-[18px]" :stroke-width="1.9" />
            </span>
            <ArrowUpRight
                class="size-4 text-muted-foreground opacity-0 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:opacity-100 group-focus-visible:opacity-100"
                aria-hidden="true"
            />
        </div>
        <div class="min-w-0">
            <p
                class="font-display text-2xl leading-none font-semibold tracking-tight text-foreground tabular-nums"
            >
                <slot />
            </p>
            <p class="mt-1.5 truncate text-sm text-muted-foreground">
                {{ label }}
            </p>
            <p v-if="hint" class="sr-only">{{ hint }}</p>
        </div>
    </Link>
</template>
