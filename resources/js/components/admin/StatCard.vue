<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

/** KPI: etiqueta, cifra en display y contexto opcional; enlazable. */
const props = defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    icon?: Component;
    href?: string;
    tone?: 'default' | 'brand' | 'success' | 'warning' | 'danger' | 'info';
}>();

const iconTone = {
    default: 'bg-muted text-muted-foreground',
    brand: 'bg-primary-soft text-primary',
    success: 'bg-success-soft text-success-ink',
    warning: 'bg-warning-soft text-warning-ink',
    danger: 'bg-danger-soft text-danger-ink',
    info: 'bg-info-soft text-info-ink',
} as const;
</script>

<template>
    <component
        :is="props.href ? Link : 'div'"
        :href="props.href"
        :class="
            cn(
                'group flex flex-col justify-between gap-4 rounded-xl border bg-card p-4 shadow-xs transition-[border-color,box-shadow] duration-150 sm:p-5',
                props.href &&
                    'hover:border-primary/40 hover:shadow-sm focus-visible:ring-3 focus-visible:ring-ring/40',
            )
        "
    >
        <div class="flex items-center justify-between gap-2">
            <span class="text-sm text-muted-foreground">{{ props.label }}</span>
            <span
                v-if="props.icon"
                :class="
                    cn(
                        'grid size-8 place-items-center rounded-lg',
                        iconTone[props.tone ?? 'default'],
                    )
                "
                aria-hidden="true"
            >
                <component :is="props.icon" class="size-4" />
            </span>
        </div>
        <div>
            <p
                class="font-display text-[1.75rem] leading-none font-semibold tracking-tight tabular-nums"
            >
                {{ props.value }}
            </p>
            <p v-if="props.hint" class="mt-2 text-xs text-muted-foreground">
                {{ props.hint }}
            </p>
        </div>
    </component>
</template>
