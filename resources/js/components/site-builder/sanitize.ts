/**
 * Saneador de texto enriquecido del editor (lista blanca). El servidor
 * vuelve a sanear con App\Support\Html al guardar; esto evita que el editor
 * muestre con v-html algo pegado o escrito que no se va a conservar.
 */
const ALLOWED = new Set([
    'P',
    'BR',
    'STRONG',
    'B',
    'EM',
    'I',
    'U',
    'A',
    'UL',
    'OL',
    'LI',
]);

const SAFE_HREF = /^(\/(?!\/)|#|https?:\/\/|mailto:|tel:)/i;

function clean(node: Node, doc: Document): Node[] {
    if (node.nodeType === Node.TEXT_NODE) {
        return [doc.createTextNode(node.textContent ?? '')];
    }
    if (!(node instanceof Element)) {
        return [];
    }

    const tag = node.tagName.toUpperCase();
    if (
        ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'SVG', 'IMG'].includes(
            tag,
        )
    ) {
        return [];
    }

    const children = [...node.childNodes].flatMap((child) => clean(child, doc));

    // Los bloques que crea el navegador al editar se vuelven párrafos.
    const target = tag === 'DIV' ? 'P' : tag;
    if (!ALLOWED.has(target)) {
        return children;
    }

    const el = doc.createElement(target.toLowerCase());
    if (target === 'A') {
        const href = node.getAttribute('href') ?? '';
        if (SAFE_HREF.test(href.trim())) {
            el.setAttribute('href', href.trim());
        }
    }
    children.forEach((child) => el.appendChild(child));

    return [el];
}

export function sanitizeRichText(html: string): string {
    if (typeof DOMParser === 'undefined') {
        return html;
    }

    const doc = new DOMParser().parseFromString(
        `<div>${html}</div>`,
        'text/html',
    );
    const root = doc.body.firstElementChild;
    if (!root) {
        return '';
    }

    const out = doc.createElement('div');
    [...root.childNodes]
        .flatMap((child) => clean(child, doc))
        .forEach((child) => out.appendChild(child));

    // Texto suelto (sin párrafo) se envuelve en <p>.
    const html_ = out.innerHTML.trim();
    if (html_ !== '' && !/^<(p|ul|ol)[\s>]/i.test(html_)) {
        return `<p>${html_}</p>`;
    }

    return html_.replace(/<p><\/p>/g, '');
}

/** Texto plano de una línea (títulos, etiquetas). */
export function plainText(value: string): string {
    return value.replace(/\s+/g, ' ').trim();
}
