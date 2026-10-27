<?php

namespace App\Services;

use App\Models\PageView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agrega los registros anónimos de page_views en las cifras que consume el
 * panel: visitas, visitantes únicos, serie temporal y rankings de "lo más
 * visto". Todas las consultas son agregadas; nunca exponen datos del
 * visitante individual.
 */
class SiteAnalytics
{
    /**
     * Resumen de KPIs del periodo de {$days} días frente al periodo anterior.
     *
     * @return array<string, mixed>
     */
    public function summary(int $days = 30): array
    {
        $days = max(1, $days);
        $from = now()->subDays($days - 1)->toDateString();
        $prevFrom = now()->subDays($days * 2 - 1)->toDateString();
        $prevTo = now()->subDays($days)->toDateString();

        $current = (int) PageView::query()->where('viewed_on', '>=', $from)->count();
        $previous = (int) PageView::query()
            ->whereBetween('viewed_on', [$prevFrom, $prevTo])
            ->count();

        return [
            'days' => $days,
            'totalViews' => (int) PageView::query()->count(),
            'rangeViews' => $current,
            'uniqueVisitors' => (int) PageView::query()
                ->where('viewed_on', '>=', $from)
                ->where('is_unique', true)
                ->count(),
            'viewsToday' => (int) PageView::query()
                ->where('viewed_on', now()->toDateString())
                ->count(),
            'avgPerDay' => (int) round($current / $days),
            'deltaPct' => $this->deltaPct($current, $previous),
        ];
    }

    /**
     * Serie diaria continua (sin huecos) de visitas y únicos del periodo.
     *
     * @return list<array{date: string, label: string, total: int, unique: int}>
     */
    public function series(int $days = 30): array
    {
        $days = max(1, $days);
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = PageView::query()
            ->where('viewed_on', '>=', $from->toDateString())
            ->selectRaw('viewed_on, count(*) as total, sum(is_unique) as uniques')
            ->groupBy('viewed_on')
            ->get()
            ->keyBy(fn (PageView $row) => Carbon::parse($row->viewed_on)->toDateString());

        return collect(range(0, $days - 1))
            ->map(function (int $i) use ($from, $rows) {
                $date = $from->copy()->addDays($i);
                $key = $date->toDateString();
                $row = $rows->get($key);

                return [
                    'date' => $key,
                    'label' => $date->isoFormat('D MMM'),
                    'total' => (int) ($row->total ?? 0),
                    'unique' => (int) ($row->uniques ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Publicaciones (tips) más vistas del periodo.
     *
     * @return list<array<string, mixed>>
     */
    public function topPosts(int $days = 30, int $limit = 8): array
    {
        $from = now()->subDays(max(1, $days) - 1)->toDateString();

        return DB::table('page_views')
            ->join('posts', 'posts.id', '=', 'page_views.post_id')
            ->leftJoin('categories', 'categories.id', '=', 'posts.category_id')
            ->whereNotNull('page_views.post_id')
            ->where('page_views.viewed_on', '>=', $from)
            ->whereNull('posts.deleted_at')
            ->groupBy('posts.id', 'posts.title', 'posts.slug', 'categories.name', 'categories.color')
            ->orderByDesc('views')
            ->limit(max(1, $limit))
            ->get([
                'posts.title',
                'posts.slug',
                'categories.name as category',
                'categories.color as color',
                DB::raw('count(*) as views'),
                DB::raw('sum(page_views.is_unique) as uniques'),
            ])
            ->map(fn ($row) => [
                'title' => $row->title,
                'slug' => $row->slug,
                'category' => $row->category,
                'color' => $row->color,
                'views' => (int) $row->views,
                'uniques' => (int) $row->uniques,
            ])
            ->all();
    }

    /**
     * Pantallas no-publicación (inicio, mi historia…) más vistas del periodo.
     *
     * @return list<array<string, mixed>>
     */
    public function topPages(int $days = 30, int $limit = 8): array
    {
        $from = now()->subDays(max(1, $days) - 1)->toDateString();

        return DB::table('page_views')
            ->whereNull('post_id')
            ->where('viewed_on', '>=', $from)
            ->groupBy('path', 'label')
            ->orderByDesc('views')
            ->limit(max(1, $limit))
            ->get([
                'path',
                'label',
                DB::raw('count(*) as views'),
                DB::raw('sum(is_unique) as uniques'),
            ])
            ->map(fn ($row) => [
                'path' => $row->path,
                'label' => $row->label ?? $row->path,
                'views' => (int) $row->views,
                'uniques' => (int) $row->uniques,
            ])
            ->all();
    }

    /**
     * Dominios de procedencia externos más frecuentes del periodo.
     *
     * @return list<array<string, mixed>>
     */
    public function topReferrers(int $days = 30, int $limit = 6): array
    {
        $from = now()->subDays(max(1, $days) - 1)->toDateString();

        return DB::table('page_views')
            ->whereNotNull('referrer_host')
            ->where('viewed_on', '>=', $from)
            ->groupBy('referrer_host')
            ->orderByDesc('views')
            ->limit(max(1, $limit))
            ->get(['referrer_host', DB::raw('count(*) as views')])
            ->map(fn ($row) => [
                'host' => $row->referrer_host,
                'views' => (int) $row->views,
            ])
            ->all();
    }

    private function deltaPct(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }
}
