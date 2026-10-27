<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemSettingsRequest;
use App\Models\SiteSetting;
use App\Services\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/configuracion: parámetros de negocio de App\Support\Settings.
 * La política de visitas (/admin/visitas/politica) y la marca, contacto y
 * SEO (/admin/sitio) tienen pantallas propias en sus módulos.
 */
class SettingsController extends Controller
{
    /** Grupos editables desde esta pantalla. */
    public const GROUPS = ['commercial', 'consultation', 'leads', 'security', 'seo'];

    public function index(): Response
    {
        $commercial = Settings::group('commercial');
        $taxRate = (string) $commercial['tax_rate'];
        $taxRate = is_numeric($taxRate) ? $taxRate : '0';

        return Inertia::render('admin/configuracion/Index', [
            'settings' => [
                'commercial' => [
                    'deposit_percent' => (string) $commercial['deposit_percent'],
                    // Se muestra como porcentaje: "0.1600" → "16.00".
                    'tax_rate' => bcmul($taxRate, '100', 2),
                    'prices_include_tax' => (bool) $commercial['prices_include_tax'],
                    'quote_validity_days' => (int) $commercial['quote_validity_days'],
                    'currency' => (string) $commercial['currency'],
                ],
                'consultation' => Settings::group('consultation'),
                'leads' => Settings::group('leads'),
                'security' => Settings::group('security'),
                // Solo la verificación de buscadores; título y descripción viven en /admin/sitio.
                'seo' => [
                    'google_site_verification' => (string) (Settings::get('seo.google_site_verification') ?? ''),
                    'bing_site_verification' => (string) (Settings::get('seo.bing_site_verification') ?? ''),
                ],
            ],
            'channels' => [
                ['value' => 'video', 'label' => 'Videollamada'],
                ['value' => 'phone', 'label' => 'Llamada telefónica'],
                ['value' => 'whatsapp', 'label' => 'WhatsApp'],
            ],
            'links' => [
                'visitPolicy' => '/admin/visitas/politica',
                'site' => '/admin/sitio',
            ],
        ]);
    }

    public function update(SystemSettingsRequest $request, string $group, ActivityLogger $activity): RedirectResponse
    {
        $before = Settings::group($group);
        $values = $request->normalized();

        Settings::put($group, $values);
        $after = Settings::group($group);

        $changedKeys = array_keys(array_filter(
            $after,
            fn ($value, $key) => ($before[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changedKeys !== []) {
            $activity->log('configuracion.actualizada', SiteSetting::query()->where('key', $group)->first(), [
                'group' => $group,
                'before' => array_intersect_key($before, array_flip($changedKeys)),
                'after' => array_intersect_key($after, array_flip($changedKeys)),
            ]);
        }

        return back()->with('success', 'Configuración guardada.');
    }
}
