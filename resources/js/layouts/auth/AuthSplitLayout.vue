<script setup lang="ts">
/**
 * Layout editorial de acceso: panel de marca oscuro (cinematográfico) a la
 * izquierda y formulario limpio a la derecha. En < 1024 px se reduce a una
 * sola columna con la marca compacta arriba del formulario.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <div class="grid min-h-svh bg-background font-sans text-foreground antialiased lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        <!-- Panel de marca (solo ≥ 1024 px) -->
        <aside class="grain relative hidden overflow-hidden bg-ink text-paper lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16" aria-label="MR. INSIGHT">
            <!-- Isotipo monumental como textura -->
            <svg
                viewBox="0 0 64 64"
                class="pointer-events-none absolute -right-40 -bottom-48 size-[42rem] opacity-[0.07]"
                aria-hidden="true"
            >
                <circle cx="32" cy="29" r="14" fill="none" stroke="#C7AA7D" stroke-width="0.6" />
                <circle cx="32" cy="29" r="4" fill="none" stroke="#C7AA7D" stroke-width="0.6" />
                <line x1="17" y1="49" x2="47" y2="49" stroke="#C7AA7D" stroke-width="0.6" stroke-linecap="round" />
            </svg>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-48 bg-linear-to-b from-gold/10 to-transparent" aria-hidden="true" />

            <Link :href="home()" class="relative flex w-fit items-center gap-3 rounded-md">
                <svg viewBox="0 0 64 64" class="size-10" aria-hidden="true">
                    <rect width="64" height="64" rx="14" fill="#16181B" />
                    <circle cx="32" cy="29" r="14" fill="none" stroke="#C7AA7D" stroke-width="3.5" />
                    <circle cx="32" cy="29" r="4.5" fill="#C7AA7D" />
                    <line x1="17" y1="50" x2="47" y2="50" stroke="#C7AA7D" stroke-width="3.5" stroke-linecap="round" />
                </svg>
                <span class="flex flex-col leading-tight">
                    <span class="font-display text-base font-semibold tracking-tight">MR. INSIGHT</span>
                    <span class="type-caption text-mist">Sala de redacción</span>
                </span>
            </Link>

            <div class="relative max-w-xl">
                <p class="type-eyebrow text-gold">Revista digital · Panel administrador</p>
                <p class="mt-6 type-display-xl text-paper">MR.<br />INSIGHT</p>
                <div class="mt-8 h-px w-16 bg-gold" aria-hidden="true" />
                <p class="mt-8 type-title text-paper">
                    Negocios. Hábitos. Vida.
                    <span class="text-gold italic">— Sin tapujos.</span>
                </p>
            </div>

            <div class="relative flex items-end justify-between gap-6 type-caption text-mist">
                <span>© {{ year }} MR. INSIGHT</span>
                <Link :href="home()" class="inline-flex min-h-11 items-center gap-1.5 rounded-md text-paper transition-colors hover:text-gold">
                    Ver sitio público
                    <ArrowUpRight class="size-4" />
                </Link>
            </div>
        </aside>

        <!-- Formulario -->
        <main class="flex flex-col px-5 py-8 sm:px-10 lg:px-12 xl:px-16">
            <!-- Marca compacta (móvil / tablet) -->
            <Link :href="home()" class="flex w-fit items-center gap-3 rounded-md lg:hidden">
                <svg viewBox="0 0 64 64" class="size-9" aria-hidden="true">
                    <rect width="64" height="64" rx="14" fill="#0D0E10" />
                    <circle cx="32" cy="29" r="14" fill="none" stroke="#C7AA7D" stroke-width="3.5" />
                    <circle cx="32" cy="29" r="4.5" fill="#C7AA7D" />
                    <line x1="17" y1="50" x2="47" y2="50" stroke="#C7AA7D" stroke-width="3.5" stroke-linecap="round" />
                </svg>
                <span class="flex flex-col leading-tight">
                    <span class="font-display text-base font-semibold tracking-tight">MR. INSIGHT</span>
                    <span class="text-xs text-muted-foreground">Negocios. Hábitos. Vida. — Sin tapujos.</span>
                </span>
            </Link>

            <div class="flex flex-1 items-center justify-center py-10 lg:py-0">
                <div class="w-full max-w-sm">
                    <header v-if="title || description" class="mb-8">
                        <h1 v-if="title" class="font-display text-3xl leading-tight font-semibold tracking-tight">{{ title }}</h1>
                        <p v-if="description" class="mt-2 text-sm text-muted-foreground">{{ description }}</p>
                    </header>
                    <slot />
                </div>
            </div>

            <p class="text-center text-xs text-muted-foreground lg:hidden">© {{ year }} MR. INSIGHT</p>
        </main>
    </div>
</template>
