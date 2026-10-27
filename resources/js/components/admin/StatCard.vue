<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

/** KPI: cifra en display, etiqueta y contexto opcional. */
defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    icon?: Component;
    href?: string;
    tone?: 'default' | 'brand' | 'success' | 'warning';
}>();
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="group flex flex-col justify-between gap-3 rounded-xl border bg-card p-4 transition-colors"
        :class="href && 'hover:border-brand/60'"
    >
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-muted-foreground">{{ label }}</span>
            <span
                v-if="icon"
                class="grid size-8 place-items-center rounded-lg"
                :class="{
                    'bg-muted text-foreground': !tone || tone === 'default',
                    'bg-brand/15 text-brand': tone === 'brand',
                    'bg-success/12 text-success': tone === 'success',
                    'bg-warning/15 text-warning': tone === 'warning',
                }"
            >
                <component :is="icon" class="size-4" />
            </span>
        </div>
        <div>
            <p class="font-display text-3xl leading-none font-semibold tabular-nums">{{ value }}</p>
            <p v-if="hint" class="mt-1.5 text-xs text-muted-foreground">{{ hint }}</p>
        </div>
    </component>
</template>
