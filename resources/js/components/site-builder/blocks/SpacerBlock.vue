<script setup lang="ts">
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Espacio en blanco (en el editor se ve rayado para poder tomarlo). */
const props = defineProps<{ block: Block }>();

const { bridge } = useBlock(() => props.block);
</script>

<template>
    <div
        class="flex-1"
        :class="
            bridge &&
            'rounded-md bg-[repeating-linear-gradient(135deg,transparent_0_6px,rgb(176_125_79/0.14)_6px_7px)] outline-1 outline-oak/30 outline-dashed'
        "
        aria-hidden="true"
    />
</template>
