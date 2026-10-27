<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';

/** Migas de pan del sitio público (el último elemento es la página actual). */
const props = defineProps<{ items: { label: string; href?: string }[] }>();
</script>

<template>
    <nav aria-label="Migas de pan" class="text-sm text-muted-foreground">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li
                v-for="(item, i) in props.items"
                :key="i"
                class="flex items-center gap-1.5"
            >
                <Link
                    v-if="item.href && i < props.items.length - 1"
                    :href="item.href"
                    class="link-underline rounded transition-colors hover:text-foreground"
                    >{{ item.label }}</Link
                >
                <span v-else aria-current="page" class="text-foreground">{{
                    item.label
                }}</span>
                <ChevronRight
                    v-if="i < props.items.length - 1"
                    class="size-3.5"
                    aria-hidden="true"
                />
            </li>
        </ol>
    </nav>
</template>
