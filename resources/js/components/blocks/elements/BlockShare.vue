<script setup lang="ts">
import { Check, Link2, Share2 } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import type { StudioNode } from '@/studio/types';

const { node } = defineProps<{ node: StudioNode }>();

const label = computed(() => String(node.props.label ?? 'Compartir'));

const copied = ref(false);
const canShare = ref(false);

async function share() {
    if (typeof window === 'undefined') return;
    const url = window.location.href;

    if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
        try {
            await navigator.share({ title: document.title, url });
        } catch {
            /* cancelado por el usuario */
        }

        return;
    }

    await navigator.clipboard.writeText(url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

onMounted(() => {
    canShare.value = typeof navigator !== 'undefined' && typeof navigator.share === 'function';
});
</script>

<template>
    <button type="button" class="btn-editorial btn-editorial--secondary sn-share" @click="share">
        <Check v-if="copied" class="size-4" aria-hidden="true" />
        <Share2 v-else-if="canShare" class="size-4" aria-hidden="true" />
        <Link2 v-else class="size-4" aria-hidden="true" />
        {{ copied ? 'Enlace copiado' : canShare ? label : 'Copiar enlace' }}
    </button>
</template>
