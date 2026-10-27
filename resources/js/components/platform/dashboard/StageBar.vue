<script setup lang="ts">
import { computed } from 'vue';
import { formatNumber, formatPercent } from '@/lib/format';

export type Stage = {
    status: string;
    label: string;
    tone: string;
    step: number;
    count: number;
};

/**
 * Proyectos activos por etapa: barra segmentada (una sola tonalidad,
 * de claro a oscuro según avance del recorrido) + leyenda con conteos.
 * La identidad nunca depende solo del color: la leyenda numera cada etapa.
 */
const props = defineProps<{ stages: Stage[] }>();

const total = computed(() => props.stages.reduce((sum, s) => sum + s.count, 0));
const maxStep = computed(() => Math.max(1, ...props.stages.map((s) => s.step)));

/** Opacidad secuencial del primario por etapa (0.35 → 1). */
function opacity(stage: Stage): number {
    return 0.35 + (0.65 * Math.max(stage.step, 1)) / maxStep.value;
}

function share(stage: Stage): string {
    return formatPercent(total.value ? stage.count / total.value : 0);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="flex h-3 w-full gap-0.5 overflow-hidden rounded-full bg-muted"
            role="img"
            :aria-label="`Proyectos activos por etapa: ${props.stages.map((s) => `${s.label} ${s.count}`).join(', ')}`"
        >
            <div
                v-for="stage in props.stages"
                :key="stage.status"
                class="h-full bg-primary first:rounded-l-full last:rounded-r-full"
                :style="{
                    flexGrow: stage.count,
                    flexBasis: 0,
                    opacity: opacity(stage),
                }"
                :title="`${stage.label}: ${stage.count} (${share(stage)})`"
            />
        </div>
        <ul class="grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
            <li
                v-for="stage in props.stages"
                :key="stage.status"
                class="flex items-center gap-2"
            >
                <span
                    class="size-2.5 shrink-0 rounded-sm bg-primary"
                    :style="{ opacity: opacity(stage) }"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 truncate">{{ stage.label }}</span>
                <span class="font-medium tabular-nums">{{
                    formatNumber(stage.count)
                }}</span>
                <span
                    class="w-10 text-right text-xs text-muted-foreground tabular-nums"
                    >{{ share(stage) }}</span
                >
            </li>
        </ul>
    </div>
</template>
