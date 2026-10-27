<?php

namespace App\Services\Web;

use App\Models\Faq;
use App\Models\Media;
use App\Models\Product;
use App\Support\Html;
use App\Support\ResponsiveImage;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Metadatos SEO para las páginas públicas (prop `seo`): título,
 * descripción, URL canónica, Open Graph/Twitter y JSON-LD (schema.org).
 *
 * Además de los datos sueltos, `head` trae las etiquetas ya escritas como
 * HTML. `app.blade.php` las imprime en el HTML inicial (así WhatsApp,
 * Facebook, LinkedIn, X o Google las leen sin ejecutar JavaScript) y el
 * cliente de Inertia las mantiene al navegar (`serverHead` en app.ts),
 * usando la misma clave `data-inertia` para no duplicarlas.
 *
 * Solo se publican datos que existen; nada inventado.
 */
class Seo
{
    public const TITLE_MAX = 60;

    public const DESCRIPTION_MAX = 155;

    public const ROBOTS_INDEX = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

    public const ROBOTS_NOINDEX = 'noindex, nofollow';

    public function __construct(
        private readonly PublicSite $site,
        private readonly OgImage $og,
    ) {}

    /**
     * @param  list<array<string, mixed>|null>  $jsonLd
     * @param  array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}|null  $og  tarjeta OG generada (OgImage); por defecto la de portada
     * @param  array<string, mixed>|null  $preload  imagen del hero (ResponsiveImage::for) para precargar (LCP)
     * @return array<string, mixed>
     */
    public function page(
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        string $type = 'website',
        array $jsonLd = [],
        ?string $canonical = null,
        bool $noindex = false,
        ?array $og = null,
        ?array $preload = null,
    ): array {
        $settings = Settings::group('seo');
        $name = PublicSite::name();

        $defaultTitle = $this->stripBrand((string) ($settings['title'] ?? ''), $name);
        $title = trim((string) ($title ?: $defaultTitle));
        $description = $this->description($description ?: (string) ($settings['description'] ?? ''));
        if ($description === '') {
            $description = $this->description((string) Settings::defaults()['seo']['description']);
        }

        $canonical = $this->canonical($canonical ?? url()->current());

        if ($image !== null) {
            $imageMeta = ['url' => $image, 'width' => null, 'height' => null, 'type' => null, 'alt' => $title !== '' ? $title : $name];
        } else {
            $imageMeta = $this->og->meta($og ?? $this->og->home());
        }

        $twitter = is_string($settings['twitter_handle'] ?? null) && $settings['twitter_handle'] !== ''
            ? '@'.ltrim($settings['twitter_handle'], '@')
            : null;

        $graph = array_values(array_filter($jsonLd));
        $json = $graph === [] ? null : json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        $seo = [
            'title' => $title,
            'fullTitle' => $this->fullTitle($title, $name),
            'description' => $description,
            'canonical' => $canonical,
            'image' => $imageMeta['url'],
            'imageWidth' => $imageMeta['width'],
            'imageHeight' => $imageMeta['height'],
            'imageType' => $imageMeta['type'],
            'imageAlt' => $imageMeta['alt'],
            'type' => $type,
            'siteName' => $name,
            'locale' => 'es_MX',
            'twitter' => $twitter,
            'robots' => $noindex ? self::ROBOTS_NOINDEX : self::ROBOTS_INDEX,
            'jsonLd' => $json === false ? null : $json,
        ];

        $seo['head'] = $this->tags($seo, $preload);

        return $seo;
    }

    /**
     * Páginas transaccionales (gracias, pagos, citas…): título y noindex,
     * sin datos estructurados.
     *
     * @return array<string, mixed>
     */
    public function private(string $title, ?string $description = null): array
    {
        return $this->page($title, $description, noindex: true, canonical: url()->current());
    }

