<?php

namespace App\Services\Web;

use App\Models\Media;
use App\Models\Page;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Models\SocialLink;
use App\Support\ResponsiveImage;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * Datos compartidos por todas las páginas públicas (`site`): identidad,
 * contacto, redes, navegación y páginas legales del pie. Se cachean y se
 * invalidan al guardar cualquier contenido del sitio (FlushesPublicSite).
 */
class PublicSite
{
    private const CACHE_KEY = 'web.public_site.v1';

    /** Claves de caché que dependen del contenido editable. */
    private const KEYS = [self::CACHE_KEY, 'web.home.v1', 'web.sitemap.v1', 'web.sitemap.v2'];

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }
    }

    public static function name(): string
    {
        $name = (string) config('app.name');

        return $name === '' || $name === 'Laravel' ? 'ML Habitat' : $name;
    }

    /**
     * @return array<string, mixed>
     */
    public function site(): array
    {
        /** @var array<string, mixed> */
        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->build());
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $brand = Settings::group('brand');
        $contact = Settings::group('contact');
        $footer = Settings::group('footer');

        $ids = array_values(array_filter(array_map(
            fn ($v) => is_numeric($v) ? (int) $v : null,
            [$brand['logo_media_id'] ?? null, $brand['logo_dark_media_id'] ?? null, $brand['favicon_media_id'] ?? null, $brand['og_media_id'] ?? null],
        )));
        $media = $ids !== []
            ? Media::query()->with('variants')->whereIn('id', $ids)->where('visibility', 'public')->get()->keyBy('id')
            : collect();

        $mediaUrl = function (mixed $id) use ($media): ?string {
            $item = is_numeric($id) ? $media->get((int) $id) : null;

            return $item instanceof Media ? $item->url() : null;
        };

        $ogMedia = is_numeric($brand['og_media_id'] ?? null) ? $media->get((int) $brand['og_media_id']) : null;

        $hasCategories = ProductCategory::query()->published()->exists();
        $hasShowcase = ShowcaseProject::query()->published()->exists();

        $navigation = array_values(array_filter([
            $hasCategories ? ['label' => 'Catálogo', 'href' => '/catalogo'] : null,
            $hasShowcase ? ['label' => 'Inspiración', 'href' => '/inspiracion'] : null,
            ['label' => 'Cómo funciona', 'href' => '/#como-funciona'],
            ['label' => 'Preguntas frecuentes', 'href' => '/preguntas-frecuentes'],
        ]));

        return [
            'name' => self::name(),
            'tagline' => $this->clean($brand['tagline'] ?? null),
            'logoUrl' => $mediaUrl($brand['logo_media_id'] ?? null),
            'logoDarkUrl' => $mediaUrl($brand['logo_dark_media_id'] ?? null),
            'faviconUrl' => $mediaUrl($brand['favicon_media_id'] ?? null),
            'ogImageUrl' => $ogMedia instanceof Media ? ResponsiveImage::largeUrl($ogMedia) : null,
            'contact' => $this->contact($contact),
            'social' => SocialLink::query()->visible()->get()->map(fn (SocialLink $link) => [
                'platform' => $link->platform,
                'label' => self::platformLabel($link->platform),
                'username' => $link->username,
                'url' => $link->url,
                'newTab' => $link->open_new_tab,
            ])->values()->all(),
            'footerPages' => Page::query()->published()->where('show_in_footer', true)->orderBy('title')->get(['slug', 'title'])
                ->map(fn (Page $page) => ['title' => $page->title, 'href' => '/legal/'.$page->slug])->values()->all(),
            'navigation' => $navigation,
            'categories' => ProductCategory::query()->published()->ordered()->limit(8)->get(['name', 'slug'])
                ->map(fn (ProductCategory $c) => ['label' => $c->name, 'href' => '/catalogo/'.$c->slug])->values()->all(),
            'footer' => [
                'text' => $this->clean($footer['text'] ?? null),
                'legalName' => $this->clean($footer['legal_name'] ?? null),
            ],
            'cta' => ['label' => 'Solicita una asesoría', 'href' => '/solicitar'],
        ];
    }

    /**
     * Solo los datos de contacto capturados; los vacíos no se muestran.
     *
     * @param  array<string, mixed>  $contact
     * @return array<string, string>
     */
    private function contact(array $contact): array
    {
        $out = [];
        foreach (['phone', 'whatsapp', 'email', 'address', 'hours'] as $key) {
            $value = $this->clean($contact[$key] ?? null);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        if (isset($out['phone'])) {
            $out['phoneHref'] = 'tel:'.preg_replace('/[^\d+]/', '', $out['phone']);
        }
        if (isset($out['whatsapp'])) {
            $digits = preg_replace('/\D/', '', $out['whatsapp']) ?? '';
            if (strlen($digits) === 10) {
                $digits = '52'.$digits;
            }
            $out['whatsappHref'] = 'https://wa.me/'.$digits;
        }
        if (isset($out['email'])) {
            $out['emailHref'] = 'mailto:'.$out['email'];
        }

        return $out;
    }

    private function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function platformLabel(string $platform): string
    {
        return match ($platform) {
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'linkedin' => 'LinkedIn',
            'pinterest' => 'Pinterest',
            'whatsapp' => 'WhatsApp',
            default => 'Sitio externo',
        };
    }
}
