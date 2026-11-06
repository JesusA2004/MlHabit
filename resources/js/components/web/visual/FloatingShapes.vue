<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Formas que "respiran" muy despacio detrás de un encabezado: un aro, un
 * aro punteado, una muestra de roble, una de eucalipto y un punto. Solo
 * `transform`/`opacity` (clase `.breathe`), decorativas y recortadas al
 * contenedor (que debe ser `relative`). Ocultas en móvil salvo el aro.
 * Con movimiento reducido se quedan quietas.
 */
const props = withDefaults(
    defineProps<{
        tone?: 'light' | 'dark';
        /** `right` (por defecto) agrupa las formas a la derecha; `spread` las reparte. */
        layout?: 'right' | 'spread';
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'light', layout: 'right' },
);
</script>

<template>
    <div
        :class="
            cn(
                'pointer-events-none absolute inset-0 -z-10 overflow-hidden',
                props.class,
            )
        "
        aria-hidden="true"
    >
        <span
            :class="
                cn(
                    'breathe absolute size-56 rounded-full border sm:size-72',
                    props.layout === 'right'
                        ? '-top-16 -right-16'
                        : '-top-20 left-[8%]',
                    props.tone === 'dark'
                        ? 'border-white/10'
                        : 'border-foreground/[0.08]',
                )
            "
            style="--breathe-duration: 11s"
        />
        <span
            :class="
                cn(
                    'breathe absolute hidden size-40 rounded-full border border-dashed md:block',
                    props.layout === 'right'
                        ? 'top-[38%] right-[18%]'
                        : 'right-[10%] bottom-[8%]',
                    props.tone === 'dark' ? 'border-white/12' : 'border-oak/25',
                )
            "
            style="--breathe-duration: 14s; --breathe-delay: -4s"
        />
        <span
            :class="
                cn(
                    'breathe absolute hidden h-24 w-9 rotate-12 rounded-lg bg-oak/25 lg:block',
                    props.layout === 'right'
                        ? 'top-[22%] right-[9%]'
                        : 'top-[30%] right-[24%]',
                )
            "
            style="
                --breathe-duration: 9s;
                --breathe-delay: -2s;
                --breathe-rotate: 12deg;
            "
        >
            <span class="tex-grain absolute inset-0 rounded-lg" />
        </span>
        <span
            :class="
                cn(
                    'breathe absolute hidden h-20 w-8 -rotate-6 rounded-lg lg:block',
                    props.layout === 'right'
                        ? 'top-[48%] right-[5%]'
                        : 'bottom-[18%] left-[6%]',
                    props.tone === 'dark' ? 'bg-white/10' : 'bg-primary/15',
                )
            "
            style="
                --breathe-duration: 12s;
                --breathe-delay: -6s;
                --breathe-rotate: -6deg;
            "
        />
        <span
            :class="
                cn(
                    'breathe absolute hidden size-2.5 rounded-full bg-oak md:block',
                    props.layout === 'right'
                        ? 'top-[18%] right-[26%]'
                        : 'top-[22%] left-[40%]',
                )
            "
            style="--breathe-duration: 7s; --breathe-delay: -1s"
        />
    </div>
</template>
