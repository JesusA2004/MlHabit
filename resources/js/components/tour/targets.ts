import type { TourTarget } from './types';

/**
 * Busca en el DOM el elemento de un paso. Ver `TourTarget` para los
 * formatos. Siempre devuelve un elemento visible (con tamaño y sin
 * `display: none`/`visibility: hidden`) o null.
 */
export function resolveTarget(target?: TourTarget): HTMLElement | null {
    if (!target || typeof document === 'undefined') {
        return null;
    }

    const list = Array.isArray(target) ? target : [target];

    for (const spec of list) {
        for (const el of candidates(spec)) {
            if (isVisible(el)) {
                return el;
            }
        }
    }

    return null;
}

function normalize(text: string | null | undefined): string {
    return (text ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
}

function textMatches(el: Element, wanted: string): boolean {
    const text = normalize(el.textContent);
    const label = normalize(el.getAttribute('aria-label'));

    return text === wanted || text.startsWith(wanted) || label === wanted;
}

function candidates(spec: string): HTMLElement[] {
    const all = (selector: string, root: ParentNode = document) =>
        Array.from(root.querySelectorAll<HTMLElement>(selector));

    if (spec.startsWith('css:')) {
        return safeQuery(spec.slice(4));
    }

    if (/^[#.[]/.test(spec)) {
        return safeQuery(spec);
    }

    const colon = spec.indexOf(':');
    const kind = colon > 0 ? spec.slice(0, colon) : '';
    const wanted = normalize(spec.slice(colon + 1));

    switch (kind) {
        case 'card':
            return all('[data-slot="section-card"]').filter((card) => {
                const heading =
                    card.querySelector('h2') ?? card.querySelector('h3');

                return heading ? textMatches(heading, wanted) : false;
            });
        case 'tab':
            return all('[data-slot="tabs-trigger"], [role="tab"]').filter(
                (tab) => textMatches(tab, wanted),
            );
        case 'action': {
            const selector = 'a, button';
            const areas = [
                ...all('[data-tour="page-actions"]'),
                ...all('#contenido'),
            ];

            return areas.flatMap((area) =>
                all(selector, area).filter((el) => textMatches(el, wanted)),
            );
        }
        case 'heading':
            return all('#contenido h1, #contenido h2, #contenido h3').filter(
                (el) => textMatches(el, wanted),
            );
        default:
            return all(`[data-tour="${cssEscape(spec)}"]`);
    }
}

function safeQuery(selector: string): HTMLElement[] {
    try {
        return Array.from(document.querySelectorAll<HTMLElement>(selector));
    } catch {
        return [];
    }
}

function cssEscape(value: string): string {
    return typeof CSS !== 'undefined' && CSS.escape
        ? CSS.escape(value)
        : value.replace(/["\\]/g, '\\$&');
}

export function isVisible(el: HTMLElement): boolean {
    if (!el.isConnected) {
        return false;
    }

    const rect = el.getBoundingClientRect();

    if (rect.width < 2 || rect.height < 2) {
        return false;
    }

    const style = window.getComputedStyle(el);

    if (style.visibility === 'hidden' || style.display === 'none') {
        return false;
    }

    // Dentro de un contenedor oculto (p. ej. pestaña inactiva).
    return el.closest('[hidden], [aria-hidden="true"][inert]') === null;
}

/** Espera a que aparezca el elemento (tras navegar o renderizar). */
export function waitForTarget(
    target: TourTarget | undefined,
    timeout = 2500,
): Promise<HTMLElement | null> {
    return new Promise((resolve) => {
        const found = resolveTarget(target);

        if (found || !target) {
            resolve(found);

            return;
        }

        const started = performance.now();

        const tick = () => {
            const el = resolveTarget(target);

            if (el) {
                resolve(el);

                return;
            }

            if (performance.now() - started > timeout) {
                resolve(null);

                return;
            }

            window.setTimeout(tick, 80);
        };

        window.setTimeout(tick, 80);
    });
}

/** Primer ancestro con scroll propio (o null si el que se desplaza es la página). */
function scrollParent(el: HTMLElement): HTMLElement | null {
    let node = el.parentElement;

    while (
        node &&
        node !== document.body &&
        node !== document.documentElement
    ) {
        const style = window.getComputedStyle(node);

        if (
            /(auto|scroll|overlay)/.test(style.overflowY) &&
            node.scrollHeight > node.clientHeight + 1
        ) {
            return node;
        }

        node = node.parentElement;
    }

    return null;
}

/** ¿Tiene posición fija (o está dentro de algo fijo)? Entonces no se desplaza. */
function isFixed(el: HTMLElement): boolean {
    let node: HTMLElement | null = el;

    while (node && node !== document.body) {
        const position = window.getComputedStyle(node).position;

        if (position === 'fixed' || position === 'sticky') {
            return true;
        }

        node = node.parentElement;
    }

    return false;
}

/**
 * Lleva el elemento a la vista. `reserveBottom` deja libre la parte baja
 * de la pantalla (la tarjeta en forma de hoja inferior en móvil).
 */
export function scrollTargetIntoView(
    el: HTMLElement,
    options: { smooth: boolean; reserveBottom: number; topOffset: number },
): void {
    const behavior: ScrollBehavior = options.smooth ? 'smooth' : 'auto';
    const parent = scrollParent(el);

    if (parent) {
        const rect = el.getBoundingClientRect();
        const box = parent.getBoundingClientRect();

        if (rect.top < box.top || rect.bottom > box.bottom) {
            parent.scrollTo({
                top:
                    parent.scrollTop +
                    (rect.top - box.top) -
                    (box.height - rect.height) / 2,
                behavior,
            });
        }
    }

    if (isFixed(el)) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const viewTop = options.topOffset;
    const viewBottom = window.innerHeight - options.reserveBottom;
    const available = viewBottom - viewTop;

    if (rect.top >= viewTop && rect.bottom <= viewBottom) {
        return;
    }

    // Centra en el espacio libre; si es más alto, alinea su borde superior.
    const offset =
        rect.height < available
            ? rect.top - viewTop - (available - rect.height) / 2
            : rect.top - viewTop - 8;

    window.scrollTo({ top: window.scrollY + offset, behavior });
}
