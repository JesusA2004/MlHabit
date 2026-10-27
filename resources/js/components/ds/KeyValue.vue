<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export type KeyValueItem = {
    /** Clave estable; se usa también como nombre de slot (`#value-{key}`). */
    key: string;
    label: string;
    value?: string | number | null;
    /** Ocupa toda la fila en la rejilla de dos columnas. */
    full?: boolean;
};

/**
 * Lista de definiciones (<dl>) para fichas de detalle. `columns="2"` en
 * escritorio; en móvil siempre una columna. Personaliza un valor con el
 * slot `value-{key}`.
 */
const props = withDefaults(
    defineProps<{
        items: KeyValueItem[];
        columns?: 1 | 2;
        layout?: 'stacked' | 'inline';
        fallback?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { columns: 1, layout: 'inline', fallback: '—' },
);
</script>

<template>
    <dl
        data-slot="key-value"
        :class="
            cn(
                'grid gap-x-8',
                props.columns === 2 ? 'sm:grid-cols-2' : 'grid-cols-1',
                props.layout === 'inline' ? 'gap-y-0' : 'gap-y-4',
                props.class,
            )
        "
    >
        <div
            v-for="item in props.items"
            :key="item.key"
            :class="
                cn(
                    props.layout === 'inline'
                        ? 'grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 border-b border-border/70 py-2.5 last:border-b-0'
                        : 'flex flex-col gap-1',
                    item.full && props.columns === 2 && 'sm:col-span-2',
                )
            "
        >
            <dt class="text-sm text-muted-foreground">{{ item.label }}</dt>
            <dd class="min-w-0 text-sm font-medium break-words text-foreground">
                <slot :name="`value-${item.key}`" :item="item">
                    <template
                        v-if="
                            item.value !== null &&
                            item.value !== undefined &&
                            item.value !== ''
                        "
                    >
                        {{ item.value }}
                    </template>
                    <span v-else class="font-normal text-muted-foreground">{{
                        props.fallback
                    }}</span>
                </slot>
            </dd>
        </div>
    </dl>
</template>
