<?php

namespace App\Support;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\MediaVariantType;
use App\Enums\VisualOrigin;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;

/**
 * Convierte un Media en los datos de una imagen responsiva para el sitio
 * público: `src` (WebP mediano), `srcset` WebP, `sources` (AVIF/WebP para
 * <picture>), dimensiones intrínsecas (evitan CLS), alt y color de
 * marcador. Nunca entrega el original si ya existen variantes.
 *
 * Requiere `variants` precargado para evitar consultas N+1.
 */
final class ResponsiveImage
{
    /**
     * @return array{src: string, srcset: string|null, sources: list<array{type: string, srcset: string}>, sizes: string, width: int|null, height: int|null, alt: string, origin: string|null, originLabel: string|null, placeholder: string|null}|null
     */
    public static function for(?Media $media, ?string $alt = null, string $sizes = '100vw'): ?array
    {
        if ($media === null || $media->type !== MediaType::Image || $media->status !== MediaStatus::Ready || $media->isPrivate()) {
            return null;
        }

        $variants = $media->relationLoaded('variants') ? $media->variants : $media->variants()->get();

        $webp = [];
        $avif = [];
        foreach ($variants as $variant) {
            $type = MediaVariantType::tryFrom($variant->type);
            if ($type === null || ! $type->isResponsive() || $variant->width === null) {
                continue;
            }
            $url = self::variantUrl($variant);
            if ($type->format() === 'webp') {
                $webp[$variant->width] = $url;
            } else {
                $avif[$variant->width] = $url;
            }
        }
        ksort($webp);
        ksort($avif);

        $src = $webp !== [] ? self::pickDefault($webp) : (string) $media->url();
        $origin = $media->origin !== null ? VisualOrigin::tryFrom($media->origin) : null;

        $sources = [];
        if ($avif !== []) {
            $sources[] = ['type' => 'image/avif', 'srcset' => self::srcset($avif)];
        }
        if ($webp !== []) {
            $sources[] = ['type' => 'image/webp', 'srcset' => self::srcset($webp)];
        }

        $color = is_array($media->metadata) ? ($media->metadata['color'] ?? null) : null;

        return [
            'src' => $src,
            'srcset' => $webp !== [] ? self::srcset($webp) : null,
            'sources' => $sources,
            'sizes' => $sizes,
            'width' => $media->width,
            'height' => $media->height,
            'alt' => trim((string) ($alt ?? $media->alt_text ?? $media->title ?? '')),
            'origin' => $origin?->value,
            'originLabel' => $origin?->label(),
            'placeholder' => is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : null,
        ];
    }

    /** URL de la variante más pequeña (miniaturas de panel, OG si no hay otra). */
    public static function thumbnailUrl(?Media $media): ?string
    {
        if ($media === null || $media->type !== MediaType::Image) {
            return $media?->loadMissing('variants')->variantUrl(MediaVariantType::Poster->value);
        }

        return $media->loadMissing('variants')->variantUrl(MediaVariantType::Thumbnail->value) ?? $media->url();
    }

    /** URL de 1600 px (Open Graph, JSON-LD). */
    public static function largeUrl(?Media $media): ?string
    {
        if ($media === null || $media->type !== MediaType::Image || $media->isPrivate()) {
            return null;
        }

        $media->loadMissing('variants');
        $url = $media->variantUrl(MediaVariantType::Webp1600->value)
            ?? $media->variantUrl(MediaVariantType::Webp960->value)
            ?? $media->url();

        return $url !== null ? url($url) : null;
    }

    /** @param array<int, string> $set */
    private static function srcset(array $set): string
    {
        return implode(', ', array_map(fn ($w, $url) => "{$url} {$w}w", array_keys($set), $set));
    }

    /** @param array<int, string> $set */
    private static function pickDefault(array $set): string
    {
        foreach ($set as $width => $url) {
            if ($width >= 1600) {
                return $url;
            }
        }

        return (string) end($set);
    }

    private static function variantUrl(MediaVariant $variant): string
    {
        return Storage::disk($variant->disk)->url($variant->path);
    }
}
