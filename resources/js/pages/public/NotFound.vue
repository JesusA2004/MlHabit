<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import type { CategorySummary } from '@/types';

withDefaults(defineProps<{ categories?: CategorySummary[] }>(), { categories: () => [] });
</script>

<template>
    <Head title="Página no encontrada">
        <meta head-key="robots" name="robots" content="noindex" />
    </Head>

    <section class="tone-ink relative flex min-h-[78vh] items-center overflow-hidden py-20">
        <span
            class="pointer-events-none absolute -right-8 bottom-0 font-display leading-none text-paper/[0.035] select-none"
            style="font-size: clamp(12rem, 38vw, 34rem)"
            aria-hidden="true"
            >404</span
        >
        <div class="container-editorial relative">
            <p class="sn-eyebrow type-eyebrow">Error 404</p>
            <h1 class="type-display-xl mt-5 max-w-3xl text-paper">ESTO NO ESTÁ AQUÍ.</h1>
            <p class="type-lead mt-6 max-w-xl text-mist">
                La página que buscas no existe o cambió de lugar. Sin tapujos: volvamos a lo que sí vale la pena.
            </p>
            <div class="mt-10 flex flex-wrap gap-3">
                <Link href="/" class="btn-editorial btn-editorial--primary">Volver al inicio</Link>
                <Link href="/tips" class="btn-editorial btn-editorial--secondary">Explorar los tips</Link>
            </div>
            <ul v-if="categories.length" class="mt-14 flex flex-wrap gap-x-8 gap-y-3 border-t border-line pt-8">
                <li v-for="c in categories" :key="c.slug">
                    <Link :href="`/tips?categoria=${c.slug}`" class="group inline-flex min-h-11 items-center gap-2 text-paper/80 hover:text-gold">
                        <span class="size-1.5 rounded-full" :style="{ background: c.color }" aria-hidden="true" />
                        {{ c.name }}
                        <ArrowRight class="size-4 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                    </Link>
                </li>
            </ul>
        </div>
    </section>
</template>
