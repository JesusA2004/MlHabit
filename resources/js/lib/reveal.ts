import type { Directive } from 'vue';
import type { MotionConfig } from '@/studio/types';

/**
 * v-reveal: animaciones de entrada declarativas (presets de Studio).
 *
 * - Un único IntersectionObserver compartido para toda la página.
 * - Solo anima opacity / transform / clip-path (sin layout shift).
 * - prefers-reduced-motion → sin animación.
 * - En móvil la distancia y duración se reducen o se desactivan según
 *   `motion.mobile`.
 * - El estado oculto solo aplica con `html.js-motion`, que se activa al
 *   registrar el primer elemento: sin JS, el contenido siempre es visible.
 */

type RevealEl = HTMLElement & { __reveal?: { config: MotionConfig; targets: HTMLElement[] } };

let observer: IntersectionObserver | null = null;

function prefersReduced(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function isMobile(): boolean {
    return window.matchMedia('(max-width: 767px)').matches;
}

function ensureObserver(): IntersectionObserver | null {
    if (observer || typeof IntersectionObserver === 'undefined') return observer;
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                const el = entry.target as HTMLElement;
                const once = el.dataset.revealOnce !== 'false';
                if (entry.isIntersecting) {
                    el.setAttribute('data-revealed', '');
                    if (once) observer?.unobserve(el);
                } else if (!once) {
                    el.removeAttribute('data-revealed');
                }
            }
        },
        { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
    );

    return observer;
}

function fromTransform(preset: string, distance: number): { transform: string; clip: string } {
    switch (preset) {
        case 'slide-up':
            return { transform: `translate3d(0, ${distance}px, 0)`, clip: 'none' };
        case 'slide-left':
            return { transform: `translate3d(${distance}px, 0, 0)`, clip: 'none' };
        case 'slide-right':
            return { transform: `translate3d(${-distance}px, 0, 0)`, clip: 'none' };
        case 'scale':
            return { transform: `scale(${1 - Math.min(distance, 60) / 400})`, clip: 'none' };
        case 'reveal':
            return { transform: 'none', clip: 'inset(0 0 100% 0)' };
        case 'stagger':
            return { transform: `translate3d(0, ${distance}px, 0)`, clip: 'none' };
        default:
            return { transform: 'none', clip: 'none' };
    }
}

function teardown(el: RevealEl): void {
    for (const target of el.__reveal?.targets ?? []) {
        observer?.unobserve(target);
        target.removeAttribute('data-reveal');
        target.removeAttribute('data-reveal-clip');
        target.removeAttribute('data-revealed');
        target.style.removeProperty('--reveal-from');
        target.style.removeProperty('--reveal-clip');
        target.style.removeProperty('--reveal-duration');
        target.style.removeProperty('--reveal-delay');
    }
    delete el.__reveal;
}

function setup(el: RevealEl, config: MotionConfig | null | undefined): void {
    teardown(el);
    if (!config || config.preset === 'none' || prefersReduced()) return;

    let { duration, distance } = config;
    if (isMobile()) {
        if (config.mobile === 'none') return;
        if (config.mobile === 'reduce') {
            distance = Math.round(distance / 2);
            duration = Math.round(duration * 0.8);
        }
    }

    document.documentElement.classList.add('js-motion');

    let targets: HTMLElement[] = [el];
    if (config.preset === 'stagger') {
        const items = Array.from(el.querySelectorAll<HTMLElement>('[data-stagger-item]'));
        targets = items.length ? items : (Array.from(el.children) as HTMLElement[]);
    }

    const { transform, clip } = fromTransform(config.preset, distance);
    targets.forEach((target, i) => {
        target.setAttribute('data-reveal', '');
        if (clip !== 'none') target.setAttribute('data-reveal-clip', '');
        else target.removeAttribute('data-reveal-clip');
        target.dataset.revealOnce = String(config.once);
        target.style.setProperty('--reveal-from', transform);
        target.style.setProperty('--reveal-clip', clip);
        target.style.setProperty('--reveal-duration', `${duration}ms`);
        target.style.setProperty('--reveal-delay', `${config.delay + (config.preset === 'stagger' ? i * 90 : 0)}ms`);
    });

    el.__reveal = { config, targets };

    const io = ensureObserver();
    if (config.trigger === 'load' || !io) {
        requestAnimationFrame(() => requestAnimationFrame(() => targets.forEach((t) => t.setAttribute('data-revealed', ''))));
    } else {
        targets.forEach((t) => io.observe(t));
    }
}

export const vReveal: Directive<HTMLElement, MotionConfig | null | undefined> = {
    mounted(el, binding) {
        setup(el as RevealEl, binding.value);
    },
    updated(el, binding) {
        if (JSON.stringify(binding.value) !== JSON.stringify(binding.oldValue)) {
            setup(el as RevealEl, binding.value);
        }
    },
    beforeUnmount(el) {
        teardown(el as RevealEl);
    },
};

/** Vuelve a reproducir las animaciones dentro de `root` (vista previa en Studio). */
export function replayReveals(root: ParentNode = document): void {
    const items = Array.from(root.querySelectorAll<HTMLElement>('[data-reveal]'));
    items.forEach((el) => el.removeAttribute('data-revealed'));
    // Forzar reflow para reiniciar transiciones.
    void document.body.offsetHeight;
    requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            for (const el of items) {
                const rect = el.getBoundingClientRect();
                if (rect.top < window.innerHeight && rect.bottom > 0) {
                    el.setAttribute('data-revealed', '');
                } else {
                    observer?.observe(el);
                }
            }
        }),
    );
}
