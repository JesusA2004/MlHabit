<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatDate, formatNumber } from '@/lib/format';

/**
 * Prospectos nuevos por día (una sola serie, columnas finas). Hover/foco
 * en cada día muestra fecha y cantidad; incluye tabla accesible.
 */
const props = defineProps<{ points: { date: string; count: number }[] }>();

const max = computed(() => Math.max(1, ...props.points.map((p) => p.count)));
const total = computed(() => props.points.reduce((sum, p) => sum + p.count, 0));
const active = ref<number | null>(null);

const activePoint = computed(() =>
    active.value === null ? null : (props.points[active.value] ?? null),
);
</script>

<template>
    <figure class="flex flex-col gap-2">
        <figcaption
            class="flex items-baseline justify-between gap-2 text-xs text-muted-foreground"
        >
            <span
                >Prospectos nuevos por día · últimos
                {{ props.points.length }} días</span
            >
            <span class="tabular-nums" aria-live="polite">
                <template v-if="activePoint">
                    {{ formatDate(activePoint.date, 'medium') }}:
                    <strong class="font-semibold text-foreground">{{
                        formatNumber(activePoint.count)
                    }}</strong>
                </template>
                <template v-else
                    >Total:
                    <strong class="font-semibold text-foreground">{{
                        formatNumber(total)
                    }}</strong></template
                >
            </span>
        </figcaption>
        <div
            class="relative flex h-20 items-end gap-0.5 border-b border-border"
            @mouseleave="active = null"
        >
            <button
                v-for="(point, index) in props.points"
                :key="point.date"
                type="button"
                class="group flex h-full min-w-0 flex-1 items-end focus-visible:outline-none"
                :aria-label="`${formatDate(point.date, 'medium')}: ${point.count} prospectos`"
                @mouseenter="active = index"
                @focus="active = index"
                @blur="active = null"
            >
                <span
                    class="admin-grow-y w-full rounded-t-[3px] bg-linear-to-t from-primary to-primary/75 transition-opacity group-hover:opacity-100 group-focus-visible:ring-2 group-focus-visible:ring-ring"
                    :class="
                        active !== null && active !== index
                            ? 'opacity-50'
                            : 'opacity-90'
                    "
                    :style="{
                        height: point.count
                            ? `${Math.max(6, (point.count / max) * 100)}%`
                            : '2px',
                        opacity: point.count ? undefined : 0.25,
                        animationDelay: `${Math.min(index * 18, 540)}ms`,
                    }"
                />
            </button>
        </div>
        <div
            class="flex justify-between text-[11px] text-muted-foreground tabular-nums"
        >
            <span>{{ formatDate(props.points[0]?.date, 'medium') }}</span>
            <span>{{
                formatDate(
                    props.points[props.points.length - 1]?.date,
                    'medium',
                )
            }}</span>
        </div>
        <table class="sr-only">
            <caption>
                Prospectos nuevos por día
            </caption>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Prospectos</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="point in props.points" :key="point.date">
                    <td>{{ formatDate(point.date, 'long') }}</td>
                    <td>{{ point.count }}</td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>
