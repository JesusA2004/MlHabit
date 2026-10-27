<?php

namespace App\Enums;

/**
 * Variantes derivadas de un archivo. Las imágenes generan anchos
 * responsivos en WebP (y AVIF cuando GD lo soporta) para servir con
 * srcset; el original nunca se envía al sitio público si hay variantes.
 */
enum MediaVariantType: string
{
    /** WebP 480 px (miniatura de biblioteca y srcset más pequeño). */
    case Thumbnail = 'thumbnail';
    case Webp960 = 'webp_960';
    case Webp1600 = 'webp_1600';
    case Webp2400 = 'webp_2400';
    case Avif480 = 'avif_480';
    case Avif960 = 'avif_960';
    case Avif1600 = 'avif_1600';
    case Avif2400 = 'avif_2400';

    /** @deprecated Variantes del pipeline anterior (tamaño original). */
    case OptimizedWebp = 'optimized_webp';
    /** @deprecated */
    case OptimizedAvif = 'optimized_avif';

    case Poster = 'poster';
    case Preview = 'preview';

    /** Anchos responsivos objetivo. */
    public const WIDTHS = [480, 960, 1600, 2400];

    public static function webp(int $width): self
    {
        return match ($width) {
            480 => self::Thumbnail,
            960 => self::Webp960,
            1600 => self::Webp1600,
            default => self::Webp2400,
        };
    }

    public static function avif(int $width): self
    {
        return match ($width) {
            480 => self::Avif480,
            960 => self::Avif960,
            1600 => self::Avif1600,
            default => self::Avif2400,
        };
    }

    public function format(): ?string
    {
        return match ($this) {
            self::Thumbnail, self::Webp960, self::Webp1600, self::Webp2400, self::OptimizedWebp => 'webp',
            self::Avif480, self::Avif960, self::Avif1600, self::Avif2400, self::OptimizedAvif => 'avif',
            default => null,
        };
    }

    public function isResponsive(): bool
    {
        return in_array($this, [
            self::Thumbnail, self::Webp960, self::Webp1600, self::Webp2400,
            self::Avif480, self::Avif960, self::Avif1600, self::Avif2400,
        ], true);
    }
}
