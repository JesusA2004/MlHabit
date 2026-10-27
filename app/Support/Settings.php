<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Configuración de negocio editable desde Administración → Configuración.
 * Ninguna decisión comercial está fija en código: aquí solo viven valores
 * por defecto seguros para desarrollo (documentados en docs/DECISIONES.md).
 *
 * Los valores que afectan dinero se CONGELAN en cada entidad al usarse
 * (contratos, visitas, órdenes), así un cambio posterior no altera lo ya
 * pactado.
 */
final class Settings
{
    /**
     * Grupos y valores por defecto.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'commercial' => [
                // Porcentaje de anticipo sugerido para nuevos contratos.
                'deposit_percent' => '10.00',
                // IVA aplicado en cotizaciones (16% en México).
                'tax_rate' => '0.1600',
                'prices_include_tax' => true,
                'quote_validity_days' => 15,
                'currency' => 'MXN',
            ],
            'consultation' => [
                'enabled' => true,
                'duration_minutes' => 30,
                'buffer_minutes' => 10,
                'price' => '0.00',
                'booking_window_days' => 21,
                'min_notice_hours' => 4,
                'channels' => ['video', 'phone', 'whatsapp'],
            ],
            'leads' => [
                'follow_up_hours' => 24,
            ],
            'contact' => [
                'phone' => null,
                'whatsapp' => null,
                'email' => null,
                'address' => null,
                'hours' => null,
            ],
            'brand' => [
                'tagline' => 'Diseñamos, fabricamos e instalamos muebles pensados para tu espacio.',
                'logo_media_id' => null,
                'logo_dark_media_id' => null,
                'favicon_media_id' => null,
                'og_media_id' => null,
            ],
            'seo' => [
                'title' => 'ML Habitat — Cocinas integrales, clósets y muebles a la medida',
                'description' => 'Diseñamos, fabricamos e instalamos cocinas integrales, clósets, recámaras y muebles a la medida. Agenda una asesoría y una visita técnica a domicilio.',
                'twitter_handle' => null,
            ],
            'footer' => [
                'text' => null,
                'legal_name' => null,
            ],
            'security' => [
                // Si es true, el personal interno debe activar la verificación
                // en dos pasos antes de entrar a /admin (EnsureTwoFactorForStaff).
                'require_2fa_for_staff' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function group(string $group): array
    {
        $defaults = self::defaults()[$group] ?? [];
        $stored = SiteSetting::get($group, []);

        return array_replace($defaults, is_array($stored) ? $stored : []);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        [$group, $field] = array_pad(explode('.', $key, 2), 2, null);
        $values = self::group((string) $group);

        if ($field === null) {
            return $values;
        }

        return $values[$field] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function put(string $group, array $values): void
    {
        $allowed = array_keys(self::defaults()[$group] ?? []);
        $current = self::group($group);

        SiteSetting::set($group, array_replace($current, array_intersect_key($values, array_flip($allowed))));
    }
}
