/**
 * Claves `data-tour` derivadas de rutas, para que la navegación (barra
 * lateral, pestañas) y los recorridos usen exactamente el mismo nombre.
 *
 * `navTourKey('/admin/pagos-proveedores')` → `nav-admin-pagos-proveedores`
 */
function slug(href: string): string {
    return (
        href
            .split(/[?#]/)[0]
            .replace(/^\/+|\/+$/g, '')
            .replace(/[^a-z0-9]+/gi, '-')
            .toLowerCase() || 'inicio'
    );
}

export function navTourKey(href: string): string {
    return `nav-${slug(href)}`;
}

export function tabTourKey(href: string): string {
    return `tab-${slug(href)}`;
}

export function groupTourKey(label: string): string {
    return `nav-group-${label
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/gi, '-')
        .toLowerCase()}`;
}
