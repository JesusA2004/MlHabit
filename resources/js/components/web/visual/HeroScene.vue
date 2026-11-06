<script setup lang="ts">
import { Hammer, PencilRuler, Ruler } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { useId } from 'vue';
import { cn } from '@/lib/utils';
import { ICON_STROKE } from './icons';

/**
 * Escena ilustrada de la portada cuando aún no hay fotografía: una cocina
 * vista a través de un arco, con el muro verde y los listones de roble del
 * logotipo, luz cálida de las lámparas y un rayo de sol. SVG en línea
 * (~4 KB, sin peticiones), decorativo (`aria-hidden`).
 *
 * Vida: las lámparas "respiran", el rayo de sol titila y las etiquetas
 * flotan (`.breathe`); todo con `transform`/`opacity` y quieto con
 * movimiento reducido. `chips=false` la deja sola (tarjetas pequeñas).
 */
const props = withDefaults(
    defineProps<{
        chips?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { chips: true },
);

// Ids únicos: puede haber varias escenas en la misma página (editor).
const uid = useId();
const id = (name: string) => `${uid}-${name}`;
const url = (name: string) => `url(#${id(name)})`;

const SLATS = Array.from({ length: 10 }, (_, i) => i);
const FLUTES = Array.from({ length: 27 }, (_, i) => i);
const PLANKS = [396, 426, 462, 504];
</script>

<template>
    <div
        :class="cn('hero-scene relative mx-auto h-full w-full', props.class)"
        :data-tilt="props.chips ? '3' : undefined"
        aria-hidden="true"
    >
        <svg
            viewBox="-12 -12 424 544"
            class="absolute inset-0 h-full w-full"
            preserveAspectRatio="xMidYMid meet"
            focusable="false"
        >
            <defs>
                <clipPath :id="id('arch')">
                    <path d="M0 520V200a200 200 0 0 1 400 0v320Z" />
                </clipPath>
                <linearGradient :id="id('wall')" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#335f50" />
                    <stop offset="1" stop-color="#1d3b32" />
                </linearGradient>
                <linearGradient :id="id('oak')" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#9a6a40" />
                    <stop offset=".5" stop-color="#c39062" />
                    <stop offset="1" stop-color="#a87648" />
                </linearGradient>
                <linearGradient :id="id('floor')" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#bfa98c" />
                    <stop offset="1" stop-color="#8f7458" />
                </linearGradient>
                <linearGradient :id="id('beam')" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#fff6e4" stop-opacity=".55" />
                    <stop offset="1" stop-color="#fff6e4" stop-opacity="0" />
                </linearGradient>
                <radialGradient :id="id('glow')">
                    <stop offset="0" stop-color="#ffd9a0" stop-opacity=".75" />
                    <stop
                        offset=".45"
                        stop-color="#f3b46b"
                        stop-opacity=".22"
                    />
                    <stop offset="1" stop-color="#f3b46b" stop-opacity="0" />
                </radialGradient>
                <linearGradient :id="id('shade')" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#000" stop-opacity="0" />
                    <stop offset="1" stop-color="#000" stop-opacity=".28" />
                </linearGradient>
            </defs>

            <!-- Aro de roble (como el del logotipo) -->
            <path
                d="M-8 528V200a208 208 0 0 1 416 0v328"
                fill="none"
                stroke="#b07d4f"
                stroke-width="2"
                opacity=".7"
            />

            <g :clip-path="url('arch')">
                <rect width="400" height="520" :fill="url('wall')" />

                <!-- Listones de roble -->
                <rect
                    v-for="i in SLATS"
                    :key="`s${i}`"
                    :x="268 + i * 13.5"
                    y="0"
                    width="10"
                    height="372"
                    :fill="url('oak')"
                    :opacity="i % 3 === 0 ? 0.92 : 1"
                />
                <rect
                    x="262"
                    y="0"
                    width="6"
                    height="372"
                    fill="#000"
                    opacity=".18"
                />

                <!-- Rayo de sol -->
                <polygon
                    class="scene-beam"
                    points="20,0 140,0 340,372 150,372"
                    :fill="url('beam')"
                />

                <!-- Alacenas -->
                <rect
                    x="34"
                    y="146"
                    width="218"
                    height="80"
                    rx="3"
                    fill="#efe8dc"
                />
                <path
                    d="M106.7 150v72M179.3 150v72"
                    stroke="#d8cfc0"
                    stroke-width="1.5"
                />
                <path
                    d="M98 208v10M115 208v10M244 208v10"
                    stroke="#b07d4f"
                    stroke-width="2.5"
                    stroke-linecap="round"
                />
                <!-- Tira de luz bajo alacena -->
                <rect
                    x="36"
                    y="226"
                    width="214"
                    height="3"
                    fill="#ffe2b0"
                    class="scene-led"
                />
                <rect
                    x="36"
                    y="229"
                    width="214"
                    height="40"
                    fill="#ffe2b0"
                    opacity=".1"
                />

                <!-- Salpicadero de piedra y barra -->
                <rect x="34" y="229" width="234" height="98" fill="#d9cfbf" />
                <path
                    d="M34 262h234M34 295h234M90 229v33M150 262v33M210 229v33M120 295v32M200 295v32"
                    stroke="#c9bdaa"
                    stroke-width="1"
                />
                <!-- Repisa: jarrón y plantita -->
                <rect
                    x="58"
                    y="300"
                    width="14"
                    height="27"
                    rx="6"
                    fill="#2b2420"
                />
                <ellipse cx="190" cy="317" rx="22" ry="10" fill="#efe8dc" />
                <rect
                    x="226"
                    y="292"
                    width="10"
                    height="35"
                    rx="2"
                    fill="#b07d4f"
                />
                <rect x="30" y="327" width="242" height="8" fill="#ece5d8" />
                <rect x="30" y="335" width="242" height="37" fill="#24463b" />

                <!-- Piso de duela -->
                <rect y="372" width="400" height="148" :fill="url('floor')" />
                <path
                    v-for="y in PLANKS"
                    :key="`p${y}`"
                    :d="`M0 ${y}H400`"
                    stroke="#7c6249"
                    stroke-opacity=".35"
                    stroke-width="1"
                />

                <!-- Lámparas colgantes -->
                <g class="scene-lamp">
                    <circle cx="140" cy="286" r="90" :fill="url('glow')" />
                    <circle
                        cx="248"
                        cy="286"
                        r="90"
                        :fill="url('glow')"
                        class="scene-lamp-b"
                    />
                </g>
                <path
                    d="M140 0v262M248 0v262"
                    stroke="#1a1512"
                    stroke-width="1.5"
                />
                <path d="M120 280a20 19 0 0 1 40 0Z" fill="#b07d4f" />
                <path d="M228 280a20 19 0 0 1 40 0Z" fill="#b07d4f" />
                <ellipse cx="140" cy="281" rx="12" ry="3" fill="#fff1d6" />
                <ellipse cx="248" cy="281" rx="12" ry="3" fill="#fff1d6" />

                <!-- Isla: cubierta de roble y frente acanalado -->
                <ellipse
                    cx="190"
                    cy="484"
                    rx="150"
                    ry="10"
                    fill="#000"
                    opacity=".22"
                />
                <rect x="70" y="402" width="240" height="80" fill="#f1ebe1" />
                <path
                    v-for="i in FLUTES"
                    :key="`f${i}`"
                    :d="`M${78 + i * 8.6} 408v70`"
                    stroke="#d6cbb9"
                    stroke-width="1.2"
                />
                <rect
                    x="70"
                    y="402"
                    width="240"
                    height="80"
                    :fill="url('shade')"
                />
                <rect
                    x="56"
                    y="390"
                    width="268"
                    height="12"
                    rx="2"
                    :fill="url('oak')"
                />

                <!-- Bancos -->
                <g stroke="#2b2420" stroke-width="3" stroke-linecap="round">
                    <path d="M116 446l-8 62M136 446l8 62M112 482h28" />
                    <path d="M226 446l-8 62M246 446l8 62M222 482h28" />
                </g>
                <rect
                    x="108"
                    y="438"
                    width="36"
                    height="9"
                    rx="4.5"
                    fill="#2b2420"
                />
                <rect
                    x="218"
                    y="438"
                    width="36"
                    height="9"
                    rx="4.5"
                    fill="#2b2420"
                />

                <!-- Planta -->
                <g class="scene-plant">
                    <path
                        d="M352 448c-30-20-38-52-26-78 14 18 24 44 26 78Z"
                        fill="#3f7a5f"
                    />
                    <path
                        d="M354 448c10-34 34-54 52-58-4 26-22 48-52 58Z"
                        fill="#2f5a4c"
                    />
                    <path
                        d="M352 448c-6-40 4-72 22-92 6 30 0 62-22 92Z"
                        fill="#4d8a6c"
                    />
                </g>
                <path d="M334 446h40l-5 42h-30Z" fill="#e9e1d3" />

                <!-- Viñeta suave -->
                <rect
                    width="400"
                    height="520"
                    fill="none"
                    stroke="#000"
                    stroke-opacity=".25"
                    stroke-width="40"
                />
            </g>
        </svg>

        <template v-if="props.chips">
            <span
                class="hero-chip breathe top-[30%] -left-[6%]"
                style="--breathe-duration: 9s"
            >
                <PencilRuler
                    class="size-4 text-oak"
                    :stroke-width="ICON_STROKE"
                />
                Diseño a la medida
            </span>
            <span
                class="hero-chip breathe top-[52%] -right-[4%]"
                style="--breathe-duration: 11s; --breathe-delay: -3s"
            >
                <Ruler class="size-4 text-oak" :stroke-width="ICON_STROKE" />
                Medimos tu espacio
            </span>
            <span
                class="hero-chip breathe bottom-[9%] left-[2%]"
                style="--breathe-duration: 12s; --breathe-delay: -6s"
            >
                <Hammer class="size-4 text-oak" :stroke-width="ICON_STROKE" />
                Fabricación e instalación
            </span>
        </template>
    </div>
</template>
