<script setup lang="ts">
import { CircleAlert, CircleCheck, Info } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/** Aviso en línea para formularios (éxito, error o información). */
const props = withDefaults(
    defineProps<{
        tone?: 'success' | 'danger' | 'info';
        message?: string | null;
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'success' },
);

const icon = computed(
    () =>
        ({ success: CircleCheck, danger: CircleAlert, info: Info })[props.tone],
);
const toneClass = computed(
    () =>
        ({
            success: 'border-success/25 bg-success-soft text-success-ink',
            danger: 'border-danger/25 bg-danger-soft text-danger-ink',
            info: 'border-info/25 bg-info-soft text-info-ink',
        })[props.tone],
);
</script>

<template>
    <div
        v-if="props.message || $slots.default"
        :role="props.tone === 'danger' ? 'alert' : 'status'"
        :class="
            cn(
                'flex items-start gap-2.5 rounded-lg border px-4 py-3 text-sm',
                toneClass,
                props.class,
            )
        "
    >
        <component
            :is="icon"
            class="mt-0.5 size-4 shrink-0"
            aria-hidden="true"
        />
        <span
            ><slot>{{ props.message }}</slot></span
        >
    </div>
</template>
