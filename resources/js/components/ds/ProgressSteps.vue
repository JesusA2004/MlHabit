<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export type ProgressStep = {
    key: string;
    title: string;
    description?: string | null;
};

/**
 * Recorrido del cliente (asesoría → visita → diseño → fabricación →
 * instalación…). Muestra pasos completados, el actual y los siguientes.
 * Horizontal en escritorio y vertical en móvil (o forzado con `orientation`).
 */
const props = withDefaults(
    defineProps<{
        steps: ProgressStep[];
        /** Clave del paso actual. Todos los anteriores cuentan como completados. */
        current: string;
        /** Marca también el paso actual como completado (recorrido terminado). */
        completed?: boolean;
        orientation?: 'responsive' | 'horizontal' | 'vertical';
        label?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        completed: false,
        orientation: 'responsive',
        label: 'Avance del proyecto',
    },
);

const currentIndex = computed(() =>
    Math.max(
        0,
        props.steps.findIndex((s) => s.key === props.current),
    ),
);

function state(index: number): 'done' | 'current' | 'upcoming' {
    if (
        index < currentIndex.value ||
        (props.completed && index === currentIndex.value)
    ) {
        return 'done';
    }

    return index === currentIndex.value ? 'current' : 'upcoming';
}

const horizontal = computed(() =>
    props.orientation === 'horizontal'
        ? 'flex-row'
        : props.orientation === 'vertical'
          ? 'flex-col'
          : 'flex-col md:flex-row',
);
</script>

<template>
    <nav :aria-label="props.label" :class="props.class">
        <ol :class="cn('flex gap-0', horizontal)">
            <li
                v-for="(step, index) in props.steps"
                :key="step.key"
                :aria-current="state(index) === 'current' ? 'step' : undefined"
                :class="
                    cn(
                        'group relative flex min-w-0 flex-1 gap-3',
                        props.orientation === 'horizontal'
                            ? 'flex-col'
                            : 'pb-6 last:pb-0',
                        props.orientation === 'responsive' &&
                            'md:flex-col md:pb-0',
                    )
                "
            >
                <!-- Conector -->
                <span
                    v-if="index < props.steps.length - 1"
                    aria-hidden="true"
                    :class="
                        cn(
                            'absolute bg-border',
                            props.orientation === 'horizontal'
                                ? 'top-3.5 right-0 left-9 h-px'
                                : 'top-9 bottom-1 left-3.5 w-px',
                            props.orientation === 'responsive' &&
                                'md:top-3.5 md:right-0 md:bottom-auto md:left-9 md:h-px md:w-auto',
                            state(index) === 'done' && 'bg-primary',
                        )
                    "
                />
                <span
                    :class="
                        cn(
                            'relative z-[1] grid size-7 shrink-0 place-items-center rounded-full text-xs font-semibold tabular-nums transition-colors',
                            state(index) === 'done' &&
                                'bg-primary text-primary-foreground',
                            state(index) === 'current' &&
                                'bg-card text-primary ring-2 ring-primary ring-offset-2 ring-offset-background',
                            state(index) === 'upcoming' &&
                                'bg-muted text-muted-foreground',
                        )
                    "
                >
                    <Check
                        v-if="state(index) === 'done'"
                        class="size-3.5"
                        stroke-width="3"
                        aria-hidden="true"
                    />
                    <template v-else>{{ index + 1 }}</template>
                </span>
                <div class="min-w-0 pr-3 md:pt-0.5">
                    <p
                        :class="
                            cn(
                                'text-sm font-medium',
                                state(index) === 'upcoming'
                                    ? 'text-muted-foreground'
                                    : 'text-foreground',
                            )
                        "
                    >
                        {{ step.title }}
                        <span class="sr-only">
                            ({{
                                state(index) === 'done'
                                    ? 'completado'
                                    : state(index) === 'current'
                                      ? 'en curso'
                                      : 'pendiente'
                            }})
                        </span>
                    </p>
                    <p
                        v-if="step.description"
                        class="mt-0.5 text-xs text-muted-foreground"
                    >
                        {{ step.description }}
                    </p>
                </div>
            </li>
        </ol>
    </nav>
</template>
