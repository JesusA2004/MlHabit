<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Carrusel accesible por scroll-snap: deslizable en táctil, botones
 * anterior/siguiente, flechas del teclado con foco en la pista y sin
 * autoplay.
 */
defineProps<{ label: string }>();

const track = ref<HTMLElement | null>(null);
const atStart = ref(true);
const atEnd = ref(false);

function update() {
    const el = track.value;
    if (!el) return;
    atStart.value = el.scrollLeft <= 4;
    atEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
}

function step(dir: 1 | -1) {
    const el = track.value;
    if (!el) return;
    const item = el.querySelector<HTMLElement>(':scope > *');
    const amount = item ? item.getBoundingClientRect().width + 24 : el.clientWidth * 0.8;
    el.scrollBy({ left: dir * amount, behavior: 'smooth' });
}

function onKey(event: KeyboardEvent) {
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        step(1);
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        step(-1);
    }
}

let resize: ResizeObserver | null = null;
onMounted(() => {
    update();
    resize = new ResizeObserver(update);
    if (track.value) resize.observe(track.value);
});
onBeforeUnmount(() => resize?.disconnect());
</script>

<template>
    <div class="sn-carousel" role="region" aria-roledescription="carrusel" :aria-label="label">
        <div
            ref="track"
            class="sn-carousel-track scrollbar-none"
            tabindex="0"
            @scroll.passive="update"
            @keydown="onKey"
        >
            <slot />
        </div>
        <div class="sn-carousel-controls">
            <button type="button" :disabled="atStart" aria-label="Anterior" @click="step(-1)">
                <ChevronLeft class="size-5" aria-hidden="true" />
            </button>
            <button type="button" :disabled="atEnd" aria-label="Siguiente" @click="step(1)">
                <ChevronRight class="size-5" aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
