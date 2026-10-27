<script setup lang="ts">
import FinishSwatches from '@/components/ds/FinishSwatches.vue';

/**
 * Aviso dentro del editor cuando una lista viva (categorías, proyectos,
 * testimonios, preguntas) aún no tiene elementos publicados.
 */
const props = defineProps<{ title: string; body: string }>();
</script>

<template>
    <div
        class="flex h-full min-h-48 flex-1 items-center gap-6 rounded-2xl border border-dashed border-oak/40 bg-[color-mix(in_oklab,var(--color-linen)_75%,transparent)] p-6 text-foreground"
    >
        <FinishSwatches
            class="hidden w-28 shrink-0 @2xl/sbpage:block"
            :labels="false"
        />
        <div>
            <p class="font-display text-lg font-semibold tracking-tight">
                {{ props.title }}
            </p>
            <p class="mt-1 max-w-prose text-sm text-muted-foreground">
                {{ props.body }}
            </p>
        </div>
    </div>
</template>
