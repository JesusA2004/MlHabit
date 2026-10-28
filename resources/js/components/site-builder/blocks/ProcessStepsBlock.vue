<script setup lang="ts">
import {
    CalendarCheck,
    ClipboardList,
    Factory,
    Hammer,
    MessagesSquare,
    PencilRuler,
    Ruler,
    Sparkles,
    Truck,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { ICON_STROKE } from '@/components/web/visual/icons';
import type { EditorialItem } from '@/types/web';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * "Cómo funciona": línea de tiempo que se rellena mientras lees.
 *
 * - La línea de progreso (`scroll-fill-y`) sigue la "línea de lectura" del
 *   runtime (`data-scroll-progress="track"`), y cada paso se enciende al
 *   cruzarla (`data-scroll-step` → `data-passed`).
 * - Sin JS o con movimiento reducido la línea está completa y todos los
 *   pasos encendidos: nunca se oculta texto.
 * - Cada paso lleva un icono deducido de su título (asesoría, medición,
 *   diseño…) y su número.
 */
const props = defineProps<{ block: Block }>();

const { dark, editing } = useBlock(() => props.block);

const steps = computed(() => {
    const value = props.block.props.steps;

    return Array.isArray(value) ? (value as EditorialItem[]) : [];
});

const STEP_ICONS: [RegExp, Component][] = [
    [/asesor|plát|conversa|llamada/i, MessagesSquare],
    [/visita|medici|medida/i, Ruler],
    [/dise[ñn]|propuesta|render/i, PencilRuler],
    [/cotiza|presupuesto/i, ClipboardList],
    [/fabrica|taller|producci/i, Factory],
    [/entrega|env[ií]o|traslado/i, Truck],
    [/instala|montaje|armado/i, Hammer],
    [/agenda|cita|fecha/i, CalendarCheck],
];

function iconFor(step: EditorialItem): Component {
    const text = `${step.title ?? ''} ${step.body ?? ''}`;

    return STEP_ICONS.find(([p]) => p.test(text))?.[1] ?? Sparkles;
}

/** Colores del punto: encendido (leído) y apagado (por leer). */
const dotVars = computed(() =>
    dark.value
        ? {
              '--dot-on-bg': 'var(--oak)',
              '--dot-on-fg': '#fff',
              '--dot-on-bd': 'var(--oak)',
              '--dot-off-bg': 'var(--espresso)',
              '--dot-off-fg': 'var(--oak)',
              '--dot-off-bd': 'rgb(255 255 255 / 0.18)',
          }
        : {
              '--dot-on-bg': 'var(--primary)',
              '--dot-on-fg': 'var(--primary-foreground)',
              '--dot-on-bd': 'var(--primary)',
              '--dot-off-bg': 'var(--background)',
              '--dot-off-fg': 'var(--oak-ink)',
              '--dot-off-bd': 'var(--border)',
          },
);
</script>

<template>
    <ol
        class="relative"
        :style="dotVars"
        :data-scroll-progress="editing ? undefined : 'track'"
    >
        <!-- Riel y relleno de progreso (centrados con los puntos). -->
        <span
            class="absolute top-6 bottom-6 left-[1.375rem] w-0.5 -translate-x-1/2 rounded-full @2xl/sbpage:left-[1.875rem]"
            :class="dark ? 'bg-white/12' : 'bg-border'"
            aria-hidden="true"
        >
            <span
                class="scroll-fill-y absolute inset-0 rounded-full"
                :class="
                    dark
                        ? 'bg-gradient-to-b from-oak to-oak/60'
                        : 'bg-gradient-to-b from-primary to-oak'
                "
            />
        </span>
        <li
            v-for="(step, i) in steps"
            :key="i"
            data-scroll-step
            class="relative flex gap-5 pb-10 last:pb-0 @2xl/sbpage:gap-7"
        >
            <span
                class="scroll-step-dot relative z-10 grid size-11 shrink-0 place-items-center rounded-full border-2 @2xl/sbpage:size-[3.75rem]"
                aria-hidden="true"
            >
                <component
                    :is="iconFor(step)"
                    class="size-5 @2xl/sbpage:size-6"
                    :stroke-width="ICON_STROKE"
                />
            </span>
            <div class="scroll-step-body min-w-0 pt-1.5 @2xl/sbpage:pt-3">
                <p
                    class="font-display text-xs font-semibold tracking-wide tabular-nums"
                    :class="dark ? 'text-oak' : 'text-oak-ink'"
                >
                    Paso {{ String(i + 1).padStart(2, '0') }}
                </p>
                <h3
                    class="mt-0.5 font-display text-xl font-semibold tracking-tight @2xl/sbpage:text-2xl"
                >
                    {{ step.title }}
                </h3>
                <p
                    v-if="step.body"
                    class="mt-2 max-w-prose text-[color:var(--sb-ink-muted)]"
                >
                    {{ step.body }}
                </p>
            </div>
        </li>
    </ol>
</template>
