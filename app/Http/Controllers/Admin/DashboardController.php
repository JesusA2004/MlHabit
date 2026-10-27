<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Media;
use App\Models\Post;
use App\Models\StorageProvider;
use App\Models\StudioPage;
use App\Services\SiteAnalytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(SiteAnalytics $analytics): Response
    {
        $totals = DB::table('posts')
            ->whereNull('deleted_at')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Últimos 6 meses, incluidos los meses sin publicaciones (eje continuo).
        $start = now()->subMonths(5)->startOfMonth();
        $created = Post::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (Post $post): string => $post->created_at?->format('Y-m') ?? 'sin-fecha')
            ->map->count();
        $publishedByMonth = Post::query()
            ->where('status', PostStatus::Published)
            ->where('published_at', '>=', $start)
            ->get(['published_at'])
            ->groupBy(fn (Post $post): string => $post->published_at?->format('Y-m') ?? 'sin-fecha')
            ->map->count();
        $postsByMonth = collect(range(0, 5))->map(function (int $i) use ($start, $created, $publishedByMonth) {
            $month = $start->copy()->addMonths($i)->format('Y-m');

            return ['month' => $month, 'total' => (int) ($created[$month] ?? 0), 'published' => (int) ($publishedByMonth[$month] ?? 0)];
        });

        $postsByCategory = DB::table('posts')
            ->whereNull('posts.deleted_at')
            ->join('categories', 'categories.id', '=', 'posts.category_id')
            ->selectRaw('categories.name as category, categories.color as color, count(*) as total')
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->get();

        $mediaBytes = (int) Media::query()->sum('size');
        $provider = StorageProvider::query()->where('is_active', true)->first();

        $recentPosts = Post::query()
            ->with(['category', 'author', 'cover'])
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'totalPosts' => Post::query()->count(),
                'published' => (int) ($totals[PostStatus::Published->value] ?? 0),
                'drafts' => (int) ($totals[PostStatus::Draft->value] ?? 0),
                'scheduled' => (int) ($totals[PostStatus::Scheduled->value] ?? 0),
                'archived' => (int) ($totals[PostStatus::Archived->value] ?? 0),
                'featured' => Post::query()->where('is_featured', true)->count(),
                'mediaCount' => Media::query()->count(),
                'mediaBytes' => $mediaBytes,
                'publishedThisMonth' => Post::query()->where('status', PostStatus::Published)->where('published_at', '>=', now()->startOfMonth())->count(),
            ],
            'charts' => [
                'postsByMonth' => $postsByMonth,
                'postsByCategory' => $postsByCategory,
            ],
            'analytics' => [
                'summary' => $analytics->summary(30),
                'topPosts' => $analytics->topPosts(30, 5),
            ],
            'recentPosts' => $recentPosts->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status->value,
                'category' => $post->category?->name,
                'categoryColor' => $post->category?->color,
                'author' => $post->author?->name,
                'coverUrl' => $post->cover?->url(),
                'createdAt' => $post->created_at?->toIso8601String(),
                'updatedAt' => $post->updated_at?->toIso8601String(),
            ]),
            'recentMedia' => Media::query()
                ->with('variants')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(fn (Media $m) => [
                    'id' => $m->id,
                    'filename' => $m->original_filename,
                    'type' => $m->type->value,
                    'status' => $m->status->value,
                    'url' => $m->url(),
                    'posterUrl' => $m->variantUrl('poster'),
                ]),
            'activity' => ActivityLog::query()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'label' => ActivityController::ACTION_LABELS[$log->action] ?? $log->action,
                    'detail' => $this->detail($log->changes ?? []),
                    'user' => $log->user?->name,
                    'result' => $log->result,
                    'createdAt' => $log->created_at?->toIso8601String(),
                ]),
            'storage' => [
                'provider' => $provider !== null ? $provider->label : 'Almacenamiento local',
                'bytes' => $mediaBytes,
            ],
            'upcoming' => CalendarController::events(now()->startOfDay(), now()->addDays(13)->endOfDay()),
            'studio' => StudioPage::query()
                ->with('publishedVersion:id,document')
                ->orderBy('id')
                ->get()
                ->map(fn (StudioPage $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'path' => $p->publicPath(),
                    'hasChanges' => $p->hasUnpublishedChanges(),
                    'publishedAt' => $p->published_at?->toIso8601String(),
                ]),
            'today' => Carbon::now()->toDateString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function detail(array $changes): ?string
    {
        foreach (['title', 'page', 'filename', 'name'] as $key) {
            if (isset($changes[$key]) && is_string($changes[$key])) {
                return $changes[$key];
            }
        }

        return null;
    }
}
