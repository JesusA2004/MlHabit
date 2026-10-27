<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const props = withDefaults(
    defineProps<{ errors: string[]; title?: string }>(),
    {
        title: 'Revisa lo siguiente',
    },
);

const uniqueErrors = computed(() => Array.from(new Set(props.errors)));
</script>

<template>
    <Alert
        class="border-danger/30 bg-danger-soft text-danger-ink [&>svg]:text-danger-ink"
    >
        <CircleAlert class="size-4" />
        <AlertTitle>{{ props.title }}</AlertTitle>
        <AlertDescription class="text-danger-ink">
            <ul class="list-inside list-disc text-sm">
                <li v-for="(error, index) in uniqueErrors" :key="index">
                    {{ error }}
                </li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
