<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { createParticleField } from '@/lib/motion/particles';
import type {
    ParticleField,
    ParticleTone,
    ParticleVariant,
} from '@/lib/motion/particles';
import { cn } from '@/lib/utils';

/**
 * Fondo de partículas: polvo en un rayo de sol (`dust`), virutas de madera
 * (`shavings`) o ambos (`mixed`). Se coloca dentro de un contenedor
 * `relative` y lo cubre por completo (`absolute inset-0`), detrás del
 * contenido. Decorativo (`aria-hidden`), sin eventos de puntero; se pausa
 * fuera de pantalla y con la pestaña oculta; con movimiento reducido queda
 * quieto. Runtime: `lib/motion/particles.ts`.
 */
const props = withDefaults(
    defineProps<{
        variant?: ParticleVariant;
        tone?: ParticleTone;
        /** Multiplicador de densidad (0.2–2). */
        density?: number;
        /** Las partículas se apartan del cursor (solo puntero fino). */
        interactive?: boolean;
        /** Rayo de luz cálido diagonal detrás de las partículas. */
        beam?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'dust',
        tone: 'dark',
        density: 1,
        interactive: true,
        beam: false,
    },
);

const canvas = ref<HTMLCanvasElement | null>(null);
let field: ParticleField | null = null;

onMounted(() => {
    if (!canvas.value) {
        return;
    }

    field = createParticleField(canvas.value, {
        variant: props.variant,
        tone: props.tone,
        density: props.density,
        interactive: props.interactive,
    });
});

watch(
    () => [props.variant, props.tone, props.density, props.interactive],
    () =>
        field?.update({
            variant: props.variant,
            tone: props.tone,
            density: props.density,
            interactive: props.interactive,
        }),
);

onBeforeUnmount(() => {
    field?.destroy();
    field = null;
});
</script>

<template>
    <div
        :class="
            cn(
                'particle-field pointer-events-none absolute inset-0 overflow-hidden',
                props.class,
            )
        "
        aria-hidden="true"
    >
        <div v-if="props.beam" class="particle-beam" :data-tone="props.tone" />
        <canvas ref="canvas" class="absolute inset-0 size-full" />
    </div>
</template>
