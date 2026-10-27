<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Agrega los datos comunes que necesita cualquier página del sitio público:
 * identidad del sitio, navegación, categorías activas y redes sociales
 * visibles. Se usa desde los controladores públicos para no repetir
 * consultas en cada página.
 */
class PublicSiteDataService
{
    /**
     * @return array<string, mixed>
     */
    public function base(): array
    {
        return [
            'site' => $this->identity(),
            'categories' => $this->categories(),
            'social' => $this->socialLinks(),
            'theme' => $this->theme(),
        ];
    }

    /**
     * Apariencia publicada (Fase 5): se inyecta como variables CSS en tiempo
     * de ejecución (ver PublicLayout.vue), sin recompilar Vite.
     *
     * @return array<string, mixed>
     */
    public function theme(): array
    {
        return Cache::remember('public.theme', 300, function () {
            $colors = ThemeSetting::get('colors', []);
            $fonts = ThemeSetting::get('fonts', []);
            $buttons = ThemeSetting::get('buttons', []);
            $cards = ThemeSetting::get('cards', []);
            $logo = ThemeSetting::get('logo', []);

            $markId = $logo['mark_media_id'] ?? null;
            $faviconId = $logo['favicon_media_id'] ?? null;
            $ids = array_filter([$markId, $faviconId]);
            $media = $ids !== [] ? Media::query()->whereIn('id', $ids)->get()->keyBy('id') : collect();

            return [
                'colors' => $colors,
                'fonts' => $fonts,
                'buttons' => $buttons,
                'cards' => $cards,
                'markUrl' => $markId && $media->has($markId) ? $media->get($markId)->url() : null,
                'faviconUrl' => $faviconId && $media->has($faviconId) ? $media->get($faviconId)->url() : null,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function identity(): array
    {
        $site = ThemeSetting::get('site', ['name' => 'MR. INSIGHT', 'subtitle' => 'POR ZURIEL ÁVILA']);
        $logo = ThemeSetting::get('logo', []);
        $footer = SiteSetting::get('footer', ['tagline' => '']);

        $logoId = $logo['logo_media_id'] ?? null;
        $logoUrl = $logoId ? Media::query()->find((int) $logoId)?->url() : null;

        return [
            'name' => $site['name'] ?? 'MR. INSIGHT',
            'subtitle' => $site['subtitle'] ?? '',
            'logoMediaId' => $logoId,
            'logoUrl' => $logoUrl,
            'footerTagline' => $footer['tagline'] ?? '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return Cache::remember('public.categories.active', 300, function () {
            return Category::query()
                ->where('status', 'active')
                ->orderBy('order')
                ->get(['id', 'name', 'slug', 'color', 'icon', 'description'])
                ->toArray();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function socialLinks(): array
    {
        return Cache::remember('public.social_links.visible', 300, function () {
            return SocialLink::query()
                ->visible()
                ->get(['platform', 'username', 'url', 'icon', 'open_new_tab'])
                ->toArray();
        });
    }
}
