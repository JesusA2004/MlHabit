<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    samePageHash,
    scrollToSection,
    whenScrollable,
} from '@/lib/navigation';

/**
 * Enlace del menú del sitio: navegación de Inertia para rutas internas y
 * `<a>` normal para enlaces externos (https://, mailto:, tel:) o los que
 * abren otra pestaña.
 *
 * Los enlaces a una sección de la página actual (`/#como-funciona` estando
 * en el inicio) no recargan la página: desplazan suavemente hasta la
 * sección dejando libre la cabecera fija y actualizan el `#` de la URL.
 * En el sitio público ese salto lo hace primero el motor de movimiento
 * (lib/motion/navigation.ts, con la misma función); aquí queda de respaldo
 * y siempre se emite `click` (p. ej. para cerrar el menú móvil). Desde otra
 * página, Inertia navega y la cabecera ajusta el desplazamiento al llegar.
 */
const props = defineProps<{
    href: string;
    external?: boolean;
    newTab?: boolean;
}>();

// El clic se escucha en fase de captura: el Link de Inertia reemplaza los
// `onClick` heredados por el suyo, y así se puede evitar su visita.
const emit = defineEmits<{ click: [event: MouseEvent] }>();

const page = usePage();

const native = computed(
    () => props.external || props.newTab || !props.href.startsWith('/'),
);

function onClick(event: MouseEvent) {
    emit('click', event);

    if (
        props.newTab ||
        props.external ||
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    const id = samePageHash(props.href, page.url);

    if (!id || !document.getElementById(id)) return;

    event.preventDefault();
    const url = `${window.location.pathname}${window.location.search}#${encodeURIComponent(id)}`;

    if (window.location.hash !== `#${id}`) {
        // Conserva el estado de Inertia en la entrada del historial.
        window.history.pushState(window.history.state, '', url);
    }

    whenScrollable(() => scrollToSection(id));
}
</script>

<template>
    <a
        v-if="native"
        :href="props.href"
        :target="props.newTab ? '_blank' : undefined"
        :rel="
            props.newTab || props.external ? 'noopener noreferrer' : undefined
        "
        @click="onClick"
    >
        <slot />
        <span v-if="props.newTab" class="sr-only">
            (se abre en otra pestaña)</span
        >
    </a>
    <Link v-else :href="props.href" @click.capture="onClick"><slot /></Link>
</template>
