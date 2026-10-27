<script setup lang="ts">
import { computed } from 'vue';

/**
 * Encuadre visual: clic/arrastre sobre la miniatura fija el punto focal
 * (object-position). Accesible con flechas del teclado (1 %, Shift 10 %).
 */
const props = defineProps<{ url: string; x: number; y: number }>();
const emit = defineEmits<{ change: [x: number, y: number] }>();

const style = computed(() => ({ left: `${props.x}%`, top: `${props.y}%` }));

function fromPointer(event: PointerEvent) {
    const el = event.currentTarget as HTMLElement;
    const r = el.getBoundingClientRect();
    const x = Math.round(Math.min(100, Math.max(0, ((event.clientX - r.left) / r.width) * 100)));
    const y = Math.round(Math.min(100, Math.max(0, ((event.clientY - r.top) / r.height) * 100)));
    emit('change', x, y);
}

function onDown(event: PointerEvent) {
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    fromPointer(event);
}

function onMove(event: PointerEvent) {
    if ((event.currentTarget as HTMLElement).hasPointerCapture(event.pointerId)) fromPointer(event);
}

function onKey(event: KeyboardEvent) {
    const step = event.shiftKey ? 10 : 1;
    const map: Record<string, [number, number]> = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    const d = map[event.key];
    if (!d) return;
    event.preventDefault();
    emit('change', Math.min(100, Math.max(0, props.x + d[0])), Math.min(100, Math.max(0, props.y + d[1])));
}
</script>

<template>
    <div
        class="relative w-full cursor-crosshair touch-none overflow-hidden rounded-md bg-muted select-none"
        role="slider"
        tabindex="0"
        aria-label="Punto focal de la imagen"
        :aria-valuetext="`Horizontal ${x} %, vertical ${y} %`"
        @pointerdown="onDown"
        @pointermove="onMove"
        @keydown="onKey"
    >
        <img :src="url" alt="" class="pointer-events-none block w-full" draggable="false" />
        <span class="pointer-events-none absolute inset-0 bg-black/20" />
        <span
            class="pointer-events-none absolute size-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_2px_rgb(0_0_0/0.5)]"
            :style="style"
        >
            <span class="absolute inset-1.5 rounded-full bg-brand" />
        </span>
    </div>
</template>
