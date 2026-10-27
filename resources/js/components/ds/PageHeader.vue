<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Encabezado de pantalla: título, descripción, migas opcionales y acciones
 * (la primaria al final). En móvil las acciones bajan debajo del título.
 */
const props = defineProps<{
    title: string;
    description?: string;
    class?: HTMLAttributes['class'];
}>();

defineSlots<{
    breadcrumbs?: () => unknown;
    meta?: () => unknown;
    actions?: () => unknown;
    title?: () => unknown;
}>();
</script>

<template>
    <header
        data-slot="page-header"
        :class="cn('flex flex-col gap-4', props.class)"
    >
        <div v-if="$slots.breadcrumbs" class="-mb-1">
            <slot name="breadcrumbs" />
        </div>
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between sm:gap-6"
        >
            <div class="min-w-0">
                <h1
                    class="font-display text-2xl leading-tight font-semibold tracking-tight text-foreground md:text-[1.75rem]"
                >
                    <slot name="title">{{ props.title }}</slot>
                </h1>
                <p
                    v-if="props.description"
                    class="mt-1.5 max-w-2xl text-sm text-muted-foreground md:text-[0.9375rem]"
                >
                    {{ props.description }}
                </p>
                <div
                    v-if="$slots.meta"
                    class="mt-3 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <slot name="meta" />
                </div>
            </div>
            <div
                v-if="$slots.actions"
                class="flex shrink-0 flex-wrap items-center gap-2 [&>*]:max-sm:flex-1"
            >
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
