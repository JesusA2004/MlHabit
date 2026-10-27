<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import AppToaster from '@/components/ds/AppToaster.vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';

/**
 * Acceso a pantalla completa.
 *
 * - ≥1024 px: dos columnas a toda la altura del viewport. Izquierda (~44 %)
 *   el formulario centrado (máx. 420 px); derecha, una composición de marca
 *   en espresso con el muestrario de acabados (o una fotografía si se pasa
 *   `image`). La columna visual queda fija mientras el formulario crece.
 * - Móvil/tableta: una columna; marca arriba, formulario y pie al fondo sin
 *   huecos artificiales. Funciona en horizontal (sin alturas fijas).
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
        class="min-h-dvh bg-background text-foreground lg:grid lg:grid-cols-[minmax(0,44fr)_minmax(0,56fr)]"
    >
        <!-- Canto de muestras (solo móvil/tableta, donde no hay columna visual) -->
        <div
            class="fixed inset-x-0 top-0 flex h-1 lg:hidden"
            aria-hidden="true"
        >
            <span class="flex-1 bg-input" />
            <span class="flex-1 bg-oak" />
            <span class="flex-1 bg-primary" />
            <span class="flex-1 bg-espresso" />
        </div>

        <!-- Formulario -->
        <main
            class="flex min-h-dvh flex-col px-5 pt-6 pb-6 sm:px-10 sm:pt-9 lg:px-12 lg:pt-8 xl:px-16"
        >
            <Link
                href="/"
                class="w-fit rounded-md"
                aria-label="ML Habitat, ir al sitio"
            >
                <AppLogo />
            </Link>

            <div
                class="flex flex-1 flex-col justify-center py-10 sm:py-14 lg:py-12"
            >
                <div class="mx-auto w-full max-w-[420px]">
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

            <footer
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                <span>© {{ year }} ML Habitat</span>
                <Link
                    href="/aviso-de-privacidad"
                    class="rounded-sm underline-offset-4 hover:text-foreground hover:underline"
                >
                    Aviso de privacidad
                </Link>
            </footer>
        </main>

        <!-- Composición de marca (solo escritorio) -->
        <aside
            class="auth-visual sticky top-0 hidden h-dvh overflow-hidden bg-espresso text-espresso-foreground lg:block"
            aria-hidden="true"
        >
            <template v-if="props.image">
                <img
                    :src="props.image"
                    :alt="props.imageAlt ?? ''"
                    class="absolute inset-0 size-full object-cover"
                />
                <div
                    class="absolute inset-0 bg-linear-to-t from-espresso/85 via-espresso/25 to-transparent"
                />
            </template>
            <template v-else>
                <!-- Luz cálida de taller y veta tenue -->
                <div class="auth-visual__light absolute inset-0" />
                <div class="auth-visual__grain absolute inset-0" />
                <div
                    class="auth-visual__fan absolute right-[-6%] bottom-[-18%] w-[min(820px,74%,66vh)]"
                >
                    <FinishSwatches tone="dark" />
                </div>
            </template>

            <div class="relative flex h-full flex-col p-12 xl:p-16">
                <p class="text-sm text-espresso-foreground/60">
                    Cocinas, clósets y muebles a la medida
                </p>
                <div class="mt-[min(9vh,6rem)] max-w-[28rem]">
                    <p class="text-display-lg text-white">
                        Tu proyecto, de la medida a la instalación.
                    </p>
                    <p
                        class="mt-5 max-w-sm text-lead text-espresso-foreground/75"
                    >
                        Cotizaciones, citas, avances de fabricación y pagos en
                        un mismo lugar.
                    </p>
                </div>
            </div>
        </aside>

        <AppToaster />
    </div>
</template>

<style scoped>
.auth-visual__light {
    background:
        radial-gradient(
            120% 80% at 85% 100%,
            rgb(176 125 79 / 0.28),
            transparent 60%
        ),
        radial-gradient(90% 70% at 0% 0%, rgb(47 90 76 / 0.35), transparent 65%);
}

.auth-visual__grain {
    opacity: 0.045;
    background-image: repeating-linear-gradient(
        172deg,
        rgb(255 255 255 / 0.5) 0 1px,
        transparent 1px 14px
    );
    mask-image: linear-gradient(to bottom, black, transparent 75%);
}

.auth-visual__fan {
    transform-origin: 50% 80%;
    animation: fan-open 1100ms var(--ease-out) both;
}

@keyframes fan-open {
    from {
        opacity: 0;
        transform: translateY(24px) rotate(-6deg) scale(0.96);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
</style>
