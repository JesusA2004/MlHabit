<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-ink p-6 font-sans text-paper antialiased md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-5">
                    <Link :href="home()" class="flex flex-col items-center gap-3">
                        <svg viewBox="0 0 64 64" class="size-12" aria-hidden="true">
                            <rect width="64" height="64" rx="14" fill="#16181B" />
                            <circle cx="32" cy="29" r="14" fill="none" stroke="#C7AA7D" stroke-width="3" />
                            <circle cx="32" cy="29" r="4" fill="#C7AA7D" />
                            <line x1="17" y1="49" x2="47" y2="49" stroke="#C7AA7D" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        <span class="flex flex-col items-center leading-tight">
                            <span class="font-display text-lg text-paper">MR. INSIGHT</span>
                            <span class="text-[10px] tracking-[0.2em] text-mist uppercase">Panel administrador</span>
                        </span>
                    </Link>
                    <div class="space-y-1.5 text-center">
                        <h1 v-if="title" class="font-display text-xl text-paper">{{ title }}</h1>
                        <p v-if="description" class="text-center text-sm text-mist">{{ description }}</p>
                    </div>
                </div>
                <div class="dark rounded-2xl border border-white/10 bg-ink-soft p-6 shadow-2xl shadow-black/40 sm:p-8">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
