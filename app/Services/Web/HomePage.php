<?php

namespace App\Services\Web;

use App\Cms\Field;
use App\Cms\Layout\DynamicBlocks;
use App\Cms\Layout\Grid;
use App\Cms\Layout\LayoutValidator;
use App\Enums\MediaType;
use App\Enums\MediaVariantType;
use App\Enums\SiteSectionType;
use App\Models\Faq;
use App\Models\Media;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Models\SiteSection;
use App\Models\Testimonial;
use App\Support\Money;
use App\Support\ResponsiveImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compone una página del editor visual (hoy, la portada) a partir de sus
 * secciones: lienzo de bloques + datos vivos de los bloques dinámicos
 * (categorías publicadas, proyectos destacados, testimonios con
 * consentimiento, preguntas, política de visita activa) e imágenes
 * responsivas de cada bloque.
 *
 * Sirve igual para lo publicado (site_sections), el borrador del editor y
 * la vista previa: todo entra como "sección de snapshot".
 */
class HomePage
{
    public function __construct(private readonly Presenter $presenter) {}

    /**
     * Secciones PUBLICADAS listas para el renderizador.
     *
     * @return list<array<string, mixed>>
     */
    public function sections(bool $includeHidden = false, string $page = 'home'): array
    {
        $sections = SiteSection::query()->forPage($page)
            ->when(! $includeHidden, fn ($q) => $q->where('is_visible', true))
            ->get();

        $out = $this->resolve(array_values($sections->map(fn (SiteSection $s) => $s->toSnapshot())->all()));

        // Compatibilidad: el id numérico de la sección publicada.
        $ids = $sections->pluck('id', 'key');
        foreach ($out as $i => $section) {
            $out[$i]['id'] = $ids->get($section['key']);
        }

        return $out;
    }

    /**
     * Resuelve secciones de snapshot (publicadas, borrador o versión).
     * En modo edición se incluyen las ocultas y las listas vivas vacías.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public function resolve(array $sections, bool $editing = false): array
    {
        $ids = [];
        foreach ($sections as $section) {
            $ids = [...$ids, ...LayoutValidator::mediaIds(is_array($section['layout'] ?? null) ? $section['layout'] : null)];
        }

        /** @var Collection<int, Media> $media */
        $media = $ids !== []
            ? Media::query()->with('variants')->whereIn('id', array_values(array_unique($ids)))->where('visibility', 'public')->get()->keyBy('id')
            : collect();

