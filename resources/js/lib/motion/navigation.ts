/**
 * Navegación con movimiento:
 * - Anclas de la misma página con desplazamiento suave y compensación de la
 *   cabecera fija (respeta movimiento reducido y mueve el foco al destino).
 * - Transiciones entre páginas de Inertia: View Transitions API cuando el
 *   navegador la soporta; si no, una entrada CSS breve en `<main>`.
 */
import { config, router } from '@inertiajs/vue3';
import { anchorOffset, prefersReducedMotion } from './env';

/** Busca el destino de un hash: `#id` o, si no existe, `[data-journey="id"]`. */
export function findAnchorTarget(hash: string): HTMLElement | null {
    const id = decodeURIComponent(hash.replace(/^#/, ''));

    if (!id) {
        return null;
    }

    return (
        document.getElementById(id) ??
        document.querySelector<HTMLElement>(
            `[data-journey="${CSS.escape(id)}"]`,
        )
    );
}

/** Desplaza suavemente hasta un elemento y le pasa el foco (accesible). */
export function scrollToElement(target: HTMLElement): void {
    const top =
        target.getBoundingClientRect().top + window.scrollY - anchorOffset();

    window.scrollTo({
        top: Math.max(0, top),
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });

    if (!target.hasAttribute('tabindex') && !isFocusable(target)) {
        target.setAttribute('tabindex', '-1');
        target.dataset.anchorFocus = '';
    }

    target.focus({ preventScroll: true });
}

function isFocusable(el: HTMLElement): boolean {
    return el.matches(
        'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])',
    );
}

function onAnchorClick(event: MouseEvent): void {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    const link = (event.target as Element | null)?.closest?.('a[href]');

    if (!(link instanceof HTMLAnchorElement) || link.target === '_blank') {
        return;
    }

    const url = new URL(link.href, window.location.href);

    if (
        !url.hash ||
        url.origin !== window.location.origin ||
        url.pathname !== window.location.pathname ||
        url.search !== window.location.search
    ) {
        return;
    }

    const target = findAnchorTarget(url.hash);

    if (!target) {
        return;
    }

    // Fase de captura: evitamos también la visita de Inertia al mismo URL.
    event.preventDefault();
    event.stopPropagation();
    history.replaceState(history.state, '', url.hash);
    scrollToElement(target);
}

/** Tras una navegación con hash a un ancla "de recorrido" (`/#visita`). */
export function settleInitialHash(): void {
    const { hash } = window.location;

    if (!hash || document.getElementById(hash.slice(1))) {
        return;
    }

    const target = findAnchorTarget(hash);

    if (target) {
        window.setTimeout(() => scrollToElement(target), 60);
    }
}

let transitionsInstalled = false;
let publicActive = false;

/**
 * Activa View Transitions en visitas GET completas mientras el sitio público
 * está montado. Se compone con cualquier `visitOptions` previo.
 */
function installViewTransitions(): void {
    if (transitionsInstalled) {
        return;
    }

    transitionsInstalled = true;

    const previous = config.get('visitOptions') as
        | ((href: string, options: Record<string, unknown>) => unknown)
        | undefined;

    config.set('visitOptions', ((
        href: string,
        options: Record<string, unknown>,
    ) => {
        const base = (previous?.(href, options) ?? {}) as Record<
            string,
            unknown
        >;
        const partial =
            (Array.isArray(options.only) && options.only.length > 0) ||
            options.preserveState === true ||
            (options.method && options.method !== 'get');

        if (
            !publicActive ||
            partial ||
            prefersReducedMotion() ||
            typeof document === 'undefined' ||
            !('startViewTransition' in document)
        ) {
            return base;
        }

        return { viewTransition: true, ...base };
    }) as never);
}

export function createNavigationEngine(options: { animate: boolean }): {
    destroy: () => void;
} {
    publicActive = true;
    document.addEventListener('click', onAnchorClick, true);

    if (options.animate) {
        installViewTransitions();
    }

    let fullVisit = false;
    const offStart = router.on('start', (event) => {
        const visit = event.detail.visit as {
            only?: string[];
            preserveState?: unknown;
            method?: string;
        };
        fullVisit =
            !(visit.only && visit.only.length) &&
            visit.preserveState !== true &&
            (visit.method ?? 'get') === 'get';
    });

    const offNavigate = router.on('navigate', () => {
        const main = document.getElementById('contenido');
        const usesViewTransition = 'startViewTransition' in document;

        if (
            options.animate &&
            fullVisit &&
            main &&
            !usesViewTransition &&
            !prefersReducedMotion()
        ) {
            main.removeAttribute('data-page-enter');
            void main.offsetWidth;
            main.setAttribute('data-page-enter', '');
            window.setTimeout(
                () => main.removeAttribute('data-page-enter'),
                700,
            );
        }

        fullVisit = false;
        window.setTimeout(settleInitialHash, 0);
    });

    settleInitialHash();

    return {
        destroy() {
            publicActive = false;
            document.removeEventListener('click', onAnchorClick, true);
            offStart();
            offNavigate();
        },
    };
}