    /**
     * Etiquetas de <head> listas para imprimir. Cada una lleva
     * `data-inertia` con una clave estable (equivale a `head-key`).
     *
     * @param  array<string, mixed>  $seo
     * @param  array<string, mixed>|null  $preload
     * @return list<string>
     */
    public function tags(array $seo, ?array $preload = null): array
    {
        $meta = fn (string $attr, string $key, mixed $content): ?string => $content === null || $content === ''
            ? null
            : '<meta '.$attr.'="'.e($key).'" content="'.e((string) $content).'" data-inertia="'.e($key).'">';

        $tags = [
            $meta('name', 'description', $seo['description']),
            $meta('name', 'robots', $seo['robots']),
            '<link rel="canonical" href="'.e($seo['canonical']).'" data-inertia="canonical">',
            '<link rel="alternate" hreflang="es-MX" href="'.e($seo['canonical']).'" data-inertia="hreflang-es-mx">',
            '<link rel="alternate" hreflang="x-default" href="'.e($seo['canonical']).'" data-inertia="hreflang-default">',
            $meta('property', 'og:type', $seo['type']),
            $meta('property', 'og:site_name', $seo['siteName']),
            $meta('property', 'og:locale', $seo['locale']),
            $meta('property', 'og:url', $seo['canonical']),
            $meta('property', 'og:title', $seo['fullTitle']),
            $meta('property', 'og:description', $seo['description']),
            $meta('property', 'og:image', $seo['image']),
            $meta('property', 'og:image:secure_url', is_string($seo['image']) && str_starts_with($seo['image'], 'https://') ? $seo['image'] : null),
            $meta('property', 'og:image:type', $seo['imageType']),
            $meta('property', 'og:image:width', $seo['imageWidth']),
            $meta('property', 'og:image:height', $seo['imageHeight']),
            $meta('property', 'og:image:alt', $seo['imageAlt']),
            $meta('name', 'twitter:card', $seo['image'] ? 'summary_large_image' : 'summary'),
            $meta('name', 'twitter:title', $seo['fullTitle']),
            $meta('name', 'twitter:description', $seo['description']),
            $meta('name', 'twitter:image', $seo['image']),
            $meta('name', 'twitter:image:alt', $seo['imageAlt']),
            $meta('name', 'twitter:site', $seo['twitter']),
        ];

        if (is_array($preload) && is_string($preload['src'] ?? null)) {
            $sources = is_array($preload['sources'] ?? null) ? $preload['sources'] : [];
            $avif = collect($sources)->firstWhere('type', 'image/avif');
            $srcset = is_array($avif) ? ($avif['srcset'] ?? null) : ($preload['srcset'] ?? null);
            $tags[] = '<link rel="preload" as="image" href="'.e($preload['src']).'"'
                .(is_string($srcset) && $srcset !== '' ? ' imagesrcset="'.e($srcset).'"' : '')
                .(is_string($preload['sizes'] ?? null) ? ' imagesizes="'.e($preload['sizes']).'"' : '')
                .(is_array($avif) ? ' type="image/avif"' : '')
                .' fetchpriority="high" data-inertia="preload-hero">';
        }

        if (is_string($seo['jsonLd'] ?? null)) {
            $tags[] = '<script type="application/ld+json" data-inertia="jsonld">'.$seo['jsonLd'].'</script>';
        }

        return array_values(array_filter($tags));
    }

