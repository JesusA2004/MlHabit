<script setup lang="ts">
import { ArrowLeft, ArrowRight, Check, X } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watch,
} from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { scrollTargetIntoView } from './targets';
import { useTour } from './useTour';

/**
 * Capa del recorrido: oscurece la pantalla, recorta un "foco" sobre el
 * elemento del paso y muestra la tarjeta con el texto y los controles.
 * Escritorio: tarjeta flotante junto al elemento. Móvil (< 640 px): hoja
 * inferior. Teclado: ← / → para moverse, Esc para salir; el foco queda
 * atrapado en la tarjeta mientras el recorrido está abierto.
 */
const tour = useTour();
const { state, target } = tour;

// La capa solo existe en el navegador: sin esto el HTML del servidor (SSR)
// no coincide con la hidratación del Teleport y Vue avisa de un desajuste.
const mounted = ref(false);
onMounted(() => (mounted.value = true));

const reducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)');
const isMobile = useMediaQuery('(max-width: 639px)');

const card = ref<HTMLElement | null>(null);
const primary = ref<InstanceType<typeof Button> | null>(null);
const titleId = useId();
const bodyId = useId();

type Box = { top: number; left: number; width: number; height: number };

const spot = ref<Box | null>(null);
const cardPos = ref<{ top: number; left: number } | null>(null);
const placementSide = ref<'top' | 'bottom' | 'left' | 'right' | 'center'>(
    'center',
);

const GAP = 14;
const MARGIN = 12;

const paragraphs = computed(() =>
    (tour.step.value?.body ?? '').split(/\n{2,}/).filter(Boolean),
);
const counter = computed(
    () => `Paso ${state.index + 1} de ${tour.total.value}`,
);
const progressPercent = computed(() =>
    tour.total.value
        ? Math.round(((state.index + 1) / tour.total.value) * 100)
        : 0,
);

function sheetHeight(): number {
    return isMobile.value ? (card.value?.offsetHeight ?? 260) + 8 : 0;
}

function measure(): void {
    const el = target.value;
    const step = tour.step.value;

    if (!el || !el.isConnected) {
        spot.value = null;
        placeCard(null);

        return;
    }

    const pad = step?.padding ?? 6;
    const rect = el.getBoundingClientRect();
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    // Recorta a la ventana (un elemento enorme no tapa los controles).
    const top = Math.max(rect.top - pad, 4);
    const left = Math.max(rect.left - pad, 4);
    const bottom = Math.min(rect.bottom + pad, vh - 4);
    const right = Math.min(rect.right + pad, vw - 4);

    spot.value =
        bottom > top && right > left
            ? { top, left, width: right - left, height: bottom - top }
            : null;
    placeCard(spot.value);
}

function placeCard(box: Box | null): void {
    if (isMobile.value) {
        cardPos.value = null;
        // Si el elemento vive abajo (pestañas fijas), la hoja sube arriba.
        const sheet = (card.value?.offsetHeight ?? 260) + 8;
        placementSide.value =
            box && box.top + box.height > window.innerHeight - sheet
                ? 'top'
                : 'bottom';

        return;
    }

    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const cw = card.value?.offsetWidth ?? 360;
    const ch = card.value?.offsetHeight ?? 220;

    if (!box) {
        placementSide.value = 'center';
        cardPos.value = {
            top: Math.max(MARGIN, (vh - ch) / 2),
            left: Math.max(MARGIN, (vw - cw) / 2),
        };

        return;
    }

    const wanted = tour.step.value?.placement ?? 'auto';
    const space = {
        bottom: vh - (box.top + box.height) - GAP,
        top: box.top - GAP,
        right: vw - (box.left + box.width) - GAP,
        left: box.left - GAP,
    };
    const fits = {
        bottom: space.bottom >= ch + MARGIN,
        top: space.top >= ch + MARGIN,
        right: space.right >= cw + MARGIN,
        left: space.left >= cw + MARGIN,
    };
    const order: Array<'bottom' | 'top' | 'right' | 'left'> =
        wanted !== 'auto'
            ? [wanted, 'bottom', 'top', 'right', 'left']
            : box.height > vh * 0.55
              ? ['right', 'left', 'bottom', 'top']
              : ['bottom', 'top', 'right', 'left'];
    const side = order.find((s) => fits[s]);
    const clampX = (x: number) =>
        Math.min(Math.max(x, MARGIN), vw - cw - MARGIN);
    const clampY = (y: number) =>
        Math.min(Math.max(y, MARGIN), vh - ch - MARGIN);
    const centerX = box.left + box.width / 2 - cw / 2;
    const centerY = box.top + box.height / 2 - ch / 2;

    switch (side) {
        case 'bottom':
            cardPos.value = {
                top: box.top + box.height + GAP,
                left: clampX(centerX),
            };
            break;
        case 'top':
            cardPos.value = { top: box.top - GAP - ch, left: clampX(centerX) };
            break;
        case 'right':
            cardPos.value = {
                top: clampY(centerY),
                left: box.left + box.width + GAP,
            };
            break;
        case 'left':
            cardPos.value = { top: clampY(centerY), left: box.left - GAP - cw };
            break;
        default:
            // No cabe a ningún lado: abajo a la derecha, sobre el elemento.
            cardPos.value = { top: vh - ch - MARGIN, left: vw - cw - MARGIN };
    }

    placementSide.value = side ?? 'bottom';
}

