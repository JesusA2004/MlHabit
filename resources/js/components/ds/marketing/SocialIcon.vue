<script setup lang="ts">
import { Globe, MessageCircle } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Iconos de redes sociales con el mismo trazo que Lucide (Lucide ya no
 * incluye logotipos de marcas). Plataforma desconocida → globo.
 */
const props = defineProps<{ platform: string; class?: string }>();

const key = computed(() => props.platform.toLowerCase().replace(/[^a-z]/g, ''));
</script>

<template>
    <MessageCircle
        v-if="key === 'whatsapp'"
        :class="props.class"
        aria-hidden="true"
    />
    <svg
        v-else-if="
            [
                'instagram',
                'facebook',
                'youtube',
                'linkedin',
                'tiktok',
                'x',
                'twitter',
            ].includes(key)
        "
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        :class="props.class"
        aria-hidden="true"
    >
        <template v-if="key === 'instagram'">
            <rect x="2" y="2" width="20" height="20" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <path d="M17.5 6.5h.01" />
        </template>
        <path
            v-else-if="key === 'facebook'"
            d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"
        />
        <template v-else-if="key === 'youtube'">
            <path
                d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"
            />
            <path d="m10 15 5-3-5-3z" />
        </template>
        <template v-else-if="key === 'linkedin'">
            <path
                d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"
            />
            <rect x="2" y="9" width="4" height="12" />
            <circle cx="4" cy="4" r="2" />
        </template>
        <path
            v-else-if="key === 'tiktok'"
            d="M13 3v12.5a3.5 3.5 0 1 1-3.5-3.5M13 3c.5 2.9 2.6 5 5.5 5.2"
        />
        <template v-else>
            <path d="M4 4l11.733 16H20L8.267 4z" />
            <path d="M4 20l6.768-6.768M13.228 10.772 20 4" />
        </template>
    </svg>
    <Globe v-else :class="props.class" aria-hidden="true" />
</template>
