<script setup lang="ts">
import { Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { FromCanvas, ToCanvas } from '@/studio/bridge';
import { isStudioMessage } from '@/studio/bridge';

/**
 * Lienzo: iframe del mismo origen con el ancho real del dispositivo
 * elegido (las media queries son las reales) escalado para caber.
 */
const props = defineProps<{ src: string; deviceWidth: number }>();
const emit = defineEmits<{ message: [data: FromCanvas] }>();

const host = ref<HTMLElement | null>(null);
const iframe = ref<HTMLIFrameElement | null>(null);
const size = ref({ w: 0, h: 0 });
const ready = ref(false);

const scale = computed(() => {
    if (!size.value.w) return 1;

    return Math.min(1, (size.value.w - 48) / props.deviceWidth);
});
const frameHeight = computed(() => Math.max(400, (size.value.h - 32) / scale.value));

function post(message: ToCanvas) {
    iframe.value?.contentWindow?.postMessage(JSON.parse(JSON.stringify(message)), window.location.origin);
}
defineExpose({ post, ready });

function onMessage(event: MessageEvent) {
    if (event.source !== iframe.value?.contentWindow || !isStudioMessage<FromCanvas>(event)) return;
    if (event.data.type === 'studio:ready') ready.value = true;
    emit('message', event.data as FromCanvas);
}

let observer: ResizeObserver | null = null;
onMounted(() => {
    window.addEventListener('message', onMessage);
    observer = new ResizeObserver(([entry]) => {
        size.value = { w: entry.contentRect.width, h: entry.contentRect.height };
    });
    if (host.value) observer.observe(host.value);
});
onBeforeUnmount(() => {
    window.removeEventListener('message', onMessage);
    observer?.disconnect();
});
</script>

<template>
    <div ref="host" class="studio-canvas-bg relative h-full min-h-0 w-full overflow-hidden">
        <div
            class="absolute top-4 left-1/2 origin-top overflow-hidden rounded-md bg-[#0d0e10] shadow-[0_30px_80px_-30px_rgb(0_0_0/0.8)] ring-1 ring-white/10 transition-[width] duration-300"
            :style="{
                width: `${deviceWidth}px`,
                height: `${frameHeight}px`,
                transform: `translateX(-50%) scale(${scale})`,
            }"
        >
            <iframe
                ref="iframe"
                :src="src"
                title="Lienzo de edición"
                class="block size-full border-0"
            />
            <div v-if="!ready" class="absolute inset-0 grid place-items-center bg-[#0d0e10] text-[#aba9a3]">
                <span class="flex items-center gap-2 text-sm"><Loader2 class="size-4 animate-spin" /> Cargando lienzo…</span>
            </div>
        </div>
        <span class="pointer-events-none absolute right-3 bottom-2 text-[10px] text-muted-foreground tabular-nums">
            {{ deviceWidth }} px · {{ Math.round(scale * 100) }} %
        </span>
    </div>
</template>

<style>
.studio-canvas-bg {
    background-color: var(--color-muted);
    background-image: radial-gradient(color-mix(in srgb, var(--color-foreground) 10%, transparent) 1px, transparent 1px);
    background-size: 18px 18px;
}
</style>
