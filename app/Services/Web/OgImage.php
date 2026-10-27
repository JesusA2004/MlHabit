<?php

namespace App\Services\Web;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\MediaVariantType;
use App\Enums\SiteSectionType;
use App\Models\Media;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Models\SiteSection;
use App\Support\Settings;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imágenes de vista previa (Open Graph / Twitter) de 1200×630 generadas en
 * el servidor con GD y las fuentes de marca (resources/fonts, OFL).
 *
 * Cada imagen se describe con un "spec" (tipo, slug, título, antetítulo,
 * fotografía). El nombre del archivo lleva un hash del contenido: si cambia
 * el título, la portada o la marca, cambia la URL (`?v=`) y se genera una
 * imagen nueva; la anterior se borra. Así WhatsApp/Facebook nunca muestran
 * una vista previa vieja con la URL nueva.
 *
 * Diseños:
 *  - `split`: lino + fotografía con esquinas redondeadas (producto, categoría, proyecto).
 *  - `cover`: fotografía a sangre con degradado espresso (portada con hero).
 *  - `type`: tarjeta tipográfica con el abanico de acabados (sin fotografía).
 *  - `override`: imagen elegida por el administrador (`brand.og_media_id`), solo recortada.
 */
final class OgImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public const TYPES = ['home', 'category', 'product', 'showcase', 'page'];

    /** Súbelo al cambiar el diseño para regenerar todas las imágenes. */
    private const VERSION = 3;

    /** Supermuestreo: se dibuja al doble y se reduce (bordes suaves). */
    private const S = 2;

    private const DISK = 'local';

    private const DIR = 'og';

    /** Páginas fijas con tarjeta propia (`/og/page/{slug}.png`). */
    private const STATIC_PAGES = [
        'catalogo' => ['Catálogo de muebles a la medida', 'Cocinas, clósets, recámaras y más'],
        'inspiracion' => ['Inspiración: proyectos y propuestas', 'Proyectos entregados y renders'],
        'preguntas-frecuentes' => ['Preguntas frecuentes', 'Proceso, visita, pagos y garantía'],
        'solicitar' => ['Solicita tu propuesta a la medida', 'Asesoría sin compromiso'],
        'asesoria' => ['Agenda una asesoría', 'Asesoría remota'],
    ];

    private const C_LINO = '#F7F5F2';

    private const C_STONE = '#EFEBE5';

    private const C_INK = '#231E1A';

    private const C_MUTED = '#6B635A';

    private const C_OAK = '#B07D4F';

    private const C_OAK_INK = '#8A6A4A';

    private const C_EUCALYPTUS = '#2F5A4C';

    private const C_ESPRESSO = '#2B2420';

    private const C_BORDER = '#E3DDD4';

    // ------------------------------------------------------------------
    // Specs
    // ------------------------------------------------------------------

    /**
     * @return array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}
     */
    public function home(): array
    {
        $name = PublicSite::name();
        $brand = Settings::group('brand');
        $seo = Settings::group('seo');

        $title = $this->stripBrand((string) ($seo['title'] ?? ''), $name);
        if ($title === '') {
            $title = 'Muebles a la medida';
        }

        $override = $this->media($brand['og_media_id'] ?? null);
        if ($override !== null) {
            return $this->spec('home', 'inicio', $title, null, $override, 'override', $name);
        }

        $hero = $this->heroMedia();

        return $this->spec(
            'home',
            'inicio',
            $title,
            'Diseño, fabricación e instalación',
            $hero,
            $hero !== null ? 'cover' : 'type',
            "{$name}: {$title}",
        );
    }

    /**
     * @return array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}
     */
    public function category(ProductCategory $category): array
    {
        $category->loadMissing('cover');
        $media = $this->usable($category->cover);

        return $this->spec(
            'category',
            $category->slug,
            $category->name,
            $category->tagline ?: 'Muebles a la medida',
            $media,
            $media !== null ? 'split' : 'type',
            "{$category->name} a la medida — ".PublicSite::name(),
        );
    }

    /**
     * @return array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}
     */
    public function product(Product $product): array
    {
        $product->loadMissing(['cover', 'category']);
        $media = $this->usable($product->cover);

        return $this->spec(
            'product',
            $product->slug,
            $product->name,
            $product->category->name,
            $media,
            $media !== null ? 'split' : 'type',
            "{$product->name} — {$product->category->name} a la medida de ".PublicSite::name(),
        );
    }

    /**
     * @return array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}
     */
    public function showcase(ShowcaseProject $project): array
    {
        $project->loadMissing(['cover', 'category']);
        $media = $this->usable($project->cover);
        $eyebrow = $project->category !== null && $project->category->is_published
            ? $project->category->name
            : 'Inspiración';

        return $this->spec(
            'showcase',
            $project->slug,
            $project->title,
            $eyebrow,
            $media,
            $media !== null ? 'split' : 'type',
            "{$project->title} — ".PublicSite::name(),
        );
    }

    /**
     * @return array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}
     */
    public function page(string $slug, string $title, ?string $eyebrow = null): array
    {
        return $this->spec('page', $slug, $title, $eyebrow, null, 'type', "{$title} — ".PublicSite::name());
    }

    /**
     * Spec a partir de la URL pública; null si no existe o no está publicado.
     *
     * @return array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}|null
     */
    public function resolve(string $type, string $slug): ?array
    {
        return match ($type) {
            'home' => $slug === 'inicio' ? $this->home() : null,
            'category' => ($c = ProductCategory::query()->published()->where('slug', $slug)->first()) ? $this->category($c) : null,
            'product' => ($p = Product::query()->published()->where('slug', $slug)
                ->whereHas('category', fn ($q) => $q->where('is_published', true))->first()) ? $this->product($p) : null,
            'showcase' => ($s = ShowcaseProject::query()->published()->where('slug', $slug)->first()) ? $this->showcase($s) : null,
            'page' => $this->resolvePage($slug),
            default => null,
        };
    }

    /**
     * Datos para las etiquetas og:image.
     *
     * @param  array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}  $spec
     * @return array{url: string, width: int, height: int, type: string, alt: string}
     */
    public function meta(array $spec): array
    {
        return [
            'url' => url('/og/'.$spec['type'].'/'.$spec['slug'].'.png').'?v='.$this->hash($spec),
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'type' => 'image/png',
            'alt' => $spec['alt'],
        ];
    }

    /**
     * Ruta absoluta del PNG en disco, generándolo si aún no existe.
     *
     * @param  array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}  $spec
     */
    public function ensure(array $spec): string
    {
        $disk = Storage::disk(self::DISK);
        $dir = self::DIR.'/'.$spec['type'];
        $file = $dir.'/'.$spec['slug'].'-'.$this->hash($spec).'.png';

        if (! $disk->exists($file)) {
            $png = $this->render($spec);

            // Borra versiones anteriores de la misma página.
            foreach ($disk->files($dir) as $old) {
                if (str_starts_with(basename($old), $spec['slug'].'-')) {
                    $disk->delete($old);
                }
            }

            $disk->put($file, $png);
        }

        return $disk->path($file);
    }

    /** Elimina todas las imágenes generadas (se regeneran al pedirse). */
    public function clear(): void
    {
        Storage::disk(self::DISK)->deleteDirectory(self::DIR);
    }

    // ------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------

    /**
     * @param  array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}  $spec
     */
    public function render(array $spec): string
    {
        $w = self::WIDTH * self::S;
        $h = self::HEIGHT * self::S;

        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        $photo = $spec['media'] !== null ? $this->loadImage($spec['media']) : null;
        $layout = $photo === null && $spec['layout'] !== 'type' ? 'type' : $spec['layout'];

        match ($layout) {
            'override' => $this->drawOverride($canvas, $photo),
            'cover' => $this->drawCover($canvas, $spec, $photo),
            'split' => $this->drawSplit($canvas, $spec, $photo),
            default => $this->drawTypographic($canvas, $spec),
        };

        if ($photo instanceof GdImage) {
            imagedestroy($photo);
        }

        $out = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($out, $canvas, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, $w, $h);
        imagedestroy($canvas);

        ob_start();
        imagepng($out, null, 8);
        imagedestroy($out);

        return (string) ob_get_clean();
    }

    private function drawOverride(GdImage $canvas, ?GdImage $photo): void
    {
        if ($photo !== null) {
            $this->cover($canvas, $photo, 0, 0, self::WIDTH, self::HEIGHT);
        }
    }

    /**
     * Fotografía a sangre + degradado espresso desde la izquierda.
     *
     * @param  array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}  $spec
     */
    private function drawCover(GdImage $canvas, array $spec, ?GdImage $photo): void
    {
        if ($photo !== null) {
            $this->cover($canvas, $photo, 0, 0, self::WIDTH, self::HEIGHT);
        }

        // Degradado horizontal (legibilidad) + base inferior.
        [$r, $g, $b] = $this->rgb('#1A1613');
        $w = self::WIDTH * self::S;
        $h = self::HEIGHT * self::S;
        for ($x = 0; $x < $w; $x += 2) {
            $t = $x / $w;
            $opacity = max(0.28, 0.9 - 0.95 * $t * $t);
            $alpha = (int) round(127 - 127 * $opacity);
            if ($alpha >= 127) {
                continue;
            }
            imagefilledrectangle($canvas, $x, 0, $x + 1, $h, $this->rgba($canvas, $r, $g, $b, $alpha));
        }
        for ($y = (int) ($h * 0.55); $y < $h; $y += 2) {
            $t = ($y - $h * 0.55) / ($h * 0.45);
            $alpha = (int) round(127 - 127 * 0.55 * $t);
            imagefilledrectangle($canvas, 0, $y, $w, $y + 1, $this->rgba($canvas, $r, $g, $b, $alpha));
        }

        $x = 72;
        $this->brandRow($canvas, $x, 64, true);

        $lines = $this->fitTitle($spec['title'], 640, [76, 68, 60, 54, 48], 3);
        $size = $lines['size'];
        $lineHeight = (int) round($size * 1.06);
        $bottom = self::HEIGHT - 88;
        $y = $bottom - ($lineHeight * (count($lines['lines']) - 1));

        if ($spec['eyebrow'] !== null && $spec['eyebrow'] !== '') {
            $this->swatchRow($canvas, $x, $y - $size - 52, 14);
            $this->text($canvas, 22, $this->font('figtree-semibold'), $x + 96, $y - $size - 37, '#EADFD2', $this->trimTo($spec['eyebrow'], 22, 'figtree-semibold', 560));
        }

        foreach ($lines['lines'] as $i => $line) {
            $this->text($canvas, $size, $this->font('familjen-bold'), $x, $y + $i * $lineHeight, '#FFFFFF', $line, -0.02);
        }

        $this->text($canvas, 22, $this->font('figtree-medium'), self::WIDTH - 72 - $this->textWidth(22, $this->font('figtree-medium'), $this->domain()), self::HEIGHT - 88, '#F4EFE8', $this->domain());
    }

    /**
     * Lino + fotografía enmarcada a la derecha.
     *
     * @param  array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}  $spec
     */
    private function drawSplit(GdImage $canvas, array $spec, ?GdImage $photo): void
    {
        $this->fill($canvas, self::C_LINO);

        // Marco de la fotografía (derecha, a sangre arriba y abajo con aire).
        $px = 548;
        $py = 40;
        $pw = self::WIDTH - $px - 40;
        $ph = self::HEIGHT - 80;
        $radius = 28;

        $this->softShadow($canvas, $px, $py, $pw, $ph, $radius);
        if ($photo !== null) {
            $this->cover($canvas, $photo, $px, $py, $pw, $ph);
        }
        $this->maskCorners($canvas, $px, $py, $pw, $ph, $radius, self::C_LINO);

        $x = 64;
        $this->brandRow($canvas, $x, 60, false);

        $maxWidth = $px - $x - 48;
        $lines = $this->fitTitle($spec['title'], $maxWidth, [66, 60, 54, 48, 44, 40, 36], 4);
        $size = $lines['size'];
        $lineHeight = (int) round($size * 1.08);
        $blockHeight = $lineHeight * count($lines['lines']);
        $eyebrowGap = 46;
        $top = (int) round((self::HEIGHT - $blockHeight - $eyebrowGap) / 2) + 18;

        if ($spec['eyebrow'] !== null && $spec['eyebrow'] !== '') {
            $this->swatchRow($canvas, $x, $top - 16, 14);
            $this->text($canvas, 22, $this->font('figtree-semibold'), $x + 96, $top, self::C_OAK_INK, $this->trimTo($spec['eyebrow'], 22, 'figtree-semibold', $maxWidth - 96));
        }

        foreach ($lines['lines'] as $i => $line) {
            $this->text($canvas, $size, $this->font('familjen-bold'), $x, $top + $eyebrowGap + $size + $i * $lineHeight, self::C_INK, $line, -0.02);
        }

        $this->text($canvas, 22, $this->font('figtree-medium'), $x, self::HEIGHT - 64, self::C_MUTED, $this->domain());
    }

    /**
     * Tarjeta tipográfica con el abanico de acabados.
     *
     * @param  array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}  $spec
     */
    private function drawTypographic(GdImage $canvas, array $spec): void
    {
        $this->fill($canvas, self::C_LINO);

        // Panel de piedra a la derecha con el abanico.
        $this->roundedRect($canvas, 700, 40, self::WIDTH - 740, self::HEIGHT - 80, 28, self::C_STONE);
        $this->fan($canvas, 930, 548, 1.55);
        $this->maskCorners($canvas, 700, 40, self::WIDTH - 740, self::HEIGHT - 80, 28, self::C_LINO, true);

        $x = 64;
        $this->brandRow($canvas, $x, 60, false);

        $maxWidth = 700 - $x - 48;
        $lines = $this->fitTitle($spec['title'], $maxWidth, [72, 66, 60, 54, 48, 44], 4);
        $size = $lines['size'];
        $lineHeight = (int) round($size * 1.06);
        $blockHeight = $lineHeight * count($lines['lines']);
        $top = (int) round((self::HEIGHT - $blockHeight - 46) / 2) + 18;

        if ($spec['eyebrow'] !== null && $spec['eyebrow'] !== '') {
            $this->text($canvas, 22, $this->font('figtree-semibold'), $x, $top, self::C_OAK_INK, $this->trimTo($spec['eyebrow'], 22, 'figtree-semibold', $maxWidth));
        }

        foreach ($lines['lines'] as $i => $line) {
            $this->text($canvas, $size, $this->font('familjen-bold'), $x, $top + 46 + $size + $i * $lineHeight, self::C_INK, $line, -0.02);
        }

        $this->text($canvas, 22, $this->font('figtree-medium'), $x, self::HEIGHT - 64, self::C_MUTED, $this->domain());
    }

    // ------------------------------------------------------------------
    // Piezas de marca
    // ------------------------------------------------------------------

    /** Monograma + "ML Habitat". */
    private function brandRow(GdImage $canvas, int $x, int $y, bool $inverted): void
    {
        $this->monogram($canvas, $x, $y, 52);
        $this->text($canvas, 30, $this->font('familjen-semibold'), $x + 68, $y + 37, $inverted ? '#FFFFFF' : self::C_INK, PublicSite::name(), -0.02);
    }

    /**
     * Monograma ML (mismo trazo que public/favicon.svg, retícula de 32).
     */
    private function monogram(GdImage $canvas, int $x, int $y, int $size): void
    {
        $k = $size / 32;
        $this->roundedRect($canvas, $x, $y, $size, $size, 8 * $k, self::C_OAK);
        // Eucalipto con esquinas superiores redondeadas; deja ver el canto de roble.
        $this->polygon($canvas, $this->roundedRectPoints($x, $y, $size, 26.5 * $k, 8 * $k, [true, true, false, false]), self::C_EUCALYPTUS);

        $points = [[7.5, 21.5], [7.5, 9], [14, 16], [20.5, 9], [20.5, 21.5], [25, 21.5]];
        $path = array_map(fn ($p) => [$x + $p[0] * $k, $y + $p[1] * $k], $points);
        $this->stroke($canvas, $path, 2.6 * $k, '#FFFFFF');
    }

    /** Fila de muestras (lino, piedra, roble, eucalipto, espresso). */
    private function swatchRow(GdImage $canvas, int $x, int $y, int $size): void
    {
        $colors = ['#F3EEE7', '#D9CFC2', self::C_OAK, self::C_EUCALYPTUS, self::C_ESPRESSO];
        foreach ($colors as $i => $color) {
            $cx = $x + $i * ($size + 2);
            $this->roundedRect($canvas, $cx, $y, $size, $size, 4, $color);
            $this->roundedRectOutline($canvas, $cx, $y, $size, $size, 4, self::C_BORDER);
        }
    }

    /**
     * Abanico de acabados (misma geometría que ds/FinishSwatches.vue,
     * escalado `k` y anclado en el pivote `px, py`).
     */
    private function fan(GdImage $canvas, int $px, int $py, float $k): void
    {
        $chips = [
            ['#F3EEE7', '#6B635A', 'Lino', -30, false],
            ['#D9CFC2', '#4A433C', 'Piedra', -18, false],
            [self::C_OAK, '#FFFFFF', 'Roble', -6, true],
            [self::C_EUCALYPTUS, '#E5EEE9', 'Eucalipto', 6, false],
            [self::C_ESPRESSO, '#D9CFC2', 'Espresso', 18, false],
        ];

        // Rectángulo de la muestra relativo al pivote (180, 272) del SVG.
        $rx = (148 - 180) * $k;
        $ry = (40 - 272) * $k;
        $rw = 64 * $k;
        $rh = 252 * $k;
        $rr = 12 * $k;

        foreach ($chips as [$fill, $ink, $name, $angle, $grain]) {
            $base = $this->roundedRectPoints($px + $rx, $py + $ry, $rw, $rh, $rr);

            // Sombra suave: capas desplazadas con poca opacidad.
            foreach ([[14, 120], [9, 116], [5, 112]] as [$dy, $alpha]) {
                $shadow = array_map(fn ($p) => [$p[0], $p[1] + $dy], $base);
                $this->polygon($canvas, $this->rotate($shadow, $px, $py, $angle), '#231E1A', $alpha);
            }

            $this->polygon($canvas, $this->rotate($base, $px, $py, $angle), $fill);

            if ($grain) {
                for ($i = 0; $i < 9; $i++) {
                    $gy = $py + $ry + 24 * $k + $i * 26 * $k;
                    $line = [];
                    for ($t = 0; $t <= 8; $t++) {
                        $gx = $px + $rx + 6 * $k + ($rw - 12 * $k) * $t / 8;
                        $line[] = [$gx, $gy + sin(($t / 8) * M_PI * 2 + $i) * 3 * $k];
                    }
                    $this->stroke($canvas, $this->rotate($line, $px, $py, $angle), 1.1 * $k, self::C_OAK_INK, 96, false);
                }
            }

            // Etiqueta vertical (como en el muestrario).
            [$lx, $ly] = $this->rotate([[$px + (163 - 180) * $k, $py + (160 - 272) * $k]], $px, $py, $angle)[0];
            imagettftext(
                $canvas,
                $this->pt(10 * $k) * self::S,
                -$angle + 90,
                (int) round($lx * self::S),
                (int) round($ly * self::S),
                $this->color($canvas, $ink),
                $this->font('figtree-semibold'),
                $name,
            );
        }

        imagefilledellipse($canvas, $px * self::S, $py * self::S, (int) (14 * $k * self::S), (int) (14 * $k * self::S), $this->color($canvas, '#FFFFFF'));
        imagefilledellipse($canvas, $px * self::S, $py * self::S, (int) (6 * $k * self::S), (int) (6 * $k * self::S), $this->color($canvas, self::C_OAK));
    }

    // ------------------------------------------------------------------
    // Primitivas (coordenadas en px de 1200×630; se escalan por S)
    // ------------------------------------------------------------------

    private function fill(GdImage $canvas, string $hex): void
    {
        imagefilledrectangle($canvas, 0, 0, self::WIDTH * self::S, self::HEIGHT * self::S, $this->color($canvas, $hex));
    }

    private function color(GdImage $canvas, string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        return $this->rgba($canvas, $r, $g, $b, $alpha);
    }

    /** Color con transparencia (0 = opaco, 127 = transparente), valores acotados. */
    private function rgba(GdImage $canvas, int $r, int $g, int $b, int $alpha): int
    {
        return (int) imagecolorallocatealpha(
            $canvas,
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b)),
            max(0, min(127, $alpha)),
        );
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /**
     * @param  array{0: bool, 1: bool, 2: bool, 3: bool}  $corners  sup-izq, sup-der, inf-der, inf-izq
     * @return list<array{0: float, 1: float}>
     */
    private function roundedRectPoints(float $x, float $y, float $w, float $h, float $r, array $corners = [true, true, true, true]): array
    {
        $points = [];
        $centers = [
            [$x + $r, $y + $r, 180, $x, $y],
            [$x + $w - $r, $y + $r, 270, $x + $w, $y],
            [$x + $w - $r, $y + $h - $r, 0, $x + $w, $y + $h],
            [$x + $r, $y + $h - $r, 90, $x, $y + $h],
        ];
        foreach ($centers as $i => [$cx, $cy, $start, $sx, $sy]) {
            if (! $corners[$i]) {
                $points[] = [$sx, $sy];

                continue;
            }
            for ($s = 0; $s <= 10; $s++) {
                $a = deg2rad($start + 90 * $s / 10);
                $points[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
        }

        return $points;
    }

    private function roundedRect(GdImage $canvas, float $x, float $y, float $w, float $h, float $r, string $hex, int $alpha = 0): void
    {
        $this->polygon($canvas, $this->roundedRectPoints($x, $y, $w, $h, $r), $hex, $alpha);
    }

    private function roundedRectOutline(GdImage $canvas, float $x, float $y, float $w, float $h, float $r, string $hex): void
    {
        $points = $this->roundedRectPoints($x, $y, $w, $h, $r);
        $points[] = $points[0];
        $this->stroke($canvas, $points, 1, $hex, 0, false);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private function polygon(GdImage $canvas, array $points, string $hex, int $alpha = 0): void
    {
        $flat = [];
        foreach ($points as [$px, $py]) {
            $flat[] = (int) round($px * self::S);
            $flat[] = (int) round($py * self::S);
        }
        imagefilledpolygon($canvas, $flat, $this->color($canvas, $hex, $alpha));
    }

    /**
     * Trazo grueso con uniones y extremos redondeados.
     *
     * @param  list<array{0: float, 1: float}>  $points
     */
    private function stroke(GdImage $canvas, array $points, float $width, string $hex, int $alpha = 0, bool $round = true): void
    {
        $half = $width / 2;
        $count = count($points);
        for ($i = 0; $i < $count - 1; $i++) {
            [$x1, $y1] = $points[$i];
            [$x2, $y2] = $points[$i + 1];
            $len = hypot($x2 - $x1, $y2 - $y1);
            if ($len <= 0) {
                continue;
            }
            $nx = -($y2 - $y1) / $len * $half;
            $ny = ($x2 - $x1) / $len * $half;
            $this->polygon($canvas, [[$x1 + $nx, $y1 + $ny], [$x2 + $nx, $y2 + $ny], [$x2 - $nx, $y2 - $ny], [$x1 - $nx, $y1 - $ny]], $hex, $alpha);
        }
        if ($round) {
            $d = (int) round($width * self::S);
            foreach ($points as [$px, $py]) {
                imagefilledellipse($canvas, (int) round($px * self::S), (int) round($py * self::S), $d, $d, $this->color($canvas, $hex, $alpha));
            }
        }
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<array{0: float, 1: float}>
     */
    private function rotate(array $points, float $cx, float $cy, float $degrees): array
    {
        $a = deg2rad($degrees);
        $cos = cos($a);
        $sin = sin($a);

        return array_map(fn ($p) => [
            $cx + ($p[0] - $cx) * $cos - ($p[1] - $cy) * $sin,
            $cy + ($p[0] - $cx) * $sin + ($p[1] - $cy) * $cos,
        ], $points);
    }

    /** Sombra difusa aproximada con capas de baja opacidad. */
    private function softShadow(GdImage $canvas, int $x, int $y, int $w, int $h, int $r): void
    {
        foreach ([[18, 124], [12, 122], [7, 119], [3, 116]] as [$spread, $alpha]) {
            $this->roundedRect($canvas, $x - $spread / 2, $y + $spread / 1.5, $w + $spread, $h + $spread / 2, $r + $spread / 2, '#231E1A', $alpha);
        }
    }

    /**
     * Pinta las esquinas de un rectángulo con el color de fondo para
     * redondear lo que haya dentro (fotografía, abanico).
     */
    private function maskCorners(GdImage $canvas, int $x, int $y, int $w, int $h, int $r, string $hex, bool $clipOutside = false): void
    {
        $corners = [
            [$x, $y, $x + $r, $y + $r, 180],
            [$x + $w, $y, $x + $w - $r, $y + $r, 270],
            [$x + $w, $y + $h, $x + $w - $r, $y + $h - $r, 0],
            [$x, $y + $h, $x + $r, $y + $h - $r, 90],
        ];
        foreach ($corners as [$ox, $oy, $cx, $cy, $start]) {
            $points = [[$ox, $oy]];
            for ($s = 0; $s <= 12; $s++) {
                $a = deg2rad($start + 90 * $s / 12);
                $points[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
            $this->polygon($canvas, $points, $hex);
        }

        if ($clipOutside) {
            // Todo lo que el abanico dibujó fuera del panel.
            $c = $this->color($canvas, $hex);
            $s = self::S;
            imagefilledrectangle($canvas, 0, ($y + $h) * $s, self::WIDTH * $s, self::HEIGHT * $s, $c);
            imagefilledrectangle($canvas, 0, 0, self::WIDTH * $s, $y * $s - 1, $c);
            imagefilledrectangle($canvas, ($x + $w) * $s + 1, 0, self::WIDTH * $s, self::HEIGHT * $s, $c);
        }
    }

    /** Copia `src` recortada para cubrir el rectángulo (object-fit: cover). */
    private function cover(GdImage $canvas, GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $target = $w / $h;
        if ($sw / $sh > $target) {
            $cw = (int) round($sh * $target);
            $ch = $sh;
            $cx = (int) round(($sw - $cw) / 2);
            $cy = 0;
        } else {
            $cw = $sw;
            $ch = (int) round($sw / $target);
            $cx = 0;
            $cy = (int) round(($sh - $ch) / 2.4);
        }

        imagecopyresampled($canvas, $src, $x * self::S, $y * self::S, $cx, $cy, $w * self::S, $h * self::S, $cw, $ch);
    }

    private function text(GdImage $canvas, float $px, string $font, float $x, float $y, string $hex, string $text, float $tracking = 0.0): void
    {
        $size = $this->pt($px) * self::S;
        $color = $this->color($canvas, $hex);

        if ($tracking === 0.0) {
            imagettftext($canvas, $size, 0, (int) round($x * self::S), (int) round($y * self::S), $color, $font, $text);

            return;
        }

        // Tracking manual: carácter por carácter.
        $cursor = $x * self::S;
        foreach (mb_str_split($text) as $char) {
            imagettftext($canvas, $size, 0, (int) round($cursor), (int) round($y * self::S), $color, $font, $char);
            $cursor += $this->advance($size, $font, $char) + $tracking * $px * self::S;
        }
    }

    private function textWidth(float $px, string $font, string $text, float $tracking = 0.0): float
    {
        $size = $this->pt($px);
        if ($tracking === 0.0) {
            $box = imagettfbbox($size, 0, $font, $text);

            return $box === false ? 0.0 : (float) ($box[2] - $box[0]);
        }

        $width = 0.0;
        foreach (mb_str_split($text) as $char) {
            $width += $this->advance($size, $font, $char) + $tracking * $px;
        }

        return $width;
    }

    private function advance(float $size, string $font, string $char): float
    {
        // El ancho de "x{char}x" menos "xx" incluye el espaciado lateral real.
        $with = imagettfbbox($size, 0, $font, 'x'.$char.'x');
        $without = imagettfbbox($size, 0, $font, 'xx');

        return $with === false || $without === false ? 0.0 : (float) (($with[2] - $with[0]) - ($without[2] - $without[0]));
    }

    /** GD trabaja en puntos a 96 ppp. */
    private function pt(float $px): float
    {
        return $px * 0.75;
    }

    /**
     * Elige el tamaño mayor con el que el título cabe en `maxLines`.
     *
     * @param  list<int>  $sizes
     * @return array{size: int, lines: list<string>}
     */
    private function fitTitle(string $title, float $maxWidth, array $sizes, int $maxLines): array
    {
        $font = $this->font('familjen-bold');
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

        foreach ($sizes as $size) {
            $lines = $this->wrap($title, $font, $size, $maxWidth);
            if (count($lines) <= $maxLines) {
                return ['size' => $size, 'lines' => $lines];
            }
        }

        $size = (int) end($sizes);
        $lines = array_slice($this->wrap($title, $font, $size, $maxWidth), 0, $maxLines);
        $last = array_pop($lines) ?? '';
        $lines[] = $this->trimTo($last.'…', $size, 'familjen-bold', $maxWidth, -0.02);

        return ['size' => $size, 'lines' => $lines];
    }

    /** @return list<string> */
    private function wrap(string $text, string $font, float $px, float $maxWidth): array
    {
        $lines = [];
        $current = '';
        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($current !== '' && $this->textWidth($px, $font, $candidate, -0.02) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function trimTo(string $text, float $px, string $fontKey, float $maxWidth, float $tracking = 0.0): string
    {
        $font = $this->font($fontKey);
        if ($this->textWidth($px, $font, $text, $tracking) <= $maxWidth) {
            return $text;
        }
        $text = rtrim($text, ' …');
        while ($text !== '' && $this->textWidth($px, $font, $text.'…', $tracking) > $maxWidth) {
            $text = rtrim(mb_substr($text, 0, -1));
        }

        return $text.'…';
    }

    /** Dominio que se imprime en la tarjeta (el de APP_URL; en local, el de producción). */
    private function domain(): string
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = (string) preg_replace('/^www./', '', $host);

        return $host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false || ! str_contains($host, '.') || str_ends_with($host, '.test')
            ? 'mlhabitat.com.mx'
            : $host;
    }

    private function font(string $key): string
    {
        return resource_path('fonts/'.match ($key) {
            'familjen-bold' => 'FamiljenGrotesk-Bold.ttf',
            'familjen-semibold' => 'FamiljenGrotesk-SemiBold.ttf',
            'figtree-medium' => 'Figtree-Medium.ttf',
            'figtree-semibold' => 'Figtree-SemiBold.ttf',
            default => 'Figtree-Regular.ttf',
        });
    }

    // ------------------------------------------------------------------
    // Datos
    // ------------------------------------------------------------------

    /**
     * @return array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}
     */
    private function spec(string $type, string $slug, string $title, ?string $eyebrow, ?Media $media, string $layout, string $alt): array
    {
        return [
            'type' => $type,
            'slug' => $slug,
            'title' => trim($title),
            'eyebrow' => $eyebrow !== null ? Str::limit(trim($eyebrow), 60) : null,
            'media' => $media,
            'layout' => $layout,
            'alt' => Str::limit($alt, 160),
        ];
    }

    /**
     * @param  array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}  $spec
     */
    private function hash(array $spec): string
    {
        return substr(md5((string) json_encode([
            self::VERSION,
            $spec['type'],
            $spec['slug'],
            $spec['title'],
            $spec['eyebrow'],
            $spec['layout'],
            $spec['media']?->id,
            $spec['media']?->updated_at?->getTimestamp(),
            PublicSite::name(),
            $this->domain(),
        ])), 0, 12);
    }

    /**
     * @return array{type: string, slug: string, title: string, eyebrow: string|null, media: Media|null, layout: string, alt: string}|null
     */
    private function resolvePage(string $slug): ?array
    {
        if (isset(self::STATIC_PAGES[$slug])) {
            [$title, $eyebrow] = self::STATIC_PAGES[$slug];

            return $this->page($slug, $title, $eyebrow);
        }

        $page = Page::query()->published()->where('slug', $slug)->first();

        return $page !== null ? $this->page($page->slug, $page->title, 'Información legal') : null;
    }

    /** Media pública, lista y de tipo imagen; si no, null. */
    private function usable(?Media $media): ?Media
    {
        if ($media === null || $media->type !== MediaType::Image || $media->status !== MediaStatus::Ready || $media->isPrivate()) {
            return null;
        }

        return $media;
    }

    private function media(mixed $id): ?Media
    {
        return is_numeric($id) ? $this->usable(Media::query()->find((int) $id)) : null;
    }

    /** Imagen del hero de la portada (CMS), si la primera sección visible es un hero. */
    private function heroMedia(): ?Media
    {
        try {
            $hero = SiteSection::query()->forPage('home')->where('is_visible', true)->first();
        } catch (Throwable) {
            return null;
        }

        if ($hero === null || $hero->type !== SiteSectionType::Hero) {
            return null;
        }

        $id = $hero->content['media_id'] ?? null;

        return $this->media($id);
    }

    /** Carga la variante de 1600 px (o el original) como imagen GD. */
    private function loadImage(Media $media): ?GdImage
    {
        $media->loadMissing('variants');

        $candidates = [];
        foreach ([MediaVariantType::Webp1600->value, MediaVariantType::Webp960->value] as $type) {
            $variant = $media->variants->firstWhere('type', $type);
            if ($variant !== null) {
                $candidates[] = [$variant->disk, $variant->path];
            }
        }
        $candidates[] = [$media->disk, $media->path];

        foreach ($candidates as [$disk, $path]) {
            try {
                $bytes = Storage::disk($disk)->get($path);
                if (is_string($bytes) && $bytes !== '') {
                    $image = @imagecreatefromstring($bytes);
                    if ($image instanceof GdImage) {
                        return $image;
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function stripBrand(string $title, string $name): string
    {
        $quoted = preg_quote($name, '/');
        $title = preg_replace('/^\s*'.$quoted.'\s*[—\-|:·]\s*/u', '', $title) ?? $title;
        $title = preg_replace('/\s*[—\-|:·]\s*'.$quoted.'\s*$/u', '', $title) ?? $title;

        return trim($title);
    }
}
