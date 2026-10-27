<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight, X } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { scrollToElement } from '@/lib/motion';
import { useJourney } from '@/lib/motion/journey';
import type { JourneyStep } from '@/lib/motion/journey';
import { cn } from '@/lib/utils';

/**
 * Guía del recorrido: un riel discreto (vertical a la derecha en escritorio,
 * barra fina arriba en móvil) que muestra en qué parte de la historia está
 * la visita, y un botón flotante con la siguiente acción lógica. Nunca tapa
 * formularios ni el pie; en páginas transaccionales desaparece.
 */
const journey = useJourney();
const { steps, current, currentIndex, present, progress } = journey;

const dismissed = ref(false);
const STORAGE_KEY = 'mlh-journey-cta-dismissed';

onMounted(() => {
    try {
        dismissed.value = sessionStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        dismissed.value = false;
    }
});

function dismiss() {
    dismissed.value = true;

    try {
        sessionStorage.setItem(STORAGE_KEY, '1');
    } catch {
        // Sin almacenamiento: se oculta solo en esta vista.
    }
}

const railVisible = computed(
    () =>
        !journey.quiet.value &&
        !journey.footerVisible.value &&
        currentIndex.value >= 0,
);

const currentStep = computed<JourneyStep | null>(
    () => steps.find((s) => s.key === current.value) ?? null,
);

/** Una página puede afinar el destino del botón (`data-journey-cta-href`). */
const ctaOverride = ref<{ href: string; label: string | null } | null>(null);

function readOverride() {
    const el = document.querySelector<HTMLElement>(
        '#contenido [data-journey-cta-href]',
    );
    ctaOverride.value = el
        ? {
              href: el.dataset.journeyCtaHref ?? '',
              label: el.dataset.journeyCtaLabel ?? null,
          }
        : null;
}

const cta = computed(() => {
    const step = currentStep.value;

    if (!step) return null;

    const override =
        ctaOverride.value && step.cta.href === '/solicitar'
            ? ctaOverride.value
            : null;

    return {
        label: override?.label || step.cta.label,
        href: override?.href || step.cta.href,
    };
});

const ctaVisible = computed(
    () =>
        !journey.quiet.value &&
        !dismissed.value &&
        journey.scrolledIn.value &&
        !journey.blocked.value &&
        !journey.footerVisible.value &&
        cta.value !== null,
);

const ctaKey = computed(() => `${current.value}-${cta.value?.label}`);

/** Al cambiar de paso, la etiqueta del riel se asoma un momento. */
const announce = ref(false);
let announceTimer = 0;

watch(current, (value, previous) => {
    // El override depende del contenido de la página actual.
    void nextTick(readOverride);

    if (!value || value === previous) return;

    announce.value = true;
    window.clearTimeout(announceTimer);
    announceTimer = window.setTimeout(() => (announce.value = false), 2200);
});

const page = usePage();

watch(
    () => page.url,
    () => window.setTimeout(readOverride, 60),
);

onMounted(readOverride);
onBeforeUnmount(() => window.clearTimeout(announceTimer));

function go(step: JourneyStep, event: MouseEvent) {
    const anchor = journey.anchorFor(step.key);
    event.preventDefault();

    if (anchor) {
        scrollToElement(anchor);

        return;
    }

    router.visit(step.href);
}

function stepState(index: number): 'done' | 'current' | 'next' {
    if (index < currentIndex.value) return 'done';
    if (index === currentIndex.value) return 'current';

    return 'next';
}

const railFill = computed(() => {
    if (currentIndex.value < 0) return 0;

    return currentIndex.value / (steps.length - 1);
});
</script>

