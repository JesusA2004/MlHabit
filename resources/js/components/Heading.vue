<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header class="space-y-1">
        <h2
            :class="
                variant === 'small'
                    ? 'text-base font-semibold tracking-tight'
                    : 'font-display text-xl font-semibold tracking-tight'
            "
        >
            {{ title }}
        </h2>
        <p v-if="description" class="max-w-2xl text-sm text-muted-foreground">
            {{ description }}
        </p>
        <slot />
    </header>
</template>
