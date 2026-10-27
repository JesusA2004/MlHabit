<script setup lang="ts">
import type { StudioNode } from '@/studio/types';

defineProps<{ node: StudioNode }>();
</script>

<template>
    <figure class="sn-quote" :class="`sn-quote--${node.props.variant ?? 'classic'}`">
        <span class="sn-quote-mark" aria-hidden="true">“</span>
        <blockquote class="sn-t" data-inline="text" data-inline-multiline>
            {{ node.props.text }}
        </blockquote>
        <figcaption v-if="node.props.author" class="type-eyebrow sn-quote-author">
            {{ node.props.author }}
        </figcaption>
    </figure>
</template>
