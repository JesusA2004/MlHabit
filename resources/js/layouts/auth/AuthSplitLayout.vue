<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AppToaster from '@/components/ds/AppToaster.vue';

/**
 * Acceso a pantalla completa (login, recuperar contraseña, 2FA, invitación…).
 *
 * Lienzo pastel de la marca (lino, salvia, eucalipto claro y roble claro)
 * con manchas de luz que se mueven muy despacio y grano de papel; al
 * centro una tarjeta de vidrio con la insignia oficial grande montada
 * sobre su borde. Una sola columna de 390 a 1440 px, sin alturas fijas
 * (funciona en horizontal). Con `prefers-reduced-motion` todo queda quieto.
 */
const props = defineProps<{
    title?: string;
    description?: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <div class="auth-shell relative isolate min-h-dvh overflow-hidden">
        <!-- Fondo pastel animado (decorativo) -->
        <div class="auth-bg absolute inset-0 -z-10" aria-hidden="true">
            <span class="auth-blob auth-blob--sage" />
            <span class="auth-blob auth-blob--oak" />
            <span class="auth-blob auth-blob--eucalyptus" />
            <span class="auth-blob auth-blob--linen" />
            <span class="auth-grain absolute inset-0" />
        </div>

        <main
            class="flex min-h-dvh flex-col items-center px-4 pt-6 pb-5 sm:px-6 sm:pt-10"
        >
            <div
                class="flex w-full flex-1 flex-col items-center justify-center py-4 sm:py-8"
            >
                <div class="auth-rise w-full max-w-[440px]">
                    <Link
                        href="/"
                        class="auth-logo relative z-10 mx-auto -mb-14 block w-fit rounded-full focus-visible:ring-4 focus-visible:ring-ring/40 focus-visible:outline-none sm:-mb-16"
                        aria-label="ML Hábitat, ir al sitio"
                    >
                        <span
                            class="block rounded-full bg-white/80 p-1.5 shadow-[0_18px_40px_-14px_rgb(35_30_26/0.35),0_2px_6px_rgb(35_30_26/0.08)] ring-1 ring-white backdrop-blur"
                        >
                            <AppLogoIcon
                                class-name="size-24 sm:size-28"
                                sizes="(min-width: 640px) 112px, 96px"
                            />
                        </span>
                    </Link>

                    <section
                        class="auth-card rounded-[28px] border border-white/80 bg-white/70 px-5 pt-[4.75rem] pb-7 shadow-[0_30px_70px_-30px_rgb(35_30_26/0.28),0_2px_8px_rgb(35_30_26/0.05)] backdrop-blur-xl min-[400px]:px-7 sm:px-9 sm:pt-[5.5rem] sm:pb-9"
                    >
                        <header
                            v-if="props.title || props.description"
                            class="mb-7 text-center"
                        >
                            <h1
                                v-if="props.title"
                                class="text-display-md text-foreground"
                            >
                                {{ props.title }}
                            </h1>
                            <p
                                v-if="props.description"
                                class="mx-auto mt-2 max-w-[22rem] text-[0.9375rem] leading-relaxed text-muted-foreground"
                            >
                                {{ props.description }}
                            </p>
                        </header>
                        <slot />
                    </section>
                </div>
            </div>

            <footer
                class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
            >
                <span>© {{ year }} ML Hábitat</span>
                <span aria-hidden="true">·</span>
                <Link
                    href="/aviso-de-privacidad"
                    class="rounded-sm underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none"
                >
                    Aviso de privacidad
                </Link>
            </footer>
        </main>

        <AppToaster />
    </div>
</template>

<style scoped>
.auth-bg {
    background:
        radial-gradient(120% 90% at 0% 0%, #eef4ef 0%, transparent 60%),
        linear-gradient(155deg, #f7f2eb 0%, #f1f4ef 48%, #f5ece2 100%);
}

.auth-blob {
    position: absolute;
    border-radius: 9999px;
    filter: blur(64px);
    opacity: 0.75;
    will-change: transform;
    animation: auth-drift 26s var(--ease-in-out) infinite alternate;
}

.auth-blob--sage {
    top: -18vmax;
    left: -14vmax;
    width: 52vmax;
    height: 52vmax;
    background: #cfe2d6;
}

.auth-blob--oak {
    right: -16vmax;
    bottom: -20vmax;
    width: 56vmax;
    height: 56vmax;
    background: #f1dcc5;
    animation-duration: 32s;
    animation-delay: -8s;
}

.auth-blob--eucalyptus {
    top: 18%;
    right: -8vmax;
    width: 32vmax;
    height: 32vmax;
    background: #dbe9e0;
    opacity: 0.6;
    animation-duration: 28s;
    animation-delay: -14s;
}

.auth-blob--linen {
    bottom: -10vmax;
    left: 8%;
    width: 30vmax;
    height: 30vmax;
    background: #efe2d3;
    opacity: 0.6;
    animation-duration: 30s;
    animation-delay: -4s;
}

/* Grano de papel muy sutil (SVG en línea, sin peticiones). */
.auth-grain {
    opacity: 0.22;
    mix-blend-mode: multiply;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix values='0 0 0 0 .14 0 0 0 0 .12 0 0 0 0 .1 0 0 0 .55 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}

/* Campos sobre vidrio: fondo casi blanco para que se lean bien. */
.auth-card :deep(input[data-slot='input']:not([readonly])) {
    background-color: rgb(255 255 255 / 0.85);
}

.auth-rise {
    animation: auth-rise 640ms var(--ease-out) both;
}

.auth-logo {
    animation: auth-pop 720ms var(--ease-out) 80ms both;
}

@keyframes auth-drift {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    50% {
        transform: translate3d(4vmax, 3vmax, 0) scale(1.06);
    }
    100% {
        transform: translate3d(-3vmax, 5vmax, 0) scale(0.96);
    }
}

@keyframes auth-rise {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes auth-pop {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.92);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .auth-blob,
    .auth-rise,
    .auth-logo {
        animation: none;
    }
}
</style>
