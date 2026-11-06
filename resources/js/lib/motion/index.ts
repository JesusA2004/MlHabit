/**
 * Sistema de movimiento de ML Habitat (sin dependencias).
 *
 * Se inicia una vez desde `PublicLayout` sobre el contenedor `.motion-scope`
 * y descubre por sí solo el contenido nuevo (navegación de Inertia, bloques
 * del editor, listas que cambian) con un MutationObserver.
 *
 * API declarativa (atributos en el HTML):
 *   data-motion="fade-up|fade-in|zoom-in|slide-left|slide-right|blur-in|
 *                mask-reveal|draw|split|settle|none|
 *                wipe-up|wipe-down|wipe-left|wipe-right|rise|pop|
 *                blur-up|tilt-in|zoom-out"
 *   data-motion-delay="120"          retraso en ms
 *   data-motion-stagger="80"         escalona a los hijos directos
 *   data-parallax="0.15"             desplazamiento ligado al scroll
 *   data-countup                     cuenta hasta la cifra que ya muestra
 *   data-magnetic="0.3"              botón magnético
 *   data-tilt="6"                    inclinación 3D con brillo
 *   data-spotlight                   foco de luz que sigue al cursor
 *   data-hover-zoom                  lupa sobre la imagen
 *   data-spotlight-layer             luz que sigue al cursor en su sección
 *   .glow-border / .img-reveal       borde animado / zoom de imagen al revelarse
 *   data-journey="<paso>"            ancla del recorrido guiado
 *
 * Documentación: docs/design/MOTION.md
 */
import {
    canAnimate,
    hasFinePointer,
    isBrowser,
    markMotionReady,
    onReducedMotionChange,
} from './env';
import { createNavigationEngine } from './navigation';
import { createPointerEngine } from './pointer';
import { createRevealEngine } from './reveal';
import type { RevealEngine } from './reveal';
import { createScrollEngine } from './scroll';
import type { ScrollEngine } from './scroll';

export { reveal as vReveal } from './directive';
export { findAnchorTarget, scrollToElement } from './navigation';
export { prefersReducedMotion, hasFinePointer, canAnimate } from './env';
export type { MotionPreset } from './reveal';
export { MOTION_PRESETS } from './reveal';
export { createParticleField } from './particles';
export type {
    ParticleField,
    ParticleOptions,
    ParticleTone,
    ParticleVariant,
} from './particles';

// Lo antes posible: evita un parpadeo de contenido antes de ocultarlo.
markMotionReady();

export type MotionController = {
    /** Fuerza un nuevo escaneo (normalmente no hace falta). */
    refresh: () => void;
    destroy: () => void;
};

/**
 * Barra de progreso de lectura (decorativa) bajo el borde superior. Lee
 * `--scroll-progress` (scroll.ts); el aspecto vive en motion.css. Se oculta
 * donde la barra segmentada del recorrido ya ocupa ese lugar.
 */
function mountProgressBar(): HTMLElement {
    const existing = document.querySelector<HTMLElement>(
        '.scroll-progress-bar',
    );

    if (existing) {
        return existing;
    }

    const bar = document.createElement('div');
    bar.className = 'scroll-progress-bar';
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);

    return bar;
}

export function createMotion(scope: HTMLElement): MotionController {
    if (!isBrowser) {
        return { refresh: () => {}, destroy: () => {} };
    }

    scope.classList.add('motion-scope');

    let reveal: RevealEngine | null = null;
    let scroll: ScrollEngine | null = null;
    let pointer: { destroy: () => void } | null = null;
    let navigation: { destroy: () => void } | null = null;
    let progressBar: HTMLElement | null = null;
    let frame = 0;

    function start(): void {
        markMotionReady();

        const animate = canAnimate();
        reveal = animate ? createRevealEngine() : null;
        scroll = createScrollEngine({ parallax: animate });
        pointer = animate && hasFinePointer() ? createPointerEngine() : null;
        navigation = createNavigationEngine({ animate });
        progressBar = animate ? mountProgressBar() : null;
        scan();
    }

    function stop(): void {
        reveal?.revealAll(scope);
        reveal?.destroy();
        scroll?.destroy();
        pointer?.destroy();
        navigation?.destroy();
        progressBar?.remove();
        progressBar = null;
        reveal = scroll = pointer = navigation = null;
    }

    function scan(): void {
        frame = 0;
        reveal?.scan(scope);
        scroll?.scan(scope);
        scroll?.update();
    }

    function schedule(): void {
        if (!frame) {
            frame = requestAnimationFrame(scan);
        }
    }

    const mutations = new MutationObserver((records) => {
        for (const record of records) {
            if (record.addedNodes.length > 0 || record.type === 'attributes') {
                schedule();

                return;
            }
        }
    });

    mutations.observe(scope, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-motion', 'data-parallax', 'data-countup'],
    });

    const offReduced = onReducedMotionChange(() => {
        stop();
        start();
    });

    start();

    return {
        refresh: schedule,
        destroy() {
            mutations.disconnect();
            offReduced();
            stop();

            if (frame) {
                cancelAnimationFrame(frame);
            }
        },
    };
}