        $out = [];
        foreach ($sections as $section) {
            if (! $editing && ($section['isVisible'] ?? true) !== true) {
                continue;
            }
            $resolved = $this->resolveSection($section, $media, $editing);
            if ($resolved !== null) {
                $out[] = $resolved;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  Collection<int, Media>  $media
     * @return array<string, mixed>|null
     */
    private function resolveSection(array $section, Collection $media, bool $editing): ?array
    {
        $type = SiteSectionType::tryFrom((string) ($section['type'] ?? '')) ?? SiteSectionType::Canvas;
        /** @var array<string, mixed> $layout */
        $layout = $section['layout'];
        $settings = $layout['settings'] ?? [];
        $fullBleed = ($settings['fullBleed'] ?? false) === true;

        $images = [];
        $videos = [];
        $data = [];
        $blocks = [];

        $bgId = $settings['background']['mediaId'] ?? null;
        $background = is_int($bgId) && ($settings['background']['kind'] ?? null) === 'image'
            ? ResponsiveImage::for($media->get($bgId), null, Presenter::SIZES_FULL)
            : null;
        if ($background !== null) {
            $images[$bgId] = $background;
        }

        foreach ($layout['blocks'] ?? [] as $block) {
            $id = $block['id'];
            $props = $block['props'] ?? [];

            if ($block['type'] === 'image' && is_int($props['mediaId'] ?? null)) {
                $images[$props['mediaId']] ??= ResponsiveImage::for($media->get($props['mediaId']), null, $this->sizes((int) $block['w'], $fullBleed));
            }
            if ($block['type'] === 'video' && is_int($props['mediaId'] ?? null)) {
                $video = $media->get($props['mediaId']);
                if ($video instanceof Media && $video->type === MediaType::Video) {
                    $videos[$props['mediaId']] = [
                        'src' => $video->url(),
                        'mime' => $video->mime_type,
                        'poster' => $video->variantUrl(MediaVariantType::Poster->value),
                        'width' => $video->width,
                        'height' => $video->height,
                    ];
                }
            }

            if (DynamicBlocks::exists($block['type'])) {
                $resolved = $this->dynamicData($block['type'], $props, $media, (int) $block['w'], $fullBleed);
                $empty = DynamicBlocks::isLiveList($block['type']) && ($resolved['items'] ?? []) === [];

                if ($empty && ! $editing) {
                    // Una sección curada cuya lista viva está vacía se oculta
                    // completa (su encabezado quedaría huérfano); en un
                    // lienzo libre solo se omite el bloque.
                    if (DynamicBlocks::sourceType($block['type']) === $type) {
                        return null;
                    }

                    continue;
                }
                $data[$id] = [...$resolved, 'empty' => $empty];
            }

            $blocks[] = $block;
        }

        $layout['blocks'] = $blocks;

        $out = [
            'id' => null,
            'key' => (string) $section['key'],
            'type' => $type->value,
            'isVisible' => ($section['isVisible'] ?? true) === true,
            'content' => $section['content'] ?? [],
            'layout' => $layout,
            'images' => (object) $images,
            'videos' => (object) $videos,
            'data' => (object) $data,
            // Compatibilidad con consumidores anteriores (SEO, pruebas): la
            // imagen principal y los datos del primer bloque dinámico.
            'image' => $background,
        ];

        foreach ($data as $resolved) {
            if (array_key_exists('items', $resolved) && ! array_key_exists('items', $out)) {
                $out['items'] = $resolved['items'];
            }
            if (array_key_exists('visit', $resolved) && ! array_key_exists('visit', $out)) {
                $out['visit'] = $resolved['visit'];
            }
        }

        return $out;
    }

    /** `sizes` de una imagen según las columnas que ocupa en escritorio. */
    private function sizes(int $columns, bool $fullBleed): string
    {
        $ratio = max(1, $columns) / Grid::COLUMNS;
        $desktop = $fullBleed ? ((int) round($ratio * 100)).'vw' : ((int) round($ratio * Grid::CONTENT_WIDTH)).'px';

        return $columns >= 8 ? "(min-width: 1024px) $desktop, 100vw" : "(min-width: 1024px) $desktop, (min-width: 768px) 66vw, 100vw";
    }

    /**
     * Datos vivos de un bloque dinámico.
     *
     * @param  array<string, mixed>  $props
     * @param  Collection<int, Media>  $media
     * @return array<string, mixed>
     */
    private function dynamicData(string $type, array $props, Collection $media, int $columns, bool $fullBleed): array
    {
        $image = function () use ($type, $props, $media, $columns, $fullBleed): ?array {
            foreach (DynamicBlocks::schema($type)->fields() as $field) {
                if ($field->kind === Field::MEDIA && is_int($props[$field->key] ?? null)) {
                    return ResponsiveImage::for($media->get($props[$field->key]), null, $this->sizes($columns, $fullBleed));
                }
            }

            return null;
        };

        return match ($type) {
            'categories' => ['items' => $this->categories($props)],
            'featured_projects' => ['items' => $this->projects((int) ($props['limit'] ?? 3))],
            'testimonials' => ['items' => $this->testimonials((int) ($props['limit'] ?? 6))],
            'faq' => ['items' => $this->faqs($props)],
            'home_visit' => ['image' => $image(), 'visit' => ($props['show_price'] ?? false) ? $this->visitPolicy() : null],
            'final_cta' => ['image' => $image()],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    private function categories(array $content): array
    {
        return array_values(ProductCategory::query()->published()
            ->when((bool) ($content['featured_only'] ?? false), fn ($q) => $q->where('is_featured', true))
            ->with('cover.variants')
            ->withCount(['products' => fn ($q) => $q->where('status', 'published')])
            ->ordered()
            ->limit((int) ($content['limit'] ?? 6))
            ->get()
            ->map(fn (ProductCategory $c, int $i) => $this->presenter->categoryCard($c, $i === 0 ? Presenter::SIZES_WIDE : Presenter::SIZES_CARD))
            ->values()->all());
    }

    /** @return list<array<string, mixed>> */
    private function projects(int $limit): array
    {
        $featured = ShowcaseProject::query()->published()->where('is_featured', true)
            ->with(['cover.variants', 'category'])->ordered()->limit($limit)->get();

        return array_values($featured->map(fn (ShowcaseProject $p, int $i) => $this->presenter->showcaseCard($p, $i === 0 ? Presenter::SIZES_WIDE : Presenter::SIZES_CARD))
            ->values()->all());
    }

    /** @return list<array<string, mixed>> */
    private function testimonials(int $limit): array
    {
        return array_values(Testimonial::query()->published()->with(['media.variants', 'showcaseProject'])
            ->orderBy('sort')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Testimonial $t) => $this->presenter->testimonial($t))->values()->all());
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array<string, mixed>>
     */
    private function faqs(array $content): array
    {
        $topics = is_array($content['topics'] ?? null) ? $content['topics'] : [];

        return array_values(Faq::query()->published()
            ->when($topics !== [], fn ($q) => $q->whereIn('topic', $topics))
            ->ordered()->limit((int) ($content['limit'] ?? 8))->get()
            ->map(fn (Faq $f) => $this->presenter->faq($f))->values()->all());
    }

    /**
     * Política de visita activa (tabla del módulo de visitas). Se consulta
     * por tabla para no acoplarse a su modelo. Sin política activa no se
     * muestra ningún precio.
     *
     * @return array{fee: string, feeFormatted: string, currency: string, isCreditable: bool, creditConditions: string|null}|null
     */
    public function visitPolicy(): ?array
    {
        if (! Schema::hasTable('visit_policies')) {
            return null;
        }

        $policy = DB::table('visit_policies')->where('is_active', true)->orderByDesc('version')->first();
        if ($policy === null || ! is_numeric($policy->fee_amount ?? null)) {
            return null;
        }

        $fee = (string) $policy->fee_amount;

        return [
            'fee' => $fee,
            'feeFormatted' => Money::of($fee)->format((string) ($policy->currency ?? 'MXN')),
            'currency' => (string) ($policy->currency ?? 'MXN'),
            'isCreditable' => (bool) $policy->is_creditable,
            'creditConditions' => is_string($policy->credit_conditions ?? null) && trim($policy->credit_conditions) !== '' ? trim($policy->credit_conditions) : null,
        ];
    }
}
