<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Enlace que usa Inertia para rutas internas y <a> para externas, anclas,
 * mailto/tel. Los externos abren en otra pestaña con rel seguro.
 */
const props = defineProps<{ href: string }>();

const external = computed(() => /^(https?:|mailto:|tel:)/i.test(props.href));
const isHash = computed(() => props.href.includes('#'));
const newTab = computed(() => /^https?:/i.test(props.href));
</script>

<template>
    <a
        v-if="external || isHash"
        :href="props.href"
        :target="newTab ? '_blank' : undefined"
        :rel="newTab ? 'noopener noreferrer' : undefined"
    >
        <slot />
    </a>
    <Link v-else :href="props.href">
        <slot />
    </Link>
</template>
