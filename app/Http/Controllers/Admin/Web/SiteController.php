<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SiteSettingsRequest;
use App\Models\Media;
use App\Models\Page;
use App\Models\ShowcaseProject;
use App\Models\SitePageVersion;
use App\Models\SocialLink;
use App\Models\Testimonial;
use App\Services\ActivityLogger;
use App\Services\MediaUsageService;
use App\Services\Web\MediaPresenter;
use App\Services\Web\PublicSite;
use App\Services\Web\SiteBuilder;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Sitio web: acceso al editor visual de la portada, identidad,
 * contacto, redes sociales, SEO, pie de página, testimonios y páginas
 * legales en una sola pantalla con pestañas.
 */
class SiteController extends Controller
{
    public const TABS = ['portada', 'identidad', 'contacto', 'redes', 'seo', 'pie', 'testimonios', 'paginas'];

    public function index(Request $request, SiteBuilder $builder): Response
    {
        Gate::authorize('content.manage');

        $tab = in_array($request->query('pestana'), self::TABS, true) ? (string) $request->query('pestana') : 'portada';

        $draft = $builder->draft('home');
        $lastVersion = SitePageVersion::query()->where('page', 'home')->with('publisher:id,name')->orderByDesc('number')->first();

        $mediaIds = collect();
        $brand = Settings::group('brand');
        foreach (array_keys(MediaUsageService::BRAND_KEYS) as $key) {
            if (is_numeric($brand[$key] ?? null)) {
                $mediaIds->push((int) $brand[$key]);
            }
        }

        $testimonials = Testimonial::query()->with(['media.variants', 'showcaseProject:id,title'])->orderBy('sort')->orderByDesc('id')->get();
        $mediaIds = $mediaIds->unique()->values()->all();
        $media = Media::query()->with('variants')->whereIn('id', $mediaIds)->get()->keyBy('id');

        return Inertia::render('admin/sitio/Index', [
            'tab' => $tab,
            'editor' => [
                'sections' => count($draft->snapshot['sections']),
                'hasChanges' => $builder->hasUnpublishedChanges($draft),
                'draftSavedAt' => $draft->updated_at?->toIso8601String(),
                'lastVersion' => $lastVersion !== null ? [
                    'number' => $lastVersion->number,
                    'publishedAt' => $lastVersion->published_at->toIso8601String(),
                    'publishedBy' => $lastVersion->publisher?->name,
                ] : null,
            ],
            'media' => $media->map(fn (Media $m) => MediaPresenter::ref($m)),
            'settings' => [
                'brand' => $brand,
                'contact' => Settings::group('contact'),
                'seo' => Settings::group('seo'),
                'footer' => Settings::group('footer'),
            ],
            'socialLinks' => SocialLink::query()->orderBy('order')->get()->map(fn (SocialLink $l) => [
                'id' => $l->id,
                'platform' => $l->platform,
                'platformLabel' => PublicSite::platformLabel($l->platform),
                'username' => $l->username,
                'url' => $l->url,
                'isVisible' => $l->is_visible,
                'openNewTab' => $l->open_new_tab,
                'order' => $l->order,
            ])->values(),
            'testimonials' => $testimonials->map(fn (Testimonial $t) => [
                'id' => $t->id,
                'authorName' => $t->author_name,
                'authorContext' => $t->author_context,
                'quote' => $t->quote,
                'showcaseProjectId' => $t->showcase_project_id,
                'showcaseProjectTitle' => $t->showcaseProject?->title,
                'media' => MediaPresenter::ref($t->media),
                'consentObtainedAt' => $t->consent_obtained_at?->toDateString(),
                'consentNote' => $t->consent_note,
                'isPublished' => $t->is_published,
            ])->values(),
            'pages' => Page::query()->with('editor:id,name')->orderBy('title')->get()->map(fn (Page $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'version' => $p->version,
                'isPublished' => $p->is_published,
                'showInFooter' => $p->show_in_footer,
                'publishedAt' => $p->published_at?->toIso8601String(),
                'updatedAt' => $p->updated_at?->toIso8601String(),
                'updatedBy' => $p->editor?->name,
            ])->values(),
            'showcaseOptions' => ShowcaseProject::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->title])->values(),
            'can' => [
                'publish' => Gate::allows('content.publish'),
            ],
        ]);
    }

    public function updateSettings(SiteSettingsRequest $request, string $group, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('content.manage');
        abort_unless(in_array($group, SiteSettingsRequest::GROUPS, true), 404);

        $before = Settings::group($group);
        $values = $request->validated();
        if ($group === 'seo' && isset($values['twitter_handle']) && is_string($values['twitter_handle'])) {
            $values['twitter_handle'] = ltrim($values['twitter_handle'], '@');
        }
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        Settings::put($group, $values);
        PublicSite::flush();

        $activity->log('sitio.ajustes_actualizados', null, ['group' => $group, 'before' => $before, 'after' => Settings::group($group)]);

        return back()->with('success', 'Cambios guardados.');
    }
}
