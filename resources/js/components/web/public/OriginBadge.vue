<script setup lang="ts">
import { Camera, PenTool } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Etiqueta obligatoria de origen visual: "Proyecto real" (foto de un
 * trabajo entregado) o "Render conceptual" (propuesta, no entregada).
 */
const props = withDefaults(
    defineProps<{
        origin: 'render' | 'real_photo' | null | undefined;
        label?: string | null;
        variant?: 'overlay' | 'soft';
        class?: HTMLAttributes['class'];
    }>(),
    { variant: 'overlay' },
);
</script>

<template>
    <span
        v-if="props.origin"
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                props.variant === 'overlay'
                    ? 'bg-black/55 text-white backdrop-blur-sm'
                    : props.origin === 'real_photo'
                      ? 'bg-success-soft text-success-ink'
                      : 'bg-info-soft text-info-ink',
                props.class,
            )
        "
    >
        <Camera
            v-if="props.origin === 'real_photo'"
            class="size-3.5"
            aria-hidden="true"
        />
        <PenTool v-else class="size-3.5" aria-hidden="true" />
        {{
            props.label ??
            (props.origin === 'real_photo'
                ? 'Proyecto real'
                : 'Render conceptual')
        }}
    </span>
</template>
