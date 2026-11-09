<script setup lang="ts">
import { FileWarning } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import type { MigrationFailure } from './types';

/**
 * Archivos que no se pudieron mover: nombre, motivo recortado (el texto
 * completo queda en el `title`) e intentos. Muestra los primeros y deja
 * ver el resto con un botón para no alargar la página.
 */
const props = defineProps<{
    failures: MigrationFailure[];
    /** Total de fallidos (puede ser mayor que la lista recibida). */
    total: number;
}>();

const PREVIEW = 5;
const expanded = ref(false);

const visible = computed(() =>
    expanded.value ? props.failures : props.failures.slice(0, PREVIEW),
);

function shortError(error: string | null): string {
    if (!error) {
        return 'Sin detalle';
    }

    const firstLine = error.split('\n')[0].trim();

    return firstLine.length > 140 ? `${firstLine.slice(0, 140)}…` : firstLine;
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <h3
            class="flex items-center gap-1.5 text-sm font-medium text-danger-ink"
        >
            <FileWarning class="size-4" />
            Archivos con error ({{ formatNumber(props.total) }})
        </h3>
        <ul class="divide-y rounded-md border text-sm">
            <li
                v-for="failure in visible"
                :key="failure.id"
                class="flex flex-col gap-0.5 px-3 py-2 sm:flex-row sm:items-start sm:gap-3"
            >
                <span class="min-w-0 font-medium break-all sm:w-2/5">
                    {{ failure.filename }}
                </span>
                <span
                    class="min-w-0 flex-1 text-xs break-words text-muted-foreground sm:text-sm"
                    :title="failure.error ?? undefined"
                >
                    {{ shortError(failure.error) }}
                </span>
                <span
                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                >
                    {{ failure.attempts }}
                    {{ failure.attempts === 1 ? 'intento' : 'intentos' }}
                </span>
            </li>
        </ul>
        <p
            v-if="props.total > props.failures.length && expanded"
            class="text-xs text-muted-foreground"
        >
            Se muestran los primeros {{ formatNumber(props.failures.length) }}.
        </p>
        <Button
            v-if="props.failures.length > PREVIEW"
            variant="ghost"
            size="sm"
            class="self-start pointer-coarse:h-11"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            {{
                expanded
                    ? 'Ver menos'
                    : `Ver todos (${formatNumber(props.failures.length)})`
            }}
        </Button>
    </div>
</template>