let frame = 0;

function schedule(): void {
    if (frame) {
        return;
    }

    frame = window.requestAnimationFrame(() => {
        frame = 0;
        tour.refreshTarget();
        measure();
    });
}

let resizeObserver: ResizeObserver | null = null;
let settleTimer: number | undefined;

function bind(): void {
    window.addEventListener('resize', schedule, { passive: true });
    window.addEventListener('scroll', schedule, {
        passive: true,
        capture: true,
    });
    window.addEventListener('keydown', onKeydown, true);

    if (typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(schedule);
    }
}

function unbind(): void {
    window.removeEventListener('resize', schedule);
    window.removeEventListener('scroll', schedule, { capture: true });
    window.removeEventListener('keydown', onKeydown, true);
    resizeObserver?.disconnect();
    resizeObserver = null;
    window.cancelAnimationFrame(frame);
    frame = 0;
    window.clearTimeout(settleTimer);
}

function focusables(): HTMLElement[] {
    return card.value
        ? Array.from(
              card.value.querySelectorAll<HTMLElement>(
                  'button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])',
              ),
          )
        : [];
}

function onKeydown(event: KeyboardEvent): void {
    if (!state.tour) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        void tour.skip();

        return;
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        if (tour.isLast.value) {
            void tour.finish();
        } else {
            tour.next();
        }

        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        tour.prev();

        return;
    }

    if (event.key === 'Tab') {
        const items = focusables();

        if (!items.length) {
            event.preventDefault();

            return;
        }

        const first = items[0];
        const last = items[items.length - 1];
        const active = document.activeElement as HTMLElement | null;
        const inside = !!active && !!card.value?.contains(active);

        if (event.shiftKey && (active === first || !inside)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !inside)) {
            event.preventDefault();
            first.focus();
        }
    }
}

function focusPrimary(): void {
    const el = (primary.value?.$el ?? null) as HTMLElement | null;
    (el ?? card.value)?.focus({ preventScroll: true });
}

let returnFocus: HTMLElement | null = null;

watch(
    () => state.tour,
    (now, before) => {
        if (now && !before) {
            returnFocus = document.activeElement as HTMLElement | null;
            bind();
        } else if (!now && before) {
            unbind();
            spot.value = null;
            returnFocus?.focus?.({ preventScroll: true });
            returnFocus = null;
        }
    },
);

// Cada paso nuevo: lleva el elemento a la vista, mide y enfoca.
watch(
    () => [state.phase, state.index, target.value] as const,
    async ([phase]) => {
        if (phase !== 'shown') {
            return;
        }

        await nextTick();
        resizeObserver?.disconnect();

        const el = target.value;

        if (el) {
            resizeObserver?.observe(el);
            scrollTargetIntoView(el, {
                smooth: !reducedMotion.value,
                reserveBottom: sheetHeight(),
                topOffset: isMobile.value ? 64 : 72,
            });
        }

        if (card.value) {
            resizeObserver?.observe(card.value);
        }

        measure();
        window.clearTimeout(settleTimer);
        // Tras el desplazamiento suave, vuelve a medir.
        settleTimer = window.setTimeout(measure, reducedMotion.value ? 0 : 380);
        await nextTick();
        focusPrimary();
    },
);

onBeforeUnmount(unbind);

const spotStyle = computed(() =>
    spot.value
        ? {
              top: `${spot.value.top}px`,
              left: `${spot.value.left}px`,
              width: `${spot.value.width}px`,
              height: `${spot.value.height}px`,
          }
        : null,
);

const cardStyle = computed(() =>
    !isMobile.value && cardPos.value
        ? { top: `${cardPos.value.top}px`, left: `${cardPos.value.left}px` }
        : undefined,
);

const animate = computed(() => !reducedMotion.value);
</script>

