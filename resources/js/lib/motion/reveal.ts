/**
 * Revelados al entrar en pantalla (`[data-motion]`), grupos escalonados
 * (`[data-motion-stagger]`) y conteo de cifras existentes (`[data-countup]`).
 *
 * El estado oculto SOLO existe en CSS bajo `html.motion-ready .motion-scope`,
 * así que sin JS, sin IntersectionObserver o con movimiento reducido el
 * contenido se ve completo desde el primer pintado.
 */
import { animateCount } from './countup';

export const MOTION_PRESETS = [
    'fade-up',
    'fade-in',
    'zoom-in',
    'slide-left',
    'slide-right',
    'blur-in',
    'mask-reveal',
    'draw',
    'split',
    'settle',
    'none',
] as const;

export type MotionPreset = (typeof MOTION_PRESETS)[number];

const BOUND = 'data-motion-bound';
const IN = 'data-motion-in';
const DONE = 'data-motion-done';

/** Duración máxima de un revelado (para limpiar el estado de transición). */
const SETTLE_MS = 1400;

function toMs(value: string | null | undefined, fallback = 0): number {
    const parsed = Number.parseInt(value ?? '', 10);

    return Number.isFinite(parsed) && parsed >= 0
        ? Math.min(parsed, 4000)
        : fallback;
}

/**
 * Convierte un contenedor `[data-motion-stagger="80"]` en revelados
 * individuales para sus hijos directos, con retraso incremental.
 */
function expandStagger(group: HTMLElement): void {
    const step = toMs(group.dataset.motionStagger, 80);
    const base = toMs(group.dataset.motionDelay);

    // El contenedor no se oculta: el movimiento lo hacen sus hijos.
    if (group.dataset.motion) {
        group.dataset.motionGroup = group.dataset.motion;
        group.removeAttribute('data-motion');
    }

    group.setAttribute(BOUND, '');

    const preset = group.dataset.motionChildren || group.dataset.motionGroup;
    const childPreset =
        preset && preset !== 'none' ? preset : ('fade-up' as const);
    // Hijos que llegan después (filtros, paginación): escalonado desde cero.
    let fresh = 0;

    Array.from(group.children).forEach((child) => {
        if (
            !(child instanceof HTMLElement) ||
            child.hasAttribute('data-motion-staggered')
        ) {
            return;
        }

        child.setAttribute('data-motion-staggered', '');

        if (!child.dataset.motion) {
            child.dataset.motion = childPreset;
        }

        if (!child.dataset.motionDelay) {
            // Tope para listas largas: nada espera más de ~0.9 s.
            child.dataset.motionDelay = String(
                base + Math.min(fresh * step, 900),
            );
        }

        fresh += 1;
    });
}

function applyDelay(el: HTMLElement): void {
    const delay = toMs(el.dataset.motionDelay);

    if (delay > 0) {
        el.style.setProperty('--motion-delay', `${delay}ms`);
    }
}

function reveal(el: HTMLElement): void {
    if (el.hasAttribute(IN)) {
        return;
    }

    el.setAttribute(IN, '');

    if (el.hasAttribute('data-countup')) {
        animateCount(el);
    }

    el.querySelectorAll<HTMLElement>('[data-countup]').forEach((child) => {
        if (!child.hasAttribute('data-motion')) {
            animateCount(child);
        }
    });

    const delay = toMs(el.dataset.motionDelay);

    window.setTimeout(() => el.setAttribute(DONE, ''), delay + SETTLE_MS);
}

export type RevealEngine = {
    scan: (root: ParentNode) => void;
    revealAll: (root: ParentNode) => void;
    destroy: () => void;
};

export function createRevealEngine(): RevealEngine {
    /**
     * `mask-reveal` empieza con clip-path al 100%: Chromium lo considera sin
     * área visible y el IntersectionObserver nunca dispararía. Para esos
     * elementos se observa a su contenedor (sentinel) y se revela el destino.
     */
    const targets = new WeakMap<Element, HTMLElement[]>();
    const sentinelFor = (el: HTMLElement): HTMLElement =>
        el.dataset.motion === 'mask-reveal' && el.parentElement
            ? el.parentElement
            : el;

    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                observer.unobserve(entry.target);
                const list = targets.get(entry.target) ?? [
                    entry.target as HTMLElement,
                ];
                targets.delete(entry.target);
                list.forEach(reveal);
            }
        },
        // Un poco antes del borde inferior, para que el movimiento ya esté
        // en curso cuando la vista llega al contenido.
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
    );

    /**
     * Red de seguridad: si por cualquier motivo el observador no disparó
     * (elemento sin área, pestaña en segundo plano, salto a un ancla), todo
     * lo que ya quedó por encima del borde inferior de la vista se revela.
     * Nunca hay contenido escondido "para siempre".
     */
    let sweepTimer = 0;

    function sweep(): void {
        sweepTimer = 0;
        const bottom = window.innerHeight;

        document
            .querySelectorAll<HTMLElement>(
                `[${BOUND}][data-motion]:not([${IN}])`,
            )
            .forEach((el) => {
                const rect = el.getBoundingClientRect();

                if (
                    rect.top < bottom ||
                    (rect.width === 0 && rect.height === 0)
                ) {
                    observer.unobserve(sentinelFor(el));
                    reveal(el);
                }
            });
    }

    function scheduleSweep(): void {
        window.clearTimeout(sweepTimer);
        sweepTimer = window.setTimeout(sweep, 2200);
    }

    window.addEventListener('scrollend', scheduleSweep, { passive: true });

    function scan(root: ParentNode): void {
        scheduleSweep();
        root.querySelectorAll<HTMLElement>('[data-motion-stagger]').forEach(
            expandStagger,
        );

        root.querySelectorAll<HTMLElement>(
            `[data-motion]:not([${BOUND}]), [data-countup]:not([${BOUND}])`,
        ).forEach((el) => {
            el.setAttribute(BOUND, '');

            if (el.dataset.motion === 'none') {
                el.setAttribute(IN, '');
                el.setAttribute(DONE, '');

                return;
            }

            applyDelay(el);
            const sentinel = sentinelFor(el);
            targets.set(sentinel, [...(targets.get(sentinel) ?? []), el]);
            observer.observe(sentinel);
        });
    }

    function revealAll(root: ParentNode): void {
        root.querySelectorAll<HTMLElement>('[data-motion]').forEach((el) => {
            observer.unobserve(sentinelFor(el));
            el.setAttribute(IN, '');
            el.setAttribute(DONE, '');
        });
    }

    return {
        scan,
        revealAll,
        destroy: () => {
            observer.disconnect();
            window.clearTimeout(sweepTimer);
            window.removeEventListener('scrollend', scheduleSweep);
        },
    };
}
