<script lang="ts">
// La insignia se precarga al importar el componente: la capa suele abrirse
// justo cuando la red está ocupada y la imagen no alcanzaría a llegar.
if (typeof window !== 'undefined') {
    for (const size of [128, 256]) {
        const img = new Image();
        img.src = `/brand/logo-${size}.webp`;
    }
}
</script>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import BrandLoader from './BrandLoader.vue';
import ProgressBar from './ProgressBar.vue';

/**
 * Capa de "trabajando" para acciones largas que no deben interrumpirse
 * (publicar el sitio, guardar credenciales, subir un video…). Muestra la
 * insignia de ML Hábitat con un aro animado, el título de la acción, una
 * descripción y, si se conoce, el porcentaje.
 *
 * - `variant="fullscreen"` (por defecto): cubre toda la ventana, bloquea
 *   clics y scroll y toma el foco; al cerrarse lo devuelve.
 * - `variant="contained"`: cubre solo su contenedor (que debe ser
 *   `relative`); útil sobre una tarjeta o un formulario.
 *
 * Normalmente se maneja con `useLongTask`, que solo la muestra si la acción
 * tarda más de ~400 ms.
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        /** 0–100; `null` = progreso desconocido. */
        progress?: number | null;
        /** Texto bajo la barra (p. ej. "12 MB de 80 MB · quedan 30 s"). */
        detail?: string;
        variant?: 'fullscreen' | 'contained';
        cancelable?: boolean;
        cancelLabel?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        title: 'Un momento…',
        description: undefined,
        progress: null,
        detail: undefined,
        variant: 'fullscreen',
        cancelable: false,
        cancelLabel: 'Cancelar',
    },
);

const emit = defineEmits<{ cancel: [] }>();

// Con SSR el Teleport se activa tras montar: el HTML del servidor y la
// hidratación coinciden (la capa nunca está abierta al cargar).
const mounted = ref(false);
onMounted(() => (mounted.value = true));

const titleId = useId();
const descriptionId = useId();
const panel = ref<HTMLElement | null>(null);
let previousFocus: HTMLElement | null = null;
let previousOverflow = '';

function lock(on: boolean) {
    if (props.variant !== 'fullscreen' || typeof document === 'undefined') {
        return;
    }

    if (on) {
        previousFocus = document.activeElement as HTMLElement | null;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        void nextTick(() => panel.value?.focus({ preventScroll: true }));
    } else {
        document.body.style.overflow = previousOverflow;
        previousFocus?.focus?.({ preventScroll: true });
        previousFocus = null;
    }
}

watch(
    () => props.open,
    (open, wasOpen) => {
        if (open !== !!wasOpen) {
            lock(open);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (props.open) {
        lock(false);
    }
});

function onKeydown(event: KeyboardEvent) {
    // El foco no sale de la capa mientras trabaja.
    if (event.key === 'Tab') {
        const focusable = panel.value?.querySelectorAll<HTMLElement>('button');

        if (!focusable?.length) {
            event.preventDefault();
            panel.value?.focus();
        }
    }

    if (event.key === 'Escape' && props.cancelable) {
        emit('cancel');
    }
}
</script>

<template>
    <Teleport to="body" :disabled="props.variant !== 'fullscreen' || !mounted">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-if="props.open"
                data-slot="busy-overlay"
                :class="
                    cn(
                        'grid place-items-center p-4',
                        props.variant === 'fullscreen'
                            ? 'fixed inset-0 z-[120] bg-background/75 backdrop-blur-md'
                            : 'absolute inset-0 z-20 rounded-[inherit] bg-card/80 backdrop-blur-[3px]',
                        props.class,
                    )
                "
                @keydown="onKeydown"
                @click.stop
            >
                <div
                    ref="panel"
                    :role="
                        props.variant === 'fullscreen'
                            ? 'alertdialog'
                            : 'status'
                    "
                    :aria-modal="
                        props.variant === 'fullscreen' ? 'true' : undefined
                    "
                    aria-busy="true"
                    aria-live="polite"
                    :aria-labelledby="titleId"
                    :aria-describedby="
                        props.description ? descriptionId : undefined
                    "
                    tabindex="-1"
                    :class="
                        cn(
                            'busy-panel flex w-full flex-col items-center text-center outline-none',
                            props.variant === 'fullscreen'
                                ? 'max-w-sm rounded-2xl border bg-card px-8 py-9 shadow-lg'
                                : 'max-w-xs px-4 py-6',
                        )
                    "
                >
                    <BrandLoader
                        :size="props.variant === 'fullscreen' ? 'lg' : 'md'"
                        :progress="null"
                    />
                    <p
                        :id="titleId"
                        class="mt-6 text-base font-semibold text-foreground"
                    >
                        {{ props.title }}
                    </p>
                    <p
                        v-if="props.description"
                        :id="descriptionId"
                        class="mt-1.5 text-sm text-pretty text-muted-foreground"
                    >
                        {{ props.description }}
                    </p>

                    <div
                        v-if="props.progress !== null || props.detail"
                        class="mt-5 w-full"
                    >
                        <div
                            v-if="props.progress !== null"
                            class="mb-1.5 flex items-center justify-between text-xs text-muted-foreground"
                        >
                            <span>Progreso</span>
                            <span
                                class="font-medium text-foreground tabular-nums"
                                >{{ Math.round(props.progress) }}%</span
                            >
                        </div>
                        <ProgressBar
                            :value="props.progress"
                            size="md"
                            :label="props.title"
                        />
                        <p
                            v-if="props.detail"
                            class="mt-2 text-xs text-muted-foreground tabular-nums"
                        >
                            {{ props.detail }}
                        </p>
                    </div>

                    <slot />

                    <Button
                        v-if="props.cancelable"
                        variant="outline"
                        class="mt-6"
                        @click="emit('cancel')"
                    >
                        {{ props.cancelLabel }}
                    </Button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .busy-panel {
        animation: busy-panel-in 320ms cubic-bezier(0.22, 1, 0.36, 1);
    }

    @keyframes busy-panel-in {
        from {
            opacity: 0;
            transform: translateY(6px) scale(0.98);
        }
    }
}
</style>
