<script setup lang="ts">
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import SocialIcon from '@/components/public/SocialIcon.vue';
import type { StudioNode } from '@/studio/types';
import { useRenderCtx } from '../context';
import SectionHeader from '../shared/SectionHeader.vue';

const { node } = defineProps<{ node: StudioNode }>();
const ctx = useRenderCtx();

const links = computed(() => (ctx.resolved.social.length ? ctx.resolved.social : ctx.social));
const layout = computed(() => String(node.props.layout ?? 'cards'));
</script>

<template>
    <div class="sn-container" :class="`w-${node.props.width ?? 'normal'}`">
        <SectionHeader :eyebrow="String(node.props.eyebrow ?? '')" :title="String(node.props.title ?? '')" />

        <p v-if="!links.length" class="sn-empty">Muy pronto encontrarás aquí todas mis redes.</p>

        <ul v-else class="sn-social" :class="`sn-social--${layout}`">
            <li v-for="link in links" :key="link.platform" data-stagger-item>
                <a
                    :href="link.url"
                    :target="link.open_new_tab ? '_blank' : undefined"
                    rel="noopener noreferrer"
                    class="sn-social-item group"
                >
                    <span class="sn-social-icon"><SocialIcon :platform="link.platform" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="sn-social-name">{{ link.platform }}</span>
                        <span v-if="link.username && layout !== 'pills'" class="sn-social-user">{{ link.username }}</span>
                    </span>
                    <ArrowUpRight v-if="layout !== 'pills'" class="size-5 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" aria-hidden="true" />
                </a>
            </li>
        </ul>
    </div>
</template>
