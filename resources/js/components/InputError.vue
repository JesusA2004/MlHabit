<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';

/** Mensaje de error bajo un campo. Enlázalo con `aria-describedby`. */
defineProps<{ id?: string; message?: string }>();
</script>

<template>
    <p
        v-if="message"
        :id="id"
        class="flex items-start gap-1.5 text-sm text-danger-ink"
        role="alert"
    >
        <CircleAlert class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
        <span>{{ message }}</span>
    </p>
</template>
