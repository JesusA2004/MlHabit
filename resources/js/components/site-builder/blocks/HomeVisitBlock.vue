<script setup lang="ts">
import { computed } from 'vue';
import OriginBadge from '@/components/web/public/OriginBadge.vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Tarjeta de la visita técnica. El costo solo aparece si existe una
 * política de visita activa (y está activado "mostrar el costo"); si no, se
 * explica cómo funciona la visita.
 */
const props = defineProps<{ block: Block }>();

const { section, dark } = useBlock(() => props.block);

const data = computed(() => section?.value.data[props.block.id] ?? {});
const visit = computed(() => data.value.visit ?? null);
const image = computed(() => data.value.image ?? null);

const visitSteps = [
    {
        title: 'Eliges día y horario',
        body: 'Ves los horarios libres en tu zona y pagas la visita en línea.',
    },
    {
        title: 'Un técnico mide tu espacio',
        body: 'Toma medidas, fotos y revisa muros, pisos e instalaciones.',
    },
    {
        title: 'Recibes diseño y cotización',
        body: 'Con esas medidas preparamos tu propuesta a la medida.',
    },
];
</script>

<template>
    <div
        class="relative overflow-hidden rounded-[2rem] ring-1"
        :class="
            dark ? 'bg-white/5 ring-white/10' : 'bg-card shadow-xs ring-border'
        "
    >
        <div v-if="image" class="relative aspect-[16/11]">
            <ResponsiveImage
                :image="image"
                :sizes="image.sizes"
                class="absolute inset-0"
            />
            <OriginBadge
                :origin="image.origin"
                :label="image.originLabel"
                class="absolute top-4 left-4"
            />
        </div>
        <div
            v-if="visit"
            class="p-6 @2xl/sbpage:p-8"
            :class="
                image
                    ? dark
                        ? 'border-t border-white/10'
                        : 'border-t border-border'
                    : ''
            "
        >
            <p class="text-sm text-[color:var(--sb-ink-muted)]">
                Costo de la visita
            </p>
            <p
                class="mt-1 font-display text-5xl font-semibold tracking-tight tabular-nums @2xl/sbpage:text-6xl"
            >
                {{ visit.feeFormatted }}
            </p>
            <p
                v-if="visit.isCreditable"
                class="mt-4 inline-flex rounded-full bg-success-soft px-3 py-1 text-sm font-medium text-success-ink"
            >
                Acreditable a tu proyecto
            </p>
            <p v-else class="mt-4 text-sm text-[color:var(--sb-ink-muted)]">
                Este costo no se acredita al proyecto.
            </p>
            <p
                v-if="visit.isCreditable && visit.creditConditions"
                class="mt-3 text-sm text-[color:var(--sb-ink-muted)]"
            >
                {{ visit.creditConditions }}
            </p>
        </div>
        <div v-else-if="!image" class="p-6 @2xl/sbpage:p-10">
            <p class="text-sm text-[color:var(--sb-ink-muted)]">
                Así funciona la visita
            </p>
            <ol class="mt-6 grid gap-6">
                <li
                    v-for="(step, i) in visitSteps"
                    :key="step.title"
                    class="grid grid-cols-[2.5rem_1fr] gap-4"
                >
                    <span
                        class="grid size-10 place-items-center rounded-full border font-display text-sm font-semibold tabular-nums"
                        :class="
                            dark
                                ? 'border-white/15 text-oak'
                                : 'border-border text-oak-ink'
                        "
                        aria-hidden="true"
                    >
                        {{ i + 1 }}
                    </span>
                    <div>
                        <p class="font-medium">{{ step.title }}</p>
                        <p
                            class="mt-1 text-sm text-[color:var(--sb-ink-muted)]"
                        >
                            {{ step.body }}
                        </p>
                    </div>
                </li>
            </ol>
            <p
                class="mt-8 border-t pt-5 text-sm text-[color:var(--sb-ink-muted)]"
                :class="dark ? 'border-white/10' : 'border-border'"
            >
                Te compartimos el costo y los horarios disponibles al agendar.
            </p>
        </div>
    </div>
</template>
