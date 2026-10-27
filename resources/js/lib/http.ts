/**
 * Pequeño helper fetch para los endpoints dedicados de multimedia
 * (subida y búsqueda), que no usan el protocolo de Inertia porque
 * responden JSON plano. Adjunta el token CSRF de la cookie XSRF-TOKEN,
 * tal como lo exige el middleware de Laravel para peticiones que
 * mutan estado.
 */
function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function apiFetch(
    url: string,
    options: RequestInit = {},
): Promise<Response> {
    const headers = new Headers(options.headers);
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('Accept', 'application/json');

    if (options.method && options.method.toUpperCase() !== 'GET') {
        headers.set('X-XSRF-TOKEN', xsrfToken());
    }

    return fetch(url, { ...options, headers, credentials: 'same-origin' });
}
