/**
 * Conteo de cifras YA presentes en el contenido (`data-countup`). Nunca
 * inventa números: lee el texto del elemento ("12 años", "$1,250", "98%"),
 * cuenta desde cero hasta esa misma cifra y deja el texto original intacto.
 * Trabaja sobre el nodo de texto existente (no reemplaza nodos), así Vue
 * sigue controlando el contenido.
 */

const NUMBER = /\d[\d,]*(?:\.\d+)?/;

function findNumberNode(el: HTMLElement): Text | null {
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    let node = walker.nextNode();

    while (node) {
        if (NUMBER.test(node.nodeValue ?? '')) {
            return node as Text;
        }

        node = walker.nextNode();
    }

    return null;
}

export function animateCount(el: HTMLElement, duration = 1100): void {
    if (el.dataset.countupDone) {
        return;
    }

    el.dataset.countupDone = '1';

    const node = findNumberNode(el);
    const original = node?.nodeValue ?? '';
    const match = original.match(NUMBER);

    if (!node || !match || match.index === undefined) {
        return;
    }

    const raw = match[0];
    const target = Number.parseFloat(raw.replace(/,/g, ''));

    if (!Number.isFinite(target) || target === 0) {
        return;
    }

    const decimals = raw.includes('.') ? (raw.split('.')[1]?.length ?? 0) : 0;
    const format = new Intl.NumberFormat('es-MX', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
        useGrouping: raw.includes(','),
    });
    const before = original.slice(0, match.index);
    const after = original.slice(match.index + raw.length);

    // Lectores de pantalla: siempre el valor final.
    if (!el.hasAttribute('aria-label')) {
        el.setAttribute('aria-label', (el.textContent ?? '').trim());
    }

    // Reserva el ancho final para que el conteo no mueva el diseño.
    const width = el.getBoundingClientRect().width;
    el.style.fontVariantNumeric = 'tabular-nums';

    if (getComputedStyle(el).display === 'inline') {
        el.style.display = 'inline-block';
    }

    el.style.minWidth = `${width}px`;

    const start = performance.now();

    const tick = (now: number) => {
        const t = Math.min(1, (now - start) / duration);
        // easeOutExpo: arranca rápido y se asienta con suavidad.
        const eased = t === 1 ? 1 : 1 - Math.pow(2, -10 * t);

        node.nodeValue =
            t < 1
                ? `${before}${format.format(target * eased)}${after}`
                : original;

        if (t < 1) {
            requestAnimationFrame(tick);
        } else {
            el.style.minWidth = '';
        }
    };

    requestAnimationFrame(tick);
}