    /**
     * Organización (marca) con logo, redes y punto de contacto.
     *
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        $site = $this->site->site();
        $contact = is_array($site['contact'] ?? null) ? $site['contact'] : [];
        $sameAs = $this->sameAs($site);

        $contactPoint = array_filter([
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'telephone' => $contact['phone'] ?? ($contact['whatsapp'] ?? null),
            'email' => $contact['email'] ?? null,
            'areaServed' => 'MX',
            'availableLanguage' => ['es'],
        ]);
        $hasContact = isset($contactPoint['telephone']) || isset($contactPoint['email']);

        return array_filter([
            '@type' => 'Organization',
            '@id' => url('/').'#organizacion',
            'name' => $site['name'],
            'description' => $site['tagline'] ?? null,
            'url' => url('/'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => isset($site['logoUrl']) ? url((string) $site['logoUrl']) : url('/icons/icon-512.png'),
            ],
            'image' => $this->og->meta($this->og->home())['url'],
            'email' => $contact['email'] ?? null,
            'telephone' => $contact['phone'] ?? null,
            'contactPoint' => $hasContact ? [$contactPoint] : null,
            'sameAs' => $sameAs !== [] ? $sameAs : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Negocio local: solo si hay dirección capturada (no se inventan
     * domicilio ni horario).
     *
     * @return array<string, mixed>|null
     */
    public function localBusiness(): ?array
    {
        $site = $this->site->site();
        $contact = is_array($site['contact'] ?? null) ? $site['contact'] : [];

        if (! isset($contact['address'])) {
            return null;
        }

        return array_filter([
            '@type' => 'HomeAndConstructionBusiness',
            '@id' => url('/').'#negocio',
            'name' => $site['name'],
            'url' => url('/'),
            'image' => $this->og->meta($this->og->home())['url'],
            'telephone' => $contact['phone'] ?? null,
            'email' => $contact['email'] ?? null,
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => $contact['address'], 'addressCountry' => 'MX'],
            'parentOrganization' => ['@id' => url('/').'#organizacion'],
            'sameAs' => ($sameAs = $this->sameAs($site)) !== [] ? $sameAs : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        // Sin SearchAction: el sitio no tiene buscador propio.
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#sitio',
            'name' => PublicSite::name(),
            'url' => url('/'),
            'inLanguage' => 'es-MX',
            'publisher' => ['@id' => url('/').'#organizacion'],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items  [nombre, url]
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item[0],
                'item' => url($item[1]),
            ], $items, array_keys($items)),
        ];
    }

    /**
     * @param  Collection<int, Faq>  $faqs
     * @return array<string, mixed>|null
     */
    public function faqPage(Collection $faqs): ?array
    {
        if ($faqs->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn (Faq $faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => Html::text($faq->answer)],
            ])->values()->all(),
        ];
    }

    /**
     * Producto: sin `offers` salvo que el precio se muestre públicamente.
     *
     * @return array<string, mixed>
     */
    public function product(Product $product): array
    {
        $images = array_values(array_unique(array_filter([
            ResponsiveImage::largeUrl($product->cover),
            ...($product->relationLoaded('media') ? $product->media->map(fn (Media $m) => ResponsiveImage::largeUrl($m))->all() : []),
        ])));
        $price = $product->displayPrice();

        return array_filter([
            '@type' => 'Product',
            '@id' => $product->publicUrl().'#producto',
            'name' => $product->name,
            'description' => Html::text($product->summary ?: $product->description, 300) ?: null,
            'image' => $images !== [] ? $images : null,
            'category' => $product->category->name,
            'url' => $product->publicUrl(),
            'brand' => ['@type' => 'Brand', 'name' => PublicSite::name()],
            'manufacturer' => ['@id' => url('/').'#organizacion'],
            'offers' => $price !== null ? array_filter([
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'MXN',
                'lowPrice' => $price['from'],
                'highPrice' => $price['to'],
                'availability' => 'https://schema.org/MadeToOrder',
            ]) : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Galería de imágenes (proyectos de inspiración, fichas con fotos).
     *
     * @param  iterable<Media|null>  $media
     * @return array<string, mixed>|null
     */
    public function imageGallery(string $name, string $url, iterable $media): ?array
    {
        $images = [];
        foreach ($media as $item) {
            $src = ResponsiveImage::largeUrl($item);
            if ($item === null || $src === null) {
                continue;
            }
            $images[$src] = array_filter([
                '@type' => 'ImageObject',
                'contentUrl' => $src,
                'caption' => $item->alt_text ?: ($item->title ?: null),
                'width' => $item->width,
                'height' => $item->height,
            ], fn ($v) => $v !== null);
        }

        if ($images === []) {
            return null;
        }

        return [
            '@type' => 'ImageGallery',
            'name' => $name,
            'url' => $url,
            'image' => array_values($images),
        ];
    }

    /**
     * "Título | ML Habitat" en ≤ 60 caracteres: primero se quitan los
     * segmentos finales "· Categoría"; si aún no cabe, se corta por palabra.
     */
    public function fullTitle(string $title, ?string $name = null): string
    {
        $name ??= PublicSite::name();
        $title = trim($title);
        if ($title === '') {
            return $name;
        }

        $suffix = ' | '.$name;
        $max = self::TITLE_MAX - mb_strlen($suffix);

        if (mb_strlen($title) > $max) {
            $parts = explode(' · ', $title);
            while (count($parts) > 1 && mb_strlen(implode(' · ', $parts)) > $max) {
                array_pop($parts);
            }
            $title = implode(' · ', $parts);
        }

        if (mb_strlen($title) > $max) {
            $title = $this->cutWords($title, $max);
        }

        return $title.$suffix;
    }

    /** Texto plano de ≤ 155 caracteres, cortado por palabra. */
    public function description(?string $html): string
    {
        $text = Html::text($html);
        if (mb_strlen($text) <= self::DESCRIPTION_MAX) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::DESCRIPTION_MAX - 1);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > 100) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, ' ,.;:—-').'…';
    }

    /** Canónica siempre con el host y esquema de APP_URL. */
    public function canonical(string $url): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if ($base === '') {
            return $url;
        }

        return $base.($path === '' ? '/' : $path).(is_string($query) && $query !== '' ? '?'.$query : '');
    }

    private function cutWords(string $text, int $max): string
    {
        $stop = ['a', 'al', 'con', 'de', 'del', 'el', 'en', 'la', 'las', 'los', 'para', 'por', 'y', 'e', 'o', 'u', 'un', 'una'];
        $words = explode(' ', mb_substr($text, 0, $max + 1));
        if (mb_strlen($text) > $max) {
            array_pop($words);
        }
        while ($words !== [] && in_array(mb_strtolower(rtrim((string) end($words), ',;:')), $stop, true)) {
            array_pop($words);
        }

        $out = rtrim(implode(' ', $words), ' ,;:—-·');

        return $out !== '' ? $out : mb_substr($text, 0, $max);
    }

    /**
     * @param  array<string, mixed>  $site
     * @return list<string>
     */
    private function sameAs(array $site): array
    {
        $social = is_array($site['social'] ?? null) ? $site['social'] : [];

        return array_values(array_filter(array_map(
            fn ($s) => is_array($s) && is_string($s['url'] ?? null) && str_starts_with($s['url'], 'http') && ($s['platform'] ?? '') !== 'whatsapp' ? $s['url'] : null,
            $social,
        )));
    }

    private function stripBrand(string $title, string $name): string
    {
        $quoted = preg_quote($name, '/');
        $title = preg_replace('/^\s*'.$quoted.'\s*[—\-|:·]\s*/u', '', $title) ?? $title;
        $title = preg_replace('/\s*[—\-|:·]\s*'.$quoted.'\s*$/u', '', $title) ?? $title;

        return trim($title);
    }
}
