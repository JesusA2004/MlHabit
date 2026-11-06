<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Compass, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useTourProgress } from './progress';
import TourOverlay from './TourOverlay.vue';
import type { TourArea } from './types';
import { useTour } from './useTour';
import { useTourActions } from './useTourActions';

/**
 * Se monta una vez por layout (panel, portal, técnicos): dibuja la capa del
 * recorrido y, la primera vez que alguien entra, le ofrece el recorrido
 * general. Si lo empieza, lo termina o dice "Ahora no", no se vuelve a
 * ofrecer (se guarda en el servidor; localStorage solo como respaldo).
 */
const props = defineProps<{ area: TourArea }>();

const page = usePage();
const tour = useTour();
const progress = useTourProgress();
const { general, ctx, startGeneral } = useTourActions(props.area);

const offer = ref(false);
let timer: number | undefined;

const userId = computed(() => page.props.auth?.user?.id ?? null);

// Pantallas a pantalla completa (editor visual): ahí no se interrumpe.
const quietPage = computed(() => /\/editor(\/|$)/.test(page.url));

const hasSteps = computed(() =>
    general.value.steps.some((s) => ctx.value.can(s.permission)),
);

async function maybeOffer(): Promise<void> {
    if (!userId.value || !hasSteps.value) {
        return;
    }

    const map = await progress.load(userId.value);

    if (map[general.value.id] || tour.active.value || quietPage.value) {
        return;
    }

    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        if (!tour.active.value && !quietPage.value) {
            offer.value = true;
        }
    }, 1200);
}

function accept(): void {
    offer.value = false;

    // Visto: aunque lo deje a medias no se vuelve a ofrecer solo
    // (al terminarlo queda como completado).
    if (userId.value) {
        void progress.mark(userId.value, general.value.id, 'dismissed', 0);
    }

    startGeneral();
}

function decline(): void {
    offer.value = false;

    if (userId.value) {
        void progress.mark(userId.value, general.value.id, 'dismissed', 0);
    }
}

watch(
    () => tour.active.value,
    (active) => {
        if (active) {
            offer.value = false;
        }
    },
);

watch(quietPage, (quiet) => {
    if (quiet) {
        offer.value = false;
    }
});

onMounted(() => {
    void maybeOffer();
});

onBeforeUnmount(() => window.clearTimeout(timer));

const withTabBar = computed(() => props.area !== 'admin');
</script>

<template>
    <TourOverlay />

    <Transition
        enter-active-class="motion-safe:transition motion-safe:duration-300 motion-safe:ease-out"
        enter-from-class="motion-safe:translate-y-3 opacity-0"
        leave-active-class="motion-safe:transition motion-safe:duration-150"
        leave-to-class="opacity-0"
    >
        <section
            v-if="offer"
            role="region"
            aria-labelledby="tour-offer-title"
            data-tour-offer
            :class="[
                'fixed right-3 left-3 z-[60] rounded-xl border bg-card p-4 text-card-foreground shadow-xl sm:right-5 sm:left-auto sm:w-[22rem]',
                withTabBar
                    ? 'bottom-[calc(5.25rem+env(safe-area-inset-bottom))] lg:bottom-5'
                    : 'bottom-[calc(0.75rem+env(safe-area-inset-bottom))] sm:bottom-5',
            ]"
        >
            <div class="flex items-start gap-3">
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary"
                    aria-hidden="true"
                >
                    <Compass class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2
                        id="tour-offer-title"
                        class="text-sm font-semibold text-foreground"
                    >
                        ¿Te mostramos cómo funciona?
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Un recorrido guiado por
                        {{
                            area === 'admin'
                                ? 'los módulos del panel'
                                : area === 'portal'
                                  ? 'tu portal'
                                  : 'la app de campo'
                        }}. Lo puedes repetir cuando quieras desde el botón de
                        ayuda (?).
                    </p>
                </div>
                <button
                    type="button"
                    class="-mt-1 -mr-1 grid size-8 shrink-0 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    aria-label="Cerrar sin ver el recorrido"
                    @click="decline"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>
            <div class="mt-3 flex justify-end gap-2">
                <Button variant="ghost" size="sm" @click="decline"
                    >Ahora no</Button
                >
                <Button size="sm" @click="accept">
                    <Compass class="size-4" aria-hidden="true" />
                    Empezar recorrido
                </Button>
            </div>
        </section>
    </Transition>
</template>
