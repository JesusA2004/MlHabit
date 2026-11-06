<?php

namespace App\Cms\Layout;

/**
 * Videos incrustados de YouTube o Vimeo. Solo se aceptan enlaces de esos
 * dominios (lista blanca) y se guarda el identificador del video, nunca la
 * URL libre: el sitio arma el reproductor con un dominio fijo
 * (youtube-nocookie.com / player.vimeo.com).
 */
final class VideoEmbed
{
    public const PROVIDERS = ['youtube', 'vimeo'];

    /** Dominios permitidos por proveedor. */
    private const HOSTS = [
        'youtube' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'www.youtube-nocookie.com', 'youtube-nocookie.com'],
        'vimeo' => ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'],
    ];

    private const YOUTUBE_ID = '/^[A-Za-z0-9_-]{11}$/';

    private const VIMEO_ID = '/^\d{6,12}$/';

    /**
     * Proveedor e identificador de un enlace; null si no es de un dominio
     * permitido o no trae un video reconocible.
     *
     * @return array{provider: string, id: string}|null
     */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || mb_strlen($url) > 300) {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        $path = trim($parts['path'] ?? '', '/');
        $segments = $path === '' ? [] : explode('/', $path);
        parse_str($parts['query'] ?? '', $query);

        if (in_array($host, self::HOSTS['youtube'], true)) {
            $id = match (true) {
                $host === 'youtu.be' => $segments[0] ?? null,
                ($segments[0] ?? null) === 'watch' || $path === 'watch' => is_string($query['v'] ?? null) ? $query['v'] : null,
                in_array($segments[0] ?? null, ['embed', 'shorts', 'live', 'v'], true) => $segments[1] ?? null,
                default => is_string($query['v'] ?? null) ? $query['v'] : null,
            };

            return is_string($id) && preg_match(self::YOUTUBE_ID, $id) === 1 ? ['provider' => 'youtube', 'id' => $id] : null;
        }

        if (in_array($host, self::HOSTS['vimeo'], true)) {
            $candidates = $host === 'player.vimeo.com' && ($segments[0] ?? null) === 'video'
                ? [$segments[1] ?? null]
                : array_reverse($segments);
            foreach ($candidates as $candidate) {
                if (is_string($candidate) && preg_match(self::VIMEO_ID, $candidate) === 1) {
                    return ['provider' => 'vimeo', 'id' => $candidate];
                }
            }
        }

        return null;
    }

    /** ¿El identificador es válido para el proveedor? */
    public static function validId(string $provider, string $id): bool
    {
        return match ($provider) {
            'youtube' => preg_match(self::YOUTUBE_ID, $id) === 1,
            'vimeo' => preg_match(self::VIMEO_ID, $id) === 1,
            default => false,
        };
    }

    /** URL del reproductor (dominio fijo). */
    public static function playerUrl(string $provider, string $id): string
    {
        return $provider === 'vimeo'
            ? 'https://player.vimeo.com/video/'.$id.'?dnt=1'
            : 'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0&modestbranding=1';
    }
}