<template>
    <Teleport v-if="mounted" to="body">
        <div
            v-if="state.tour"
            class="tour-root fixed inset-0 z-[200]"
            data-tour-overlay
        >
            <!-- Bloquea la interacción con la pantalla durante el recorrido -->
            <div
                class="absolute inset-0"
                aria-hidden="true"
                @pointerdown.prevent
                @click.prevent
            />

            <!-- Foco recortado (o pantalla atenuada si no hay elemento) -->
            <div
                v-if="spotStyle"
                aria-hidden="true"
                :class="[
                    'pointer-events-none fixed rounded-xl ring-2 ring-primary/80 ring-offset-2 ring-offset-transparent',
                    'shadow-[0_0_0_9999px_rgb(24_20_17/0.58)]',
                    animate &&
                        'transition-[top,left,width,height] duration-300 ease-out',
                ]"
                :style="spotStyle"
            />
            <div
                v-else
                aria-hidden="true"
                :class="[
                    'pointer-events-none absolute inset-0 bg-[rgb(24_20_17/0.58)]',
                    animate && 'tour-fade',
                ]"
            />

            <!-- Tarjeta -->
            <div
                ref="card"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                :aria-describedby="bodyId"
                tabindex="-1"
                :data-placement="placementSide"
                data-tour-card
                :class="[
                    'fixed z-[1] flex flex-col bg-card text-card-foreground shadow-2xl outline-none',
                    isMobile && placementSide === 'top'
                        ? 'inset-x-0 top-0 max-h-[70svh] rounded-b-2xl border-b px-5 pt-[calc(1rem+env(safe-area-inset-top))] pb-4'
                        : isMobile
                          ? 'inset-x-0 bottom-0 max-h-[70svh] rounded-t-2xl border-t px-5 pt-3 pb-[calc(1rem+env(safe-area-inset-bottom))]'
                          : 'w-[min(380px,calc(100vw-24px))] rounded-xl border p-5',
                    animate &&
                        (isMobile
                            ? placementSide === 'top'
                                ? 'tour-sheet-top'
                                : 'tour-sheet'
                            : 'tour-pop transition-[top,left] duration-300 ease-out'),
                ]"
                :style="cardStyle"
            >
                <div
                    v-if="isMobile && placementSide !== 'top'"
                    class="mx-auto mb-3 h-1 w-10 shrink-0 rounded-full bg-border"
                    aria-hidden="true"
                />

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-medium text-primary">
                            {{ state.tour.title }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ counter }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="-mt-1 -mr-2 grid size-9 shrink-0 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Cerrar el recorrido"
                        @click="tour.skip()"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>

                <div
                    class="mt-2 h-1 w-full overflow-hidden rounded-full bg-muted"
                    aria-hidden="true"
                >
                    <div
                        :class="[
                            'h-full rounded-full bg-primary',
                            animate && 'transition-[width] duration-300',
                        ]"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>

                <div class="mt-4 min-h-0 overflow-y-auto">
                    <h2
                        :id="titleId"
                        class="font-display text-lg leading-snug font-semibold tracking-tight text-foreground"
                    >
                        {{ tour.step.value?.title }}
                    </h2>
                    <div
                        :id="bodyId"
                        class="mt-2 flex flex-col gap-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        <p v-for="(p, i) in paragraphs" :key="i">{{ p }}</p>
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <Button
                        v-if="!tour.isLast.value"
                        variant="ghost"
                        size="sm"
                        class="-ml-2 text-muted-foreground"
                        @click="tour.skip()"
                        >Saltar</Button
                    >
                    <div class="ml-auto flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="tour.isFirst.value"
                            @click="tour.prev()"
                        >
                            <ArrowLeft class="size-4" aria-hidden="true" />
                            Anterior
                        </Button>
                        <Button
                            v-if="!tour.isLast.value"
                            ref="primary"
                            size="sm"
                            @click="tour.next()"
                        >
                            Siguiente
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Button>
                        <Button
                            v-else
                            ref="primary"
                            size="sm"
                            @click="tour.finish()"
                        >
                            <Check class="size-4" aria-hidden="true" />
                            Terminar
                        </Button>
                    </div>
                </div>
                <p
                    class="mt-3 hidden text-[11px] text-muted-foreground/80 sm:block"
                    aria-hidden="true"
                >
                    Usa ← y → para moverte · Esc para salir
                </p>

                <!-- Cargando la siguiente pantalla -->
                <div
                    v-if="state.phase === 'loading'"
                    class="absolute inset-0 grid place-items-center rounded-[inherit] bg-card/80"
                    role="status"
                >
                    <span
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Spinner /> Abriendo la siguiente pantalla…
                    </span>
                </div>
            </div>

            <p class="sr-only" aria-live="polite">
                {{
                    state.phase === 'shown'
                        ? `${counter}: ${tour.step.value?.title ?? ''}`
                        : ''
                }}
            </p>
        </div>
    </Teleport>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .tour-fade {
        animation: tour-fade 200ms ease-out both;
    }

    .tour-pop {
        animation: tour-pop 220ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
    }

    .tour-sheet {
        animation: tour-sheet 260ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
    }

    .tour-sheet-top {
        animation: tour-sheet-top 260ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
    }
}

@keyframes tour-fade {
    from {
        opacity: 0;
    }
}

@keyframes tour-pop {
    from {
        opacity: 0;
        transform: translateY(6px) scale(0.98);
    }
}

@keyframes tour-sheet-top {
    from {
        transform: translateY(-100%);
    }
}

@keyframes tour-sheet {
    from {
        transform: translateY(100%);
    }
}
</style>
