<?php

namespace App\Services\Web;

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
 * Compone la portada a partir de las secciones del CMS en su orden y
 * visibilidad guardados. Cada sección se resuelve con sus datos vivos
 * (categorías publicadas, proyectos destacados, testimonios con
 * consentimiento, preguntas, política de visita activa).
 */
class HomePage
{
    public function __construct(private readonly Presenter $presenter) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(bool $includeHidden = false): array
    {
        $sections = SiteSection::query()->forPage('home')
            ->when(! $includeHidden, fn ($q) => $q->where('is_visible', true))
            ->get();

        $mediaIds = $sections->flatMap(fn (SiteSection $s) => $s->schema()->mediaIds($s->content))->unique()->values()->all();
        /** @var Collection<int, Media> $media */
        $media = $mediaIds !== []
            ? Media::query()->with('variants')->whereIn('id', $mediaIds)->get()->keyBy('id')
            : collect();

        $out = [];
        foreach ($sections as $section) {
            $data = $this->resolve($section, $media);
            if ($data !== null) {
                $out[] = $data;
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Media>  $media
     * @return array<string, mixed>|null
     */
    private function resolve(SiteSection $section, Collection $media): ?array
    {
        $content = $section->content;
        $image = function (string $key, string $sizes = Presenter::SIZES_WIDE) use ($content, $media): ?array {
            $id = $content[$key] ?? null;

            return is_int($id) ? ResponsiveImage::for($media->get($id), null, $sizes) : null;
        };

        $base = ['id' => $section->id, 'type' => $section->type->value, 'content' => $content];

        return match ($section->type) {
            SiteSectionType::Hero => [...$base, 'image' => $image('media_id', Presenter::SIZES_FULL)],
            SiteSectionType::Benefits, SiteSectionType::FinalCta => [...$base, 'image' => $image('media_id')],
            SiteSectionType::HomeVisit => [...$base, 'image' => $image('media_id'), 'visit' => ($content['show_price'] ?? false) ? $this->visitPolicy() : null],
            SiteSectionType::Categories => $this->withItems($base, $this->categories($content)),
            SiteSectionType::FeaturedProjects => $this->withItems($base, $this->projects((int) ($content['limit'] ?? 3))),
            SiteSectionType::Testimonials => $this->withItems($base, $this->testimonials((int) ($content['limit'] ?? 6))),
            SiteSectionType::Faq => $this->withItems($base, $this->faqs($content)),
            SiteSectionType::Process, SiteSectionType::PaymentOptions, SiteSectionType::Coverage => $base,
        };
    }

    /**
     * Secciones con listas vivas: si no hay elementos, la sección se oculta.
     *
     * @param  array<string, mixed>  $base
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function withItems(array $base, array $items): ?array
    {
        return $items === [] ? null : [...$base, 'items' => $items];
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
