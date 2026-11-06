<script setup lang="ts">
import { MonitorPlay } from '@lucide/vue';
import { computed } from 'vue';
import { radiusCss, shadowCss } from '../tokens';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Video de YouTube o Vimeo. El servidor solo guarda proveedor + id
 * (App\Cms\Layout\VideoEmbed, lista blanca de dominios); aquí el
 * reproductor se arma con un dominio fijo y sin cookies de seguimiento.
 * En el editor se muestra una tarjeta (sin cargar el reproductor).
 */
const props = defineProps<{ block: Block }>();

const { bridge, str } = useBlock(() => props.block);

const PATTERNS: Record<string, RegExp> = {
    youtube: /^[A-Za-z0-9_-]{11}$/,
    vimeo: /^\d{6,12}$/,
};

const provider = computed(() => str('provider'));
const videoId = computed(() => str('videoId'));
const valid = computed(
    () => PATTERNS[provider.value]?.test(videoId.value) ?? false,
);
const src = computed(() => {
    if (!valid.value) return null;

    return provider.value === 'vimeo'
        ? `https://player.vimeo.com/video/${videoId.value}?dnt=1`
        : `https://www.youtube-nocookie.com/embed/${videoId.value}?rel=0&modestbranding=1`;
});

const ratio = computed(() => {
    const [w, h] = str('aspect', '16:9').split(':').map(Number);

    return w && h ? w / h : 16 / 9;
});
const title = computed(
    () =>
        str('title') ||
        (provider.value === 'vimeo' ? 'Video de Vimeo' : 'Video de YouTube'),
);
</script>

<template>
    <div class="sb-embed relative flex-1" :style="{ '--sb-ratio': ratio }">
        <div
            class="sb-embed__frame overflow-hidden bg-espresso"
            :style="{
                borderRadius: radiusCss(props.block.style.radius),
                boxShadow: shadowCss(props.block.style.shadow),
            }"
        >
            <iframe
                v-if="src && !bridge"
                :src="src"
                :title="title"
                class="absolute inset-0 h-full w-full border-0"
                loading="lazy"
                allow="
                    accelerometer;
                    autoplay;
                    clipboard-write;
                    encrypted-media;
                    gyroscope;
                    picture-in-picture;
                    fullscreen;
                "
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
            />
            <div
                v-else-if="bridge"
                class="absolute inset-0 grid place-items-center p-4 text-center text-sm text-white/75"
            >
                <span class="flex flex-col items-center gap-2">
                    <MonitorPlay class="size-8 text-oak" aria-hidden="true" />
                    <template v-if="valid">
                        {{ provider === 'vimeo' ? 'Vimeo' : 'YouTube' }} ·
                        {{ title }}
                        <span class="text-xs text-white/50"
                            >El reproductor se ve en la vista previa y en el
                            sitio</span
                        >
                    </template>
                    <template v-else
                        >Pega el enlace de YouTube o Vimeo en el panel
                        derecho</template
                    >
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sb-embed {
    container-type: size;
    min-height: 0;
}

.sb-embed__frame {
    position: absolute;
    inset: 0;
    margin: auto;
    width: min(100cqw, calc(100cqh * var(--sb-ratio)));
    aspect-ratio: var(--sb-ratio);
    max-height: 100cqh;
}
</style>
