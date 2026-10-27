<script setup lang="ts">
import { computed } from 'vue';
import SocialIcon from '@/components/public/SocialIcon.vue';
import type { StudioNode } from '@/studio/types';
import { useRenderCtx } from '../context';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const links = computed(() => (ctx.resolved.social.length ? ctx.resolved.social : ctx.social));
</script>

<template>
    <ul v-if="links.length" class="sn-social-links" :class="`sn-social-links--${node.props.variant ?? 'icons'}`">
        <li v-for="link in links" :key="link.platform">
            <a
                :href="link.url"
                :target="link.open_new_tab ? '_blank' : undefined"
                rel="noopener noreferrer"
                :aria-label="node.props.variant === 'labels' ? undefined : link.platform"
            >
                <SocialIcon :platform="link.platform" />
                <span v-if="node.props.variant === 'labels'" class="capitalize">{{ link.platform }}</span>
            </a>
        </li>
    </ul>
    <p v-else-if="ctx.editing" class="sn-placeholder">No hay redes visibles. Configúralas en Sitio web → Redes sociales.</p>
</template>
