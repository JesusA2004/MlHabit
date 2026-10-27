<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppearanceRequest;
use App\Models\Media;
use App\Models\ThemeSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editor de apariencia (/admin/apariencia): identidad de marca controlada
 * por plantillas premium, no un constructor visual libre. Los valores
 * "publicados" viven en las mismas claves que ya consume el sitio público
 * (colors, fonts, buttons, cards, logo, site) para no duplicar la fuente
 * de verdad; el borrador vive aparte en "appearance_draft" hasta publicar.
 */
class AppearanceController extends Controller
{
    private const PUBLISHED_KEYS = ['colors', 'fonts', 'buttons', 'cards', 'logo', 'site'];

    private const DRAFT_KEY = 'appearance_draft';

    /**
     * @return array{colors: array<string, string>, fonts: array<string, string>, buttons: array<string, string>, cards: array<string, string>, logo: array<string, int|null>, site: array<string, string>}
     */
    public static function defaults(): array
    {
        return [
            'colors' => [
                'background' => '#0D0E10',
                'foreground' => '#F4F1EA',
                'accent' => '#C7AA7D',
                'muted' => '#ABA9A3',
            ],
            'fonts' => [
                'display' => 'Fraunces',
                'body' => 'Instrument Sans',
            ],
            'buttons' => [
                'style' => 'solid',
                'radius' => 'md',
            ],
            'cards' => [
                'radius' => 'lg',
            ],
            'logo' => [
                'logo_media_id' => null,
                'mark_media_id' => null,
                'favicon_media_id' => null,
            ],
            'site' => [
                'name' => 'MR. INSIGHT',
                'subtitle' => 'POR ZURIEL ÁVILA',
            ],
        ];
    }

    public function edit(): Response
    {
        abort_unless(request()->user()?->canManagePosts(), 403);

        $published = $this->published();
        $draft = ThemeSetting::get(self::DRAFT_KEY);

        return Inertia::render('admin/apariencia/Form', [
            'published' => $published,
            'draft' => $draft ?? $published,
            'hasDraft' => $draft !== null,
            'media' => $this->mediaPreview($draft ?? $published),
            'presets' => $this->presets(),
            'defaults' => self::defaults(),
        ]);
    }

    public function update(AppearanceRequest $request, ActivityLogger $activity): RedirectResponse
    {
        ThemeSetting::set(self::DRAFT_KEY, $request->validated());
        $activity->log('apariencia.borrador_guardado');

        return back()->with('success', 'Borrador de apariencia guardado.');
    }

    public function publish(AppearanceRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validated();

        foreach (self::PUBLISHED_KEYS as $key) {
            ThemeSetting::set($key, $data[$key]);
        }
        ThemeSetting::set(self::DRAFT_KEY, null);

        $activity->log('apariencia.publicada');

        return back()->with('success', 'Apariencia publicada. El sitio público ya refleja los cambios.');
    }

    public function discard(ActivityLogger $activity): RedirectResponse
    {
        ThemeSetting::set(self::DRAFT_KEY, null);
        $activity->log('apariencia.borrador_descartado');

        return back()->with('success', 'Borrador descartado.');
    }

    public function reset(ActivityLogger $activity): RedirectResponse
    {
        foreach (self::defaults() as $key => $value) {
            ThemeSetting::set($key, $value);
        }
        ThemeSetting::set(self::DRAFT_KEY, null);

        $activity->log('apariencia.restablecida');

        return back()->with('success', 'Apariencia restablecida a los valores originales.');
    }

    /**
     * @return array<string, mixed>
     */
    private function published(): array
    {
        $defaults = self::defaults();
        $result = [];

        foreach (self::PUBLISHED_KEYS as $key) {
            $result[$key] = ThemeSetting::get($key, $defaults[$key]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, array{id: int, url: string|null}|null>
     */
    private function mediaPreview(array $state): array
    {
        $ids = array_filter([
            'logo_media_id' => $state['logo']['logo_media_id'] ?? null,
            'mark_media_id' => $state['logo']['mark_media_id'] ?? null,
            'favicon_media_id' => $state['logo']['favicon_media_id'] ?? null,
        ]);

        if ($ids === []) {
            return ['logo' => null, 'mark' => null, 'favicon' => null];
        }

        $media = Media::query()->whereIn('id', $ids)->get()->keyBy('id');

        $resolve = function (?int $id) use ($media): ?array {
            if ($id === null || ! $media->has($id)) {
                return null;
            }

            return ['id' => $id, 'url' => $media->get($id)->url()];
        };

        return [
            'logo' => $resolve($state['logo']['logo_media_id'] ?? null),
            'mark' => $resolve($state['logo']['mark_media_id'] ?? null),
            'favicon' => $resolve($state['logo']['favicon_media_id'] ?? null),
        ];
    }

    /**
     * Plantillas premium: combinaciones de color/tipografía ya probadas para
     * mantener personalización controlada en vez de un constructor libre.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presets(): array
    {
        return [
            [
                'key' => 'carbon-dorado',
                'label' => 'Carbón & Dorado (original)',
                'colors' => self::defaults()['colors'],
                'fonts' => self::defaults()['fonts'],
            ],
            [
                'key' => 'tinta-marfil',
                'label' => 'Tinta & Marfil',
                'colors' => [
                    'background' => '#111214',
                    'foreground' => '#F7F4EC',
                    'accent' => '#B98D5A',
                    'muted' => '#9C9A94',
                ],
                'fonts' => ['display' => 'Playfair Display', 'body' => 'Inter'],
            ],
            [
                'key' => 'grafito-bronce',
                'label' => 'Grafito & Bronce',
                'colors' => [
                    'background' => '#0B0C0E',
                    'foreground' => '#EFEDE7',
                    'accent' => '#A8793F',
                    'muted' => '#8E8C87',
                ],
                'fonts' => ['display' => 'Cormorant Garamond', 'body' => 'Work Sans'],
            ],
        ];
    }
}
