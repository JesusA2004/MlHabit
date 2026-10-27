<?php

namespace App\Support\Crm;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Atribución de primer contacto (first-touch): UTMs, referrer externo y
 * página de llegada se guardan en sesión la primera vez y no se
 * sobrescriben después. Al enviar /solicitar se combinan con los campos
 * ocultos del formulario (por si la sesión expiró).
 */
final class Attribution
{
    public const SESSION_KEY = 'crm.attribution';

    public const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    private const LIMITS = [
        'utm_source' => 120, 'utm_medium' => 120, 'utm_campaign' => 160, 'utm_term' => 160,
        'utm_content' => 160, 'referrer' => 500, 'landing_url' => 500,
    ];

    /** Guarda la atribución de primer contacto si aún no existe en la sesión. */
    public static function capture(Request $request): void
    {
        if (! $request->hasSession() || ! $request->isMethod('GET')) {
            return;
        }

        $session = $request->session();
        $current = $session->get(self::SESSION_KEY);
        $hasUtm = collect(self::UTM_KEYS)->contains(fn ($k) => filled($request->query($k)));

        if (is_array($current) && ($current['utm_source'] ?? null) !== null) {
            return; // Primer contacto con campaña ya registrado.
        }

        if (is_array($current) && ! $hasUtm) {
            return;
        }

        $data = [];
        foreach (self::UTM_KEYS as $key) {
            $data[$key] = self::clean($request->query($key), $key);
        }
        $data['referrer'] = self::externalReferrer($request);
        $data['landing_url'] = self::clean($request->fullUrl(), 'landing_url');

        $session->put(self::SESSION_KEY, $data);
    }

    /**
     * Combina sesión (prioridad) con los campos ocultos enviados.
     *
     * @param  array<string, mixed>  $submitted
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_term: ?string, utm_content: ?string, referrer: ?string, landing_url: ?string, device_type: string}
     */
    public static function resolve(Request $request, array $submitted = []): array
    {
        $stored = $request->hasSession() ? (array) $request->session()->get(self::SESSION_KEY, []) : [];
        $out = [];
        foreach ([...self::UTM_KEYS, 'referrer', 'landing_url'] as $key) {
            $out[$key] = self::clean($stored[$key] ?? null, $key) ?? self::clean($submitted[$key] ?? null, $key);
        }
        $out['device_type'] = self::deviceType($request->userAgent());

        /** @var array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_term: ?string, utm_content: ?string, referrer: ?string, landing_url: ?string, device_type: string} $out */
        return $out;
    }

    /** Clasificación gruesa del dispositivo a partir del User-Agent. */
    public static function deviceType(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        if ($ua === '') {
            return 'desktop';
        }
        if (preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile|windows phone/', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function externalReferrer(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');
        if (! is_string($referrer) || $referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);
        if (! is_string($host) || $host === $request->getHost()) {
            return null;
        }

        return self::clean($referrer, 'referrer');
    }

    private static function clean(mixed $value, string $key): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : Str::limit($value, self::LIMITS[$key] ?? 160, '');
    }
}