<template>
    <!-- Escritorio: riel vertical -->
    <Transition
        enter-active-class="transition duration-500 ease-out"
        enter-from-class="opacity-0 translate-x-3"
        leave-active-class="transition duration-300 ease-in"
        leave-to-class="opacity-0 translate-x-3"
    >
        <nav
            v-if="railVisible"
            data-journey-rail
            aria-label="Tu recorrido por ML Habitat"
            class="fixed top-1/2 right-4 z-30 hidden -translate-y-1/2 xl:block"
        >
            <ol class="relative flex flex-col items-end gap-1">
                <span
                    class="absolute top-4 right-[15px] bottom-4 w-px bg-foreground/12"
                    aria-hidden="true"
                />
                <span
                    class="absolute top-4 right-[15px] w-px origin-top bg-primary transition-[height] duration-700 ease-out"
                    :style="{ height: `calc((100% - 2rem) * ${railFill})` }"
                    aria-hidden="true"
                />
                <li v-for="(step, i) in steps" :key="step.key">
                    <a
                        :href="step.href"
                        :aria-current="
                            stepState(i) === 'current' ? 'step' : undefined
                        "
                        class="group flex h-8 items-center gap-3 rounded-full pl-3 outline-none"
                        @click="go(step, $event)"
                    >
                        <span
                            :class="
                                cn(
                                    'rounded-full bg-background/90 px-2.5 py-1 text-xs font-medium whitespace-nowrap shadow-xs ring-1 ring-border backdrop-blur transition-all duration-300 ease-out',
                                    stepState(i) === 'current' && announce
                                        ? 'translate-x-0 text-foreground opacity-100'
                                        : 'pointer-events-none translate-x-2 text-muted-foreground opacity-0 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:translate-x-0 group-focus-visible:opacity-100',
                                )
                            "
                        >
                            {{ step.label }}
                            <ArrowUpRight
                                v-if="!present.includes(step.key)"
                                class="-mt-0.5 ml-0.5 inline size-3 text-muted-foreground"
                                :stroke-width="1.5"
                                aria-hidden="true"
                            />
                            <span
                                v-if="stepState(i) === 'current'"
                                class="sr-only"
                                >(estás aquí)</span
                            >
                        </span>
                        <span
                            class="relative grid size-[31px] place-items-center"
                            aria-hidden="true"
                        >
                            <span
                                :class="
                                    cn(
                                        'rounded-full transition-all duration-500 ease-out group-focus-visible:ring-3 group-focus-visible:ring-ring/50',
                                        stepState(i) === 'current'
                                            ? 'size-3 bg-primary ring-4 ring-primary/15'
                                            : stepState(i) === 'done'
                                              ? 'size-2 bg-primary'
                                              : 'size-2 border border-foreground/30 bg-background group-hover:border-primary',
                                    )
                                "
                            />
                        </span>
                    </a>
                </li>
            </ol>
        </nav>
    </Transition>

    <!-- Móvil: barra fina segmentada -->
    <div
        v-if="!journey.quiet.value && currentIndex >= 0"
        class="pointer-events-none fixed inset-x-0 top-0 z-50 flex h-[3px] gap-[3px] xl:hidden"
        aria-hidden="true"
    >
        <span
            v-for="(step, i) in steps"
            :key="step.key"
            class="relative flex-1 overflow-hidden bg-foreground/8"
        >
            <span
                class="absolute inset-y-0 left-0 bg-primary transition-[width] duration-500 ease-out"
                :style="{
                    width:
                        i < currentIndex
                            ? '100%'
                            : i === currentIndex
                              ? `${Math.max(18, Math.round(progress * 100))}%`
                              : '0%',
                }"
            />
        </span>
    </div>

    <!-- Botón flotante contextual -->
    <Transition
        enter-active-class="transition duration-500 ease-out"
        enter-from-class="opacity-0 translate-y-4 scale-95"
        leave-active-class="transition duration-250 ease-in"
        leave-to-class="opacity-0 translate-y-4 scale-95"
    >
        <div
            v-if="ctaVisible && cta"
            data-journey-cta
            class="fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 flex justify-center sm:inset-x-auto sm:right-6 sm:bottom-6"
        >
            <div
                class="flex max-w-full items-center gap-1 rounded-full bg-espresso/95 p-1.5 pl-4 text-espresso-foreground shadow-lg ring-1 ring-white/10 backdrop-blur"
                role="region"
                aria-label="Siguiente paso sugerido"
            >
                <p
                    class="mr-2 hidden min-w-0 truncate text-xs text-espresso-foreground/70 sm:block"
                >
                    Paso {{ currentIndex + 1 }} de {{ steps.length }} ·
                    {{ currentStep?.label }}
                </p>
                <p class="mr-2 min-w-0 truncate text-xs sm:hidden">
                    {{ currentIndex + 1 }}/{{ steps.length }}
                </p>
                <Transition
                    mode="out-in"
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-2"
                    leave-active-class="transition duration-150 ease-in"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <Link
                        :key="ctaKey"
                        :href="cta.href"
                        data-magnetic="0.2"
                        class="btn-nudge btn-sweep inline-flex h-10 shrink-0 items-center gap-2 rounded-full bg-oak px-4 text-sm font-medium text-white [--sweep:#8a5f38]"
                    >
                        {{ cta.label }}
                        <ArrowRight
                            class="nudge size-4"
                            :stroke-width="1.75"
                            aria-hidden="true"
                        />
                    </Link>
                </Transition>
                <button
                    type="button"
                    class="grid size-10 shrink-0 place-items-center rounded-full text-espresso-foreground/70 transition-colors hover:bg-white/10 hover:text-white"
                    aria-label="Ocultar sugerencia"
                    @click="dismiss"
                >
                    <X class="size-4" :stroke-width="1.75" />
                </button>
            </div>
        </div>
    </Transition>
</template>
