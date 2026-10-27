<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

defineProps<{
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
}>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="rounded-sm font-medium text-primary underline decoration-primary/30 underline-offset-4 transition-colors duration-150 hover:decoration-primary"
    >
        <slot />
    </Link>
</template>
