<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendario editorial: publicaciones programadas y publicadas por mes,
 * más los borradores pendientes de fecha. Usa `scheduled_at` y
 * `published_at`, que ya gestiona PostService.
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->canManagePosts(), 403);

        $month = $this->month($request->query('mes'));
        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        return Inertia::render('admin/calendario/Index', [
            'month' => $month->format('Y-m'),
            'rangeStart' => $start->toDateString(),
            'rangeEnd' => $end->toDateString(),
            'events' => self::events($start, $end),
            'unscheduled' => Post::query()
                ->where('status', PostStatus::Draft)
                ->with('category:id,name,color')
                ->orderByDesc('updated_at')
                ->limit(12)
                ->get(['id', 'title', 'category_id', 'updated_at'])
                ->map(fn (Post $post) => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'category' => $post->category?->name,
                    'color' => $post->category?->color,
                    'updatedAt' => $post->updated_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function events(CarbonInterface $start, CarbonInterface $end): array
    {
        return Post::query()
            ->with('category:id,name,color')
            ->where(function ($q) use ($start, $end) {
                $q->where(fn ($s) => $s->where('status', PostStatus::Scheduled)->whereBetween('scheduled_at', [$start, $end->copy()->endOfDay()]))
                    ->orWhere(fn ($p) => $p->where('status', PostStatus::Published)->whereBetween('published_at', [$start, $end->copy()->endOfDay()]));
            })
            ->get(['id', 'title', 'status', 'category_id', 'scheduled_at', 'published_at'])
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'status' => $post->status->value,
                'date' => ($post->status === PostStatus::Scheduled ? $post->scheduled_at : $post->published_at)?->toIso8601String(),
                'category' => $post->category?->name,
                'color' => $post->category?->color,
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }

    private function month(mixed $value): CarbonInterface
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value)) {
            return Date::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
        }

        return now()->startOfMonth();
    }
}
