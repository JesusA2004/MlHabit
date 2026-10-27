<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import DateText from './DateText.vue';
import { toneClasses } from './tones';
import type { Tone } from './tones';

export type TimelineEvent = {
    id: string | number;
    title: string;
    description?: string | null;
    /** ISO o "YYYY-MM-DD". */
    date?: string | null;
    actor?: string | null;
    icon?: Component;
    tone?: Tone;
};

/**
 * Historial vertical (bitácora de un proyecto, cambios de estado, pagos).
 * Orden de lectura de arriba abajo; personaliza cada evento con el slot
 * `#event` o añade contenido extra con `#extra`.
 */
const props = withDefaults(
    defineProps<{
        events: TimelineEvent[];
        relativeDates?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { relativeDates: false },
);
</script>

<template>
    <ol data-slot="timeline" :class="cn('relative flex flex-col', props.class)">
        <li
            v-for="(event, index) in props.events"
            :key="event.id"
            class="relative flex gap-4 pb-6 last:pb-0"
        >
            <span
                v-if="index < props.events.length - 1"
                class="absolute top-9 bottom-0 left-[15px] w-px bg-border"
                aria-hidden="true"
            />
            <span
                :class="
                    cn(
                        'relative z-[1] grid size-8 shrink-0 place-items-center rounded-full ring-4 ring-card',
                        toneClasses[event.tone ?? 'neutral'].soft,
                    )
                "
                aria-hidden="true"
            >
                <component
                    :is="event.icon"
                    v-if="event.icon"
                    class="size-4"
                    stroke-width="2"
                />
                <span
                    v-else
                    :class="
                        cn(
                            'size-2 rounded-full',
                            toneClasses[event.tone ?? 'neutral'].dot,
                        )
                    "
                />
            </span>
            <div class="min-w-0 flex-1 pt-1">
                <slot name="event" :event="event">
                    <div
                        class="flex flex-col gap-x-3 gap-y-0.5 sm:flex-row sm:items-baseline sm:justify-between"
                    >
                        <p class="text-sm font-medium text-foreground">
                            {{ event.title }}
                        </p>
                        <DateText
                            v-if="event.date"
                            :value="event.date"
                            :relative="props.relativeDates"
                            format="datetime"
                            class="shrink-0 text-xs text-muted-foreground"
                        />
                    </div>
                    <p
                        v-if="event.description"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ event.description }}
                    </p>
                    <p
                        v-if="event.actor"
                        class="mt-1.5 text-xs text-muted-foreground"
                    >
                        Por
                        <span class="font-medium text-foreground">{{
                            event.actor
                        }}</span>
                    </p>
                </slot>
                <slot name="extra" :event="event" />
            </div>
        </li>
    </ol>
</template>
