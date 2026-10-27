<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Gráfica de barras de una sola serie (HTML, sin dependencias):
 * barras finas con extremo redondeado anclado a la base, rejilla
 * recesiva, tooltip al pasar el cursor/enfocar y tabla accesible.
 */
const props = defineProps<{
    title: string;
    data: { label: string; value: number; sublabel?: string }[];
    unit?: string;
}>();

const max = computed(() => Math.max(1, ...props.data.map((d) => d.value)));
const ticks = computed(() => {
    const step = Math.max(1, Math.ceil(max.value / 4));
    const top = step * 4;

    return { top, values: [4, 3, 2, 1, 0].map((i) => i * step) };
});
const hover = ref<number | null>(null);
</script>

<template>
    <figure class="flex h-full flex-col">
        <div class="relative flex min-h-[200px] flex-1 gap-3" role="img" :aria-label="title">
            <!-- eje y -->
            <div class="flex flex-col justify-between pb-6 text-right text-[11px] text-muted-foreground tabular-nums" aria-hidden="true">
                <span v-for="t in ticks.values" :key="t">{{ t }}</span>
            </div>
            <div class="relative flex-1">
                <div class="absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between" aria-hidden="true">
                    <span v-for="t in ticks.values" :key="t" class="border-t border-dashed border-border/80" :class="{ 'border-solid border-border': t === 0 }" />
                </div>
                <div class="absolute inset-x-0 top-0 bottom-6 flex items-end justify-around gap-2">
                    <button
                        v-for="(d, i) in data"
                        :key="d.label"
                        type="button"
                        class="group relative flex h-full w-full max-w-14 items-end justify-center outline-none"
                        :aria-label="`${d.label}: ${d.value} ${unit ?? ''}`"
                        @mouseenter="hover = i"
                        @mouseleave="hover = null"
                        @focus="hover = i"
                        @blur="hover = null"
                    >
                        <span
                            class="block w-full max-w-7 rounded-t-[4px] bg-chart-1 transition-opacity"
                            :class="hover !== null && hover !== i ? 'opacity-40' : 'opacity-100'"
                            :style="{ height: `${(d.value / ticks.top) * 100}%`, minHeight: d.value ? '3px' : '0' }"
                        />
                        <span
                            v-if="hover === i"
                            class="pointer-events-none absolute bottom-full z-10 mb-2 rounded-md border bg-popover px-2.5 py-1.5 text-left text-xs whitespace-nowrap shadow-lg"
                            :style="{ bottom: `${(d.value / ticks.top) * 100}%` }"
                        >
                            <span class="block text-muted-foreground">{{ d.sublabel ?? d.label }}</span>
                            <span class="font-semibold tabular-nums">{{ d.value }} {{ unit }}</span>
                        </span>
                    </button>
                </div>
                <div class="absolute inset-x-0 bottom-0 flex justify-around gap-2 text-[11px] text-muted-foreground" aria-hidden="true">
                    <span v-for="d in data" :key="d.label" class="w-full max-w-14 truncate text-center">{{ d.label }}</span>
                </div>
            </div>
        </div>
        <table class="sr-only">
            <caption>{{ title }}</caption>
            <tbody>
                <tr v-for="d in data" :key="d.label"><th scope="row">{{ d.sublabel ?? d.label }}</th><td>{{ d.value }}</td></tr>
            </tbody>
        </table>
    </figure>
</template>
