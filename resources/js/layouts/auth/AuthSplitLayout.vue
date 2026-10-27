<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import AppToaster from '@/components/ds/AppToaster.vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';

/**
 * Acceso en dos columnas: a la izquierda (≥1024 px) el muestrario de
 * acabados o una fotografía (`image`); a la derecha el formulario. En móvil
 * queda una sola columna con la marca arriba.
 */
const props = defineProps<{
    title?: string;
    description?: string;
    image?: string | null;
    imageAlt?: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <div
        class="grid min-h-screen grid-cols-1 bg-background text-foreground lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)]"
    >
        <aside
            class="relative hidden overflow-hidden bg-stone lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-14"
            aria-label="ML Habitat"
        >
            <template v-if="props.image">
                <img
                    :src="props.image"
                    :alt="props.imageAlt ?? ''"
                    class="absolute inset-0 size-full object-cover"
                />
                <div
                    class="absolute inset-0 bg-linear-to-t from-espresso/80 via-espresso/20 to-transparent"
                    aria-hidden="true"
                />
            </template>
            <div
                v-else
                class="pointer-events-none absolute -right-[7%] -bottom-[6%] w-[min(500px,62%)]"
                aria-hidden="true"
            >
                <FinishSwatches />
            </div>

            <Link
                href="/"
                class="relative w-fit rounded-md"
                aria-label="ML Habitat, ir al sitio"
            >
                <AppLogo size="lg" :inverted="!!props.image" />
            </Link>

            <div
                class="relative max-w-md"
                :class="props.image ? 'mt-auto' : 'mt-[min(14vh,8rem)] mb-auto'"
            >
                <p
                    class="text-display-lg"
                    :class="props.image ? 'text-white' : 'text-foreground'"
                >
                    Tu proyecto, de la medida a la instalación.
                </p>
                <p
                    class="mt-5 max-w-sm text-lead"
                    :class="
                        props.image ? 'text-white/80' : 'text-muted-foreground'
                    "
                >
                    Cotizaciones, citas, avances de fabricación y pagos en un
                    mismo lugar.
                </p>
            </div>
            <p
                class="relative mt-12 text-sm"
                :class="props.image ? 'text-white/70' : 'text-muted-foreground'"
            >
                © {{ year }} ML Habitat
            </p>
        </aside>

        <main class="flex flex-col px-5 pt-6 pb-8 sm:px-10 lg:px-14 xl:px-20">
            <Link
                href="/"
                class="w-fit rounded-md lg:hidden"
                aria-label="ML Habitat, ir al sitio"
            >
                <AppLogo />
            </Link>

            <div class="flex flex-1 items-center justify-center py-12 lg:py-0">
                <div class="w-full max-w-[380px]">
                    <header
                        v-if="props.title || props.description"
                        class="mb-8"
                    >
                        <h1
                            v-if="props.title"
                            class="text-display-md text-foreground"
                        >
                            {{ props.title }}
                        </h1>
                        <p
                            v-if="props.description"
                            class="mt-2.5 text-[0.9375rem] leading-relaxed text-muted-foreground"
                        >
                            {{ props.description }}
                        </p>
                    </header>
                    <slot />
                </div>
            </div>

            <p class="text-center text-xs text-muted-foreground lg:hidden">
                © {{ year }} ML Habitat
            </p>
        </main>
        <AppToaster />
    </div>
</template>
