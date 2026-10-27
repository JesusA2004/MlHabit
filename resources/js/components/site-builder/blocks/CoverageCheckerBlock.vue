<script setup lang="ts">
import CoverageChecker from '@/components/web/public/CoverageChecker.vue';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Verificador de código postal (CoverageService es la única verdad). */
const props = defineProps<{ block: Block }>();

const { str, dark, bridge } = useBlock(() => props.block);
</script>

<template>
    <div :class="bridge && 'pointer-events-none'">
        <CoverageChecker
            :cta-label="
                str('cta_label') || 'Revisa si llegamos a tu código postal'
            "
            :fallback="str('fallback')"
            :tone="dark ? 'dark' : 'light'"
        />
    </div>
</template>
