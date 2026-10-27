<?php

namespace App\Services;

use App\Models\PageView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agrega los registros anónimos de page_views en las cifras del panel:
 * visitas, visitantes únicos, serie diaria, áreas del sitio, páginas más
 * vistas y procedencia. Todas las consultas son agregadas.
 */
class SiteAnalytics
{
    /** Áreas del sitio: etiqueta → prefijos de ruta. */
    private const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public const AREAS = [
        'Inicio' => ['/'],
        'Catálogo' => ['/catalogo'],
        'Inspiración' => ['/inspiracion'],
        'Preguntas frecuentes' => ['/preguntas-frecuentes'],
        'Solicitud de asesoría' => ['/solicitar'],
        'Agendar visita' => ['/agendar-visita'],
        'Páginas legales' => ['/legal'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function summary(int $days = 30): array
    {
        $days = max(1, $days);
        $from = now()->subDays($days - 1)->toDateString();
        $prevFrom = now()->subDays($days * 2 - 1)->toDateString();
        $prevTo = now()->subDays($days)->toDateString();

        $current = PageView::query()->where('viewed_on', '>=', $from)->count();
        $previous = PageView::query()->whereBetween('viewed_on', [$prevFrom, $prevTo])->count();
        $requests = PageView::query()->where('viewed_on', '>=', $from)->where('path', 'like', '/solicitar%')->where('is_unique', true)->count();

        return [
            'days' => $days,
            'totalViews' => PageView::query()->count(),
            'rangeViews' => $current,
            'uniqueVisitors' => PageView::query()->where('viewed_on', '>=', $from)->where('is_unique', true)->distinct()->count('visitor_hash'),
            'viewsToday' => PageView::query()->where('viewed_on', now()->toDateString())->count(),
            'avgPerDay' => (int) round($current / $days),
            'deltaPct' => $this->deltaPct($current, $previous),
            'requestPageVisitors' => $requests,
        ];
    }

    /**
     * @return list<array{date: string, label: string, total: int, unique: int}>
     */
    public function series(int $days = 30): array
    {
        $days = max(1, $days);
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = DB::table('page_views')
            ->where('viewed_on', '>=', $from->toDateString())
            ->selectRaw('viewed_on, count(*) as total, sum(is_unique) as uniques')
            ->groupBy('viewed_on')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse((string) $row->viewed_on)->toDateString());

        return array_values(collect(range(0, $days - 1))
            ->map(function (int $i) use ($from, $rows) {
                $date = $from->copy()->addDays($i);
                $row = $rows->get($date->toDateString());

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->day.' '.self::MONTHS[$date->month - 1],
                    'total' => (int) ($row->total ?? 0),
                    'unique' => (int) ($row->uniques ?? 0),
                ];
            })
            ->all());
    }

    /**
     * Visitas por área del sitio.
     *
     * @return list<array{area: string, views: int, uniques: int}>
     */
    public function areas(int $days = 30): array
    {
        $from = now()->subDays(max(1, $days) - 1)->toDateString();
        $out = [];

        foreach (self::AREAS as $area => $prefixes) {
            $query = DB::table('page_views')->where('viewed_on', '>=', $from)
                ->where(function ($q) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $prefix === '/' ? $q->orWhere('path', '/') : $q->orWhere('path', $prefix)->orWhere('path', 'like', $prefix.'/%');
                    }
                });

            $row = $query->selectRaw('count(*) as views, coalesce(sum(is_unique), 0) as uniques')->first();
            $out[] = ['area' => $area, 'views' => (int) ($row->views ?? 0), 'uniques' => (int) ($row->uniques ?? 0)];
        }

        usort($out, fn ($a, $b) => $b['views'] <=> $a['views']);

        return $out;
    }

    /**
     * @return list<array{path: string, label: string, views: int, uniques: int}>
     */
    public function topPages(int $days = 30, int $limit = 10): array
    {
        $from = now()->subDays(max(1, $days) - 1)->toDateString();

        return array_values(DB::table('page_views')
            ->where('viewed_on', '>=', $from)
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(max(1, $limit))
            ->get(['path', DB::raw('max(label) as label'), DB::raw('count(*) as views'), DB::raw('sum(is_unique) as uniques')])
            ->map(fn ($row) => [
                'path' => (string) $row->path,
                'label' => (string) ($row->label ?? $row->path),
                'views' => (int) $row->views,
                'uniques' => (int) $row->uniques,
            ])
            ->all());
    }

    /**
     * @return list<array{host: string, views: int}>
     */
    public function topReferrers(int $days = 30, int $limit = 8): array
    {
        $from = now()->subDays(max(1, $days) - 1)->toDateString();

        return array_values(DB::table('page_views')
            ->whereNotNull('referrer_host')
            ->where('viewed_on', '>=', $from)
            ->groupBy('referrer_host')
            ->orderByDesc('views')
            ->limit(max(1, $limit))
            ->get(['referrer_host', DB::raw('count(*) as views')])
            ->map(fn ($row) => ['host' => (string) $row->referrer_host, 'views' => (int) $row->views])
            ->all());
    }

    private function deltaPct(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }
}
