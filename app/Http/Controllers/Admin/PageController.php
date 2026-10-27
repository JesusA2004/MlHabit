<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    private const MANAGED_KEYS = ['mi-historia', 'mi-proceso', 'aviso-privacidad'];

    private const TITLES = [
        'mi-historia' => 'Mi historia',
        'mi-proceso' => 'Mi proceso',
        'aviso-privacidad' => 'Aviso de privacidad',
    ];

    public function index(): Response
    {
        $pages = Page::query()->whereIn('key', self::MANAGED_KEYS)->get()->keyBy('key');

        return Inertia::render('admin/paginas/Index', [
            'pages' => collect(self::MANAGED_KEYS)->map(fn ($key) => [
                'key' => $key,
                'label' => self::TITLES[$key],
                'title' => $pages[$key]->title ?? self::TITLES[$key],
                'updatedAt' => $pages[$key]->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function edit(string $key): Response
    {
        abort_unless(in_array($key, self::MANAGED_KEYS, true), 404);

        $page = Page::query()->where('key', $key)->firstOrFail();
        Gate::authorize('update', $page);

        $page->load('cover');

        $data = [
            'page' => [
                'key' => $page->key,
                'title' => $page->title,
                'intro' => $page->intro,
                'body' => $page->body,
                'cover' => $page->cover ? ['id' => $page->cover->id, 'url' => $page->cover->url()] : null,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
            ],
            'label' => self::TITLES[$key],
        ];

        if ($key === 'mi-proceso') {
            $data['entries'] = $page->entries()->with('cover')->get()->map(fn ($entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'entry_date' => $entry->entry_date?->toDateString(),
                'body' => $entry->body,
                'is_visible' => $entry->is_visible,
                'order' => $entry->order,
                'cover' => $entry->cover ? ['id' => $entry->cover->id, 'url' => $entry->cover->url()] : null,
            ]);
        }

        return Inertia::render('admin/paginas/Form', $data);
    }

    public function update(PageRequest $request, string $key, ActivityLogger $activity): RedirectResponse
    {
        abort_unless(in_array($key, self::MANAGED_KEYS, true), 404);

        $page = Page::query()->where('key', $key)->firstOrFail();
        Gate::authorize('update', $page);

        $page->update([...$request->validated(), 'updated_by' => Auth::id()]);

        $activity->log('pagina.actualizada', $page, ['key' => $key]);

        return back()->with('success', 'Página actualizada.');
    }
}
