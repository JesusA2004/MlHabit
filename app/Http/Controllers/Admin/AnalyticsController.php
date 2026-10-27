<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SiteAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de estadísticas del sitio (/admin/estadisticas): visitas totales,
 * visitantes únicos, evolución diaria y rankings de "lo más visto".
 *
 * Todas las cifras provienen de agregados anónimos de page_views; no se
 * expone ningún dato del visitante.
 */
class AnalyticsController extends Controller
{
    /** Rangos permitidos (en días) para evitar consultas arbitrarias. */
    private const RANGES = [7, 30, 90];

    public function __invoke(Request $request, SiteAnalytics $analytics): Response
    {
        $days = (int) $request->integer('dias', 30);
        if (! in_array($days, self::RANGES, true)) {
            $days = 30;
        }

        return Inertia::render('admin/estadisticas/Index', [
            'range' => $days,
            'ranges' => self::RANGES,
            'summary' => $analytics->summary($days),
            'series' => $analytics->series($days),
            'topPosts' => $analytics->topPosts($days, 8),
            'topPages' => $analytics->topPages($days, 8),
            'referrers' => $analytics->topReferrers($days, 6),
        ]);
    }
}
