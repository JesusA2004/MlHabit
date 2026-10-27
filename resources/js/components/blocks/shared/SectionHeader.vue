<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

defineProps<{
    eyebrow?: string;
    title?: string;
    linkLabel?: string;
    linkHref?: string;
    align?: 'start' | 'center';
}>();
</script>

<template>
    <header
        v-if="eyebrow || title || linkLabel"
        class="sn-section-header"
        :class="{ 'sn-section-header--center': align === 'center' }"
    >
        <div class="min-w-0">
            <p v-if="eyebrow" class="sn-eyebrow type-eyebrow">{{ eyebrow }}</p>
            <h2 v-if="title" class="sn-heading type-display-md">
                <span v-for="(line, i) in title.split('\n')" :key="i" class="block">{{ line }}</span>
            </h2>
        </div>
        <Link
            v-if="linkLabel && linkHref"
            :href="linkHref"
            class="sn-section-link group"
        >
            <span class="link-underline">{{ linkLabel }}</span>
            <ArrowRight class="size-4 transition-transform group-hover:translate-x-1" aria-hidden="true" />
        </Link>
    </header>
</template>
