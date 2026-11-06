<script setup lang="ts">
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';

/** Las páginas de acceso pasan `title` y `description` vía `defineOptions({ layout })`. */
const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthSplitLayout :title="title" :description="description">
        <slot />
    </AuthSplitLayout>
</template>
