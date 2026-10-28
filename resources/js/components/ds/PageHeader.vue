<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, inject } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import { adminNavigation, isActivePath, normalizePath } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { pageContainerContextKey } from './pageContext';

/**
 * Encabezado de pantalla: título, descripción, migas opcionales y acciones
 * (la primaria al final). En móvil las acciones bajan debajo del título.
 *
 * Dentro del panel muestra el icono de la sección (el mismo de la barra
 * lateral). `icon` lo reemplaza; `:icon="false"` lo oculta.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component | false;
        class?: HTMLAttributes['class'];
    }>(),
    { icon: undefined },
);

defineSlots<{
    breadcrumbs?: () => unknown;
    meta?: () => unknown;
    actions?: () => unknown;
    title?: () => unknown;
}>();

const context = inject(pageContainerContextKey, null);
const page = usePage();

const sectionIcon = computed<Component | null>(() => {
    if (props.icon === false) {
        return null;
    }

    if (props.icon) {
        return props.icon;
    }

    if (!context?.sectionIcons) {
        return null;
    }

    // El elemento de navegación más específico que coincide con la URL.
    const current = normalizePath(page.url);
    let best: { icon: Component; length: number } | null = null;

    for (const group of adminNavigation) {
        for (const item of group.items) {
            if (!isActivePath(current, item)) {
                continue;
            }

            const length = normalizePath(item.match ?? item.href).length;

            if (!best || length > best.length) {
                best = { icon: item.icon, length };
            }
        }
    }

    return best?.icon ?? null;
});
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
            <div class="flex min-w-0 items-start gap-3.5">
                <span
                    v-if="sectionIcon"
                    class="admin-pop relative hidden size-11 shrink-0 place-items-center rounded-xl border border-primary/15 bg-linear-to-br from-primary-soft via-primary-soft/60 to-card text-primary shadow-xs sm:grid"
                    aria-hidden="true"
                >
                    <component
                        :is="sectionIcon"
                        class="size-5"
                        stroke-width="1.85"
                    />
                    <span
                        class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full border-2 border-background bg-oak"
                    />
                </span>
                <div class="min-w-0">
                    <h1
                        class="font-display text-2xl leading-tight font-semibold tracking-tight text-foreground md:text-[1.75rem]"
                    >
                        <slot name="title">{{ props.title }}</slot>
                    </h1>
                    <p
                        v-if="props.description"
                        class="mt-1.5 max-w-3xl text-sm text-muted-foreground md:text-[0.9375rem]"
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
            </div>
            <div
                v-if="$slots.actions"
                class="flex shrink-0 flex-wrap items-center gap-2 max-sm:w-full [&>*]:max-sm:flex-1"
            >
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
