<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Gráfica de líneas/área de dos series (visitas y únicos) sin dependencias.
 * SVG responsivo con trazo de grosor constante, rejilla recesiva, punto y
 * tooltip al pasar el cursor o enfocar, y tabla accesible oculta.
 */
const props = defineProps<{
    title: string;
    data: { label: string; total: number; unique: number }[];
}>();

const W = 720;
const H = 240;
const PAD = { top: 16, right: 8, bottom: 24, left: 8 };

const max = computed(() => Math.max(1, ...props.data.map((d) => d.total)));
const stepX = computed(() => {
    const inner = W - PAD.left - PAD.right;

    return props.data.length > 1 ? inner / (props.data.length - 1) : 0;
});

function x(i: number): number {
    return PAD.left + i * stepX.value;
}
function y(value: number): number {
    const inner = H - PAD.top - PAD.bottom;

    return PAD.top + inner - (value / max.value) * inner;
}

function linePath(key: 'total' | 'unique'): string {
    return props.data
        .map(
            (d, i) =>
                `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(d[key]).toFixed(1)}`,
        )
        .join(' ');
}

const areaPath = computed(() => {
    if (!props.data.length) return '';
    const base = H - PAD.bottom;

    return `${linePath('total')} L${x(props.data.length - 1).toFixed(1)},${base} L${x(0).toFixed(1)},${base} Z`;
});

const gridlines = computed(() => {
    const step = Math.max(1, Math.ceil(max.value / 4));
    const top = step * 4;

    return [0, 1, 2, 3, 4].map((i) => ({
        value: i * step,
        y: y(((i * step) / top) * max.value),
    }));
});

const hover = ref<number | null>(null);
const active = computed(() =>
    hover.value !== null ? props.data[hover.value] : null,
);

// Puntos espaciados del eje X (máx. 6 etiquetas para no saturar).
const xLabels = computed(() => {
    const n = props.data.length;
    if (n === 0) return [];
    const every = Math.max(1, Math.ceil(n / 6));

    return props.data.map((d, i) => ({
        i,
        label: d.label,
        show: i % every === 0 || i === n - 1,
    }));
});
</script>

<template>
    <figure class="flex flex-col">
        <div class="relative">
            <svg
                :viewBox="`0 0 ${W} ${H}`"
                class="h-56 w-full"
                preserveAspectRatio="none"
                role="img"
                :aria-label="title"
                @mouseleave="hover = null"
            >
                <defs>
                    <linearGradient id="trend-fill" x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0%"
                            stop-color="var(--chart-1)"
                            stop-opacity="0.22"
                        />
                        <stop
                            offset="100%"
                            stop-color="var(--chart-1)"
                            stop-opacity="0"
                        />
                    </linearGradient>
                </defs>

                <!-- rejilla -->
                <g aria-hidden="true">
                    <line
                        v-for="g in gridlines"
                        :key="g.value"
                        :x1="PAD.left"
                        :x2="W - PAD.right"
                        :y1="g.y"
                        :y2="g.y"
                        stroke="var(--border)"
                        stroke-width="1"
                        vector-effect="non-scaling-stroke"
                        :stroke-dasharray="g.value === 0 ? '0' : '3 4'"
                    />
                </g>

                <path :d="areaPath" fill="url(#trend-fill)" />
                <path
                    :d="linePath('total')"
                    fill="none"
                    stroke="var(--chart-1)"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    vector-effect="non-scaling-stroke"
                />
                <path
                    :d="linePath('unique')"
                    fill="none"
                    stroke="var(--muted-foreground)"
                    stroke-width="1.5"
                    stroke-dasharray="4 4"
                    stroke-linejoin="round"
                    vector-effect="non-scaling-stroke"
                />

                <!-- punto activo -->
                <circle
                    v-if="active && hover !== null"
                    :cx="x(hover)"
                    :cy="y(active.total)"
                    r="4"
                    fill="var(--chart-1)"
                    stroke="var(--card)"
                    stroke-width="2"
                    vector-effect="non-scaling-stroke"
                />

                <!-- zonas de interacción -->
                <rect
                    v-for="(d, i) in data"
                    :key="i"
                    :x="x(i) - stepX / 2"
                    :y="0"
                    :width="stepX || W"
                    :height="H"
                    fill="transparent"
                    tabindex="0"
                    :aria-label="`${d.label}: ${d.total} visitas, ${d.unique} únicos`"
                    @mouseenter="hover = i"
                    @focus="hover = i"
                />
            </svg>

            <!-- tooltip -->
            <div
                v-if="active && hover !== null"
                class="pointer-events-none absolute top-2 z-10 -translate-x-1/2 rounded-lg border bg-popover px-2.5 py-1.5 text-xs shadow-md"
                :style="{ left: `${(x(hover) / W) * 100}%` }"
            >
                <p class="font-medium">{{ active.label }}</p>
                <p class="flex items-center gap-1.5 text-muted-foreground">
                    <span class="size-2 rounded-full bg-brand" />
                    {{ active.total }} visitas
                </p>
                <p class="flex items-center gap-1.5 text-muted-foreground">
                    <span
                        class="size-2 rounded-full border border-dashed border-muted-foreground"
                    />
                    {{ active.unique }} únicos
                </p>
            </div>
        </div>

        <!-- eje x -->
        <div
            class="mt-2 flex justify-between px-1 text-[11px] text-muted-foreground"
        >
            <span
                v-for="l in xLabels"
                :key="l.i"
                :class="{ 'opacity-0': !l.show }"
                >{{ l.label }}</span
            >
        </div>

        <figcaption
            class="mt-3 flex items-center gap-4 text-xs text-muted-foreground"
        >
            <span class="flex items-center gap-1.5"
                ><span class="h-0.5 w-4 rounded bg-brand" /> Visitas</span
            >
            <span class="flex items-center gap-1.5"
                ><span
                    class="h-0 w-4 border-t border-dashed border-muted-foreground"
                />
                Visitantes únicos</span
            >
        </figcaption>

        <table class="sr-only">
            <caption>
                {{
                    title
                }}
            </caption>
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Visitas</th>
                    <th>Únicos</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(d, i) in data" :key="i">
                    <td>{{ d.label }}</td>
                    <td>{{ d.total }}</td>
                    <td>{{ d.unique }}</td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>
