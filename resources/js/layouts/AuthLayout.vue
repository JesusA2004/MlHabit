<script setup lang="ts">
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';

/** Las páginas de acceso pasan `title`, `description` e `image` vía `defineOptions({ layout })`. */
const {
    title = '',
    description = '',
    image = null,
} = defineProps<{
    title?: string;
    description?: string;
    image?: string | null;
}>();
</script>

<template>
    <AuthSplitLayout :title="title" :description="description" :image="image">
        <slot />
    </AuthSplitLayout>
</template>
