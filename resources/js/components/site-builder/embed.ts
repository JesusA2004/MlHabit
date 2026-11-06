/**
 * Reconoce enlaces de YouTube y Vimeo (misma lista blanca que
 * App\Cms\Layout\VideoEmbed). El servidor vuelve a validar al guardar.
 */
export type VideoEmbedRef = { provider: 'youtube' | 'vimeo'; id: string };

const YOUTUBE_HOSTS = [
    'youtube.com',
    'www.youtube.com',
    'm.youtube.com',
    'music.youtube.com',
    'youtu.be',
    'www.youtube-nocookie.com',
    'youtube-nocookie.com',
];
const VIMEO_HOSTS = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];
const YOUTUBE_ID = /^[A-Za-z0-9_-]{11}$/;
const VIMEO_ID = /^\d{6,12}$/;

export function parseVideoUrl(raw: string): VideoEmbedRef | null {
    let value = raw.trim();
    if (!value || value.length > 300) return null;
    if (!/^https?:\/\//i.test(value)) value = `https://${value}`;

    let url: URL;
    try {
        url = new URL(value);
    } catch {
        return null;
    }

    const host = url.hostname.toLowerCase();
    const segments = url.pathname.split('/').filter(Boolean);

    if (YOUTUBE_HOSTS.includes(host)) {
        let id: string | null = null;
        if (host === 'youtu.be') id = segments[0] ?? null;
        else if (['embed', 'shorts', 'live', 'v'].includes(segments[0] ?? ''))
            id = segments[1] ?? null;
        else id = url.searchParams.get('v');

        return id && YOUTUBE_ID.test(id) ? { provider: 'youtube', id } : null;
    }

    if (VIMEO_HOSTS.includes(host)) {
        const candidates =
            host === 'player.vimeo.com' && segments[0] === 'video'
                ? [segments[1]]
                : [...segments].reverse();
        const id = candidates.find((c) => c && VIMEO_ID.test(c));

        return id ? { provider: 'vimeo', id } : null;
    }

    return null;
}
