<script setup lang="ts">
import { inkCss } from '../tokens';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Cifra con etiqueta. El valor lo escribe el equipo (texto libre): nunca se
 * siembran cifras inventadas. En el sitio público la cifra cuenta desde 0
 * hasta el número que ya muestra el texto (`data-countup`) y una regla de
 * roble se dibuja encima al revelarse.
 */
const props = defineProps<{ block: Block }>();

const { str, bridge } = useBlock(() => props.block);
</script>

<template>
    <div :style="{ color: inkCss(str('color', 'auto')) }">
        <span class="sb-stat-rule mb-5" aria-hidden="true" />
        <p
            class="sb-stat-value"
            :data-countup="!bridge && /\d/.test(str('value')) ? '' : undefined"
        >
            {{ str('value') || (bridge ? '—' : '') }}
        </p>
        <p
            v-if="str('label') || bridge"
            class="mt-3 max-w-[22ch] text-sm leading-snug text-[color:var(--sb-ink-muted)]"
        >
            {{ str('label') || 'Escribe la etiqueta en el panel derecho' }}
        </p>
    </div>
</template>
