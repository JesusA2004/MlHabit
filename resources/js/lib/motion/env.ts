/**
 * Entorno del sistema de movimiento: detección de capacidades y de la
 * preferencia de movimiento reducido. Sin dependencias.
 */

export const isBrowser =
    typeof window !== 'undefined' && typeof document !== 'undefined';

const REDUCED_QUERY = '(prefers-reduced-motion: reduce)';
const FINE_POINTER_QUERY = '(hover: hover) and (pointer: fine)';

/** ¿La persona pidió reducir el movimiento? (también si no hay `matchMedia`). */
export function prefersReducedMotion(): boolean {
    if (!isBrowser || typeof window.matchMedia !== 'function') {
        return false;
    }

    return window.matchMedia(REDUCED_QUERY).matches;
}

/** Puntero fino con hover real (ratón/trackpad): habilita imán, inclinación y foco. */
export function hasFinePointer(): boolean {
    if (!isBrowser || typeof window.matchMedia !== 'function') {
        return false;
    }

    return window.matchMedia(FINE_POINTER_QUERY).matches;
}

/** Puede animar revelados: hay IntersectionObserver y no se pidió reducir. */
export function canAnimate(): boolean {
    return (
        isBrowser && 'IntersectionObserver' in window && !prefersReducedMotion()
    );
}

/** Suscribe un callback a cambios de la preferencia de movimiento reducido. */
export function onReducedMotionChange(
    callback: (reduced: boolean) => void,
): () => void {
    if (!isBrowser || typeof window.matchMedia !== 'function') {
        return () => {};
    }

    const query = window.matchMedia(REDUCED_QUERY);
    const handler = (event: MediaQueryListEvent) => callback(event.matches);
    query.addEventListener('change', handler);

    return () => query.removeEventListener('change', handler);
}

/**
 * Activa `html.motion-ready` lo antes posible (al importar el módulo), antes
 * de que Vue pinte la primera página. Sin JS o con movimiento reducido la
 * clase no existe y todo el contenido se muestra tal cual (mejora progresiva).
 */
export function markMotionReady(): void {
    if (!isBrowser) {
        return;
    }

    document.documentElement.classList.toggle('motion-ready', canAnimate());
}

/** Altura útil para desplazamientos a anclas (cabecera fija + aire). */
export function anchorOffset(): number {
    if (!isBrowser) {
        return 0;
    }

    const header = document.querySelector<HTMLElement>('[data-site-header]');

    return (header?.offsetHeight ?? 72) + 16;
}
