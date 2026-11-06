<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Logotipo oficial de ML Hábitat: la insignia redonda completa (monograma
 * "ML", "ML HÁBITAT" y "Cocinas · Clósets · Interiores" sobre la cocina de
 * muro verde, con aro de roble). Fuente: `public/logoSinFondo.png`; las
 * versiones en `public/brand/logo-{64,128,256,512}.{png,webp}` son la misma
 * insignia solo redimensionada (Lanczos, sin recortes).
 *
 * El tamaño lo da `className` (p. ej. `size-8`); la imagen nunca provoca
 * saltos de diseño porque lleva dimensiones intrínsecas cuadradas, y
 * `sizes` + `srcset` eligen la resolución nítida en pantallas retina.
 * `variant` se conserva por compatibilidad: la insignia es la misma sobre
 * cualquier fondo.
 */
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        className?: HTMLAttributes['class'];
        /** @deprecated La insignia oficial no tiene versión monocroma. */
        variant?: 'color' | 'mono';
        /** Texto alternativo. Sin él, la imagen es decorativa (la acompaña el nombre). */
        title?: string;
        /** Ancho mostrado aproximado, para elegir la resolución (atributo `sizes`). */
        sizes?: string;
        /** `eager` + prioridad alta para logotipos dentro del primer pintado. */
        loading?: 'lazy' | 'eager';
    }>(),
    { variant: 'color', sizes: '48px', loading: 'eager' },
);
</script>

<template>
    <img
        src="/brand/logo-128.webp"
        srcset="
            /brand/logo-64.webp   64w,
            /brand/logo-128.webp 128w,
            /brand/logo-256.webp 256w,
            /brand/logo-512.webp 512w
        "
        :sizes="props.sizes"
        width="128"
        height="128"
        :alt="props.title ?? ''"
        :aria-hidden="props.title ? undefined : 'true'"
        :loading="props.loading"
        :fetchpriority="props.loading === 'eager' ? 'high' : undefined"
        decoding="async"
        draggable="false"
        :class="
            cn(
                'aspect-square rounded-full object-contain select-none',
                props.className,
            )
        "
        v-bind="$attrs"
    />
</template>
