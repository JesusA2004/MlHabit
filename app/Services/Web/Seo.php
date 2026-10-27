<?php

namespace App\Services\Web;

use App\Models\Faq;
use App\Models\Product;
use App\Support\Html;
use App\Support\ResponsiveImage;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Metadatos SEO para las páginas públicas (prop `seo`): título,
 * descripción, URL canónica, Open Graph/Twitter y JSON-LD (schema.org).
 * Solo se publican datos que existen; nada inventado.
 */
class Seo
{
    public function __construct(private readonly PublicSite $site) {}

    /**
     * @param  list<array<string, mixed>>  $jsonLd
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
    ): array {
        $settings = Settings::group('seo');
        $site = $this->site->site();
        $name = PublicSite::name();

        $defaultTitle = $this->stripBrand((string) ($settings['title'] ?? ''), $name);
        $title = trim((string) ($title ?: $defaultTitle));
        $description = Html::text($description ?: (string) ($settings['description'] ?? ''), 300);

        $canonical ??= url()->current();
        $image ??= is_string($site['ogImageUrl'] ?? null) ? $site['ogImageUrl'] : null;
        $twitter = is_string($settings['twitter_handle'] ?? null) && $settings['twitter_handle'] !== ''
            ? '@'.ltrim($settings['twitter_handle'], '@')
            : null;

        $graph = array_values(array_filter($jsonLd));

        return [
            'title' => $title,
            'fullTitle' => $title !== '' ? "{$title} — {$name}" : $name,
            'description' => $description,
            'canonical' => $canonical,
            'image' => $image,
            'type' => $type,
            'siteName' => $name,
            'locale' => 'es_MX',
            'twitter' => $twitter,
            'robots' => $noindex ? 'noindex, nofollow' : 'index, follow',
            'jsonLd' => $graph === [] ? null : json_encode(
                ['@context' => 'https://schema.org', '@graph' => $graph],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
            ),
        ];
    }

    /**
     * Organización / negocio local con los datos de contacto capturados.
     *
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        $site = $this->site->site();
        $contact = is_array($site['contact'] ?? null) ? $site['contact'] : [];

        $sameAs = array_values(array_map(fn ($s) => $s['url'], is_array($site['social'] ?? null) ? $site['social'] : []));

        return array_filter([
            '@type' => 'HomeAndConstructionBusiness',
            '@id' => url('/').'#organizacion',
            'name' => $site['name'],
            'description' => $site['tagline'] ?? null,
            'url' => url('/'),
            'logo' => isset($site['logoUrl']) ? url((string) $site['logoUrl']) : null,
            'image' => $site['ogImageUrl'] ?? null,
            'telephone' => $contact['phone'] ?? null,
            'email' => $contact['email'] ?? null,
            'address' => isset($contact['address']) ? ['@type' => 'PostalAddress', 'streetAddress' => $contact['address'], 'addressCountry' => 'MX'] : null,
            'sameAs' => $sameAs !== [] ? $sameAs : null,
        ], fn ($v) => $v !== null);
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
        $image = ResponsiveImage::largeUrl($product->cover);
        $price = $product->displayPrice();

        return array_filter([
            '@type' => 'Product',
            'name' => $product->name,
            'description' => Html::text($product->summary ?: $product->description, 300) ?: null,
            'image' => $image,
            'category' => $product->category->name,
            'url' => $product->publicUrl(),
            'brand' => ['@type' => 'Brand', 'name' => PublicSite::name()],
            'offers' => $price !== null ? array_filter([
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'MXN',
                'lowPrice' => $price['from'],
                'highPrice' => $price['to'],
                'availability' => 'https://schema.org/MadeToOrder',
            ]) : null,
        ], fn ($v) => $v !== null);
    }

    private function stripBrand(string $title, string $name): string
    {
        $quoted = preg_quote($name, '/');
        $title = preg_replace('/^\s*'.$quoted.'\s*[—\-|:]\s*/u', '', $title) ?? $title;
        $title = preg_replace('/\s*[—\-|:]\s*'.$quoted.'\s*$/u', '', $title) ?? $title;

        return trim($title);
    }
}
