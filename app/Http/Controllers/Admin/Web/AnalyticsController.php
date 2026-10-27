<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Services\SiteAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Estadísticas del sitio público (/admin/estadisticas): agregados
 * anónimos de page_views, sin datos del visitante.
 */
class AnalyticsController extends Controller
{
    private const RANGES = [7, 30, 90];

    public function __invoke(Request $request, SiteAnalytics $analytics): Response
    {
        Gate::authorize('dashboard.view');

        $days = $request->integer('dias', 30);
        if (! in_array($days, self::RANGES, true)) {
            $days = 30;
        }

        return Inertia::render('admin/estadisticas/Index', [
            'range' => $days,
            'ranges' => self::RANGES,
            'summary' => $analytics->summary($days),
            'series' => $analytics->series($days),
            'areas' => $analytics->areas($days),
            'topPages' => $analytics->topPages($days, 10),
            'referrers' => $analytics->topReferrers($days, 8),
        ]);
    }
}
