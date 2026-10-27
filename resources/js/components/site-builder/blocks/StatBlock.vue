<script setup lang="ts">
import { inkCss } from '../tokens';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Cifra con etiqueta. El valor lo escribe el equipo (texto libre): nunca se
 * siembran cifras inventadas.
 */
const props = defineProps<{ block: Block }>();

const { str, bridge } = useBlock(() => props.block);
</script>

<template>
    <div :style="{ color: inkCss(str('color', 'auto')) }">
        <p class="sb-stat-value">
            {{ str('value') || (bridge ? '—' : '') }}
        </p>
        <p
            v-if="str('label') || bridge"
            class="mt-2 text-sm text-[color:var(--sb-ink-muted)]"
        >
            {{ str('label') || 'Escribe la etiqueta en el panel derecho' }}
        </p>
    </div>
</template>
