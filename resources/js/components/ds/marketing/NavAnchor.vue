<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Enlace del menú del sitio: navegación de Inertia para rutas internas y
 * `<a>` normal para enlaces externos (https://, mailto:, tel:) o los que
 * abren otra pestaña.
 */
const props = defineProps<{
    href: string;
    external?: boolean;
    newTab?: boolean;
}>();

const native = computed(
    () => props.external || props.newTab || !props.href.startsWith('/'),
);
</script>

<template>
    <a
        v-if="native"
        :href="props.href"
        :target="props.newTab ? '_blank' : undefined"
        :rel="
            props.newTab || props.external ? 'noopener noreferrer' : undefined
        "
    >
        <slot />
        <span v-if="props.newTab" class="sr-only">
            (se abre en otra pestaña)</span
        >
    </a>
    <Link v-else :href="props.href"><slot /></Link>
</template>
