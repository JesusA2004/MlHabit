<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';

/** Banda de cierre para páginas interiores: invita a solicitar asesoría. */
const props = withDefaults(
    defineProps<{
        title?: string;
        body?: string;
        href?: string;
        label?: string;
    }>(),
    {
        title: '¿Te imaginas algo así en tu casa?',
        body: 'Cuéntanos qué necesitas. Un asesor te orienta y te explica los siguientes pasos.',
        href: '/solicitar',
        label: 'Solicita una asesoría',
    },
);
</script>

<template>
    <section class="pb-16 sm:pb-24" aria-labelledby="cta-band-title">
        <div class="container-site">
            <div
                class="flex flex-col gap-8 rounded-[2rem] bg-espresso px-6 py-12 text-espresso-foreground sm:px-12 md:flex-row md:items-center md:justify-between"
            >
                <div class="max-w-xl">
                    <h2 id="cta-band-title" class="text-display-md">
                        {{ props.title }}
                    </h2>
                    <p class="mt-3 text-espresso-foreground/75">
                        {{ props.body }}
                    </p>
                </div>
                <Button
                    as-child
                    size="lg"
                    class="h-12 shrink-0 rounded-full bg-oak px-7 text-base text-white hover:bg-oak/90"
                >
                    <Link :href="props.href">
                        {{ props.label }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
        </div>
    </section>
</template>
