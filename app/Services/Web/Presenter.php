<?php

namespace App\Services\Web;

use App\Enums\VisualOrigin;
use App\Models\Faq;
use App\Models\Finish;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Models\Testimonial;
use App\Support\ResponsiveImage;

/**
 * Arreglos explícitos para las páginas públicas (nunca modelos completos).
 * Las relaciones deben venir precargadas (cover.variants, media.variants…).
 */
class Presenter
{
    /** Tamaños `sizes` habituales. */
    public const SIZES_CARD = '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw';

    public const SIZES_WIDE = '(min-width: 1024px) 60vw, 100vw';

    public const SIZES_FULL = '100vw';

    /** @return array<string, mixed> */
    public function categoryCard(ProductCategory $category, string $sizes = self::SIZES_CARD): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'tagline' => $category->tagline,
            'href' => '/catalogo/'.$category->slug,
            'isFeatured' => $category->is_featured,
            'image' => ResponsiveImage::for($category->cover, $category->name, $sizes),
            'productsCount' => array_key_exists('products_count', $category->getAttributes()) ? (int) $category->getAttributes()['products_count'] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function productCard(Product $product, string $sizes = self::SIZES_CARD): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'summary' => $product->summary,
            'href' => '/catalogo/'.$product->category->slug.'/'.$product->slug,
            'category' => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'image' => ResponsiveImage::for($product->cover, $product->name, $sizes),
            'price' => $product->displayPrice(),
        ];
    }

    /** @return array<string, mixed> */
    public function productDetail(Product $product): array
    {
        return [
            ...$this->productCard($product, self::SIZES_WIDE),
            'description' => $product->description,
            'benefits' => $this->editorialList($product->benefits),
            'features' => $this->editorialList($product->features),
            'gallery' => $this->gallery($product->media),
            'finishes' => $product->finishes->where('is_active', true)->values()->map(fn (Finish $f) => $this->finish($f))->all(),
            'tags' => $product->tags->map(fn ($t) => ['name' => $t->name, 'slug' => $t->slug])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function finish(Finish $finish): array
    {
        return [
            'id' => $finish->id,
            'name' => $finish->name,
            'material' => $finish->material,
            'colorHex' => $finish->color_hex,
            'description' => $finish->description,
            'image' => ResponsiveImage::for($finish->media, $finish->name, '96px'),
        ];
    }

    /** @return array<string, mixed> */
    public function showcaseCard(ShowcaseProject $project, string $sizes = self::SIZES_CARD): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'slug' => $project->slug,
            'summary' => $project->summary,
            'href' => '/inspiracion/'.$project->slug,
            'origin' => $project->origin->value,
            'originLabel' => $project->origin->label(),
            'locationLabel' => $project->location_label,
            'category' => $project->category !== null ? ['name' => $project->category->name, 'slug' => $project->category->slug] : null,
            'image' => ResponsiveImage::for($project->cover, $project->title, $sizes),
        ];
    }

    /** @return array<string, mixed> */
    public function showcaseDetail(ShowcaseProject $project): array
    {
        return [
            ...$this->showcaseCard($project, self::SIZES_FULL),
            'story' => $project->story,
            'gallery' => $this->gallery($project->media, $project->origin),
        ];
    }

    /** @return array<string, mixed> */
    public function faq(Faq $faq): array
    {
        return [
            'id' => $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'topic' => $faq->topic->value,
            'topicLabel' => $faq->topic->label(),
        ];
    }

    /** @return array<string, mixed> */
    public function testimonial(Testimonial $testimonial): array
    {
        return [
            'id' => $testimonial->id,
            'authorName' => $testimonial->author_name,
            'authorContext' => $testimonial->author_context,
            'quote' => $testimonial->quote,
            'image' => ResponsiveImage::for($testimonial->media, $testimonial->author_name, '96px'),
            'project' => $testimonial->showcaseProject !== null && $testimonial->showcaseProject->status->value === 'published'
                ? ['title' => $testimonial->showcaseProject->title, 'href' => '/inspiracion/'.$testimonial->showcaseProject->slug]
                : null,
        ];
    }

    /**
     * Galería: cada imagen con su etiqueta de origen. Si la imagen no
     * declara origen, hereda el del proyecto (si se indica).
     *
     * @param  iterable<Media>  $media
     * @return list<array<string, mixed>>
     */
    public function gallery(iterable $media, ?VisualOrigin $fallback = null): array
    {
        $out = [];
        foreach ($media as $item) {
            $image = ResponsiveImage::for($item, null, self::SIZES_WIDE);
            if ($image === null) {
                continue;
            }
            if ($image['origin'] === null && $fallback !== null) {
                $image['origin'] = $fallback->value;
                $image['originLabel'] = $fallback->label();
            }
            $caption = $item->getRelationValue('pivot')?->getAttribute('caption');
            $out[] = [...$image, 'id' => $item->id, 'caption' => is_string($caption) ? $caption : null];
        }

        return $out;
    }

    /**
     * @return list<array{title: string, body: string|null}>
     */
    public function editorialList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if (is_array($item) && is_string($item['title'] ?? null) && trim($item['title']) !== '') {
                $out[] = ['title' => trim($item['title']), 'body' => is_string($item['body'] ?? null) && trim($item['body']) !== '' ? trim($item['body']) : null];
            }
        }

        return $out;
    }
}
