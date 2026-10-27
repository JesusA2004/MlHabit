<?php

namespace App\Http\Controllers\Admin\Web;

use App\Enums\SiteSectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SiteSettingsRequest;
use App\Models\Media;
use App\Models\Page;
use App\Models\ShowcaseProject;
use App\Models\SiteSection;
use App\Models\SocialLink;
use App\Models\Testimonial;
use App\Services\ActivityLogger;
use App\Services\MediaUsageService;
use App\Services\Web\HomePage;
use App\Services\Web\MediaPresenter;
use App\Services\Web\PublicSite;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Sitio web: portada (secciones del CMS), identidad,
 * contacto, redes sociales, SEO, pie de página, testimonios y páginas
 * legales en una sola pantalla con pestañas.
 */
class SiteController extends Controller
{
    public const TABS = ['portada', 'identidad', 'contacto', 'redes', 'seo', 'pie', 'testimonios', 'paginas'];

    public function index(Request $request, HomePage $home): Response
    {
        Gate::authorize('content.manage');

        $tab = in_array($request->query('pestana'), self::TABS, true) ? (string) $request->query('pestana') : 'portada';

        $this->ensureSections();
        $sections = SiteSection::query()->forPage('home')->with('editor:id,name')->get();

        $mediaIds = $sections->flatMap(fn (SiteSection $s) => $s->schema()->mediaIds($s->content));
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
            'sections' => $sections->map(fn (SiteSection $s) => [
                'id' => $s->id,
                'type' => $s->type->value,
                'sort' => $s->sort,
                'isVisible' => $s->is_visible,
                'content' => $s->content,
                'schema' => $s->schema()->toArray(),
                'updatedAt' => $s->updated_at?->toIso8601String(),
                'updatedBy' => $s->editor?->name,
            ])->values(),
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
            'visitPolicy' => $home->visitPolicy(),
            'can' => [
                'publish' => Gate::allows('content.publish'),
            ],
        ]);
    }

    public function updateSection(Request $request, SiteSection $section, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $section);

        $request->validate(['content' => ['required', 'array']]);
        /** @var array<string, mixed> $input */
        $input = (array) $request->input('content');
        $content = $section->schema()->validate($input);

        $before = $section->content;
        $section->forceFill(['content' => $content, 'updated_by' => $request->user()?->id])->save();

        $activity->log('sitio.seccion_actualizada', $section, ['type' => $section->type->value, 'before' => $before, 'after' => $section->content]);

        return back()->with('success', 'Sección guardada. Los cambios ya se ven en el sitio.');
    }

    public function resetSection(Request $request, SiteSection $section, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $section);

        $before = $section->content;
        $section->forceFill(['content' => $section->schema()->defaults(), 'updated_by' => $request->user()?->id])->save();
        $activity->log('sitio.seccion_restablecida', $section, ['type' => $section->type->value, 'before' => $before]);

        return back()->with('success', 'Se restableció el texto sugerido de la sección.');
    }

    public function toggleSection(Request $request, SiteSection $section, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('publish', SiteSection::class);

        $data = $request->validate(['is_visible' => ['required', 'boolean']]);
        $section->forceFill(['is_visible' => (bool) $data['is_visible'], 'updated_by' => $request->user()?->id])->save();

        $activity->log($section->is_visible ? 'sitio.seccion_mostrada' : 'sitio.seccion_ocultada', $section, ['type' => $section->type->value]);

        return back()->with('success', $section->is_visible ? 'La sección ahora es visible.' : 'La sección quedó oculta.');
    }

    public function reorderSections(Request $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('content.manage');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:site_sections,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $index => $id) {
                SiteSection::query()->whereKey($id)->update(['sort' => ($index + 1) * 10]);
            }
        });
        PublicSite::flush();

        $activity->log('sitio.secciones_reordenadas', null, ['ids' => $data['ids']]);

        return back()->with('success', 'Orden de la portada actualizado.');
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

    /** Crea las secciones que falten (instalación nueva sin sembrar). */
    private function ensureSections(): void
    {
        $existing = SiteSection::query()->where('page', 'home')->pluck('type')->map(fn ($t) => $t instanceof SiteSectionType ? $t->value : (string) $t)->all();

        foreach (SiteSectionType::cases() as $index => $type) {
            if (! in_array($type->value, $existing, true)) {
                SiteSection::query()->create([
                    'page' => 'home',
                    'type' => $type,
                    'sort' => ($index + 1) * 10,
                    'is_visible' => true,
                    'content' => $type->schema()->defaults(),
                ]);
            }
        }
    }
}
