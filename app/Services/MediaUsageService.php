<?php

namespace App\Services;

use App\Cms\Layout\LayoutValidator;
use App\Models\Finish;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShowcaseProject;
use App\Models\SitePageDraft;
use App\Models\SiteSection;
use App\Models\Testimonial;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Averigua dónde se usa un archivo antes de permitir borrarlo. Si algo lo
 * usa, el borrado se bloquea: primero hay que quitarlo de ahí. Esto evita
 * portadas en blanco, galerías incompletas o documentos de clientes
 * huérfanos.
 */
class MediaUsageService
{
    /** Claves de `brand` que guardan ids de medios. */
    public const BRAND_KEYS = [
        'logo_media_id' => 'Logotipo',
        'logo_dark_media_id' => 'Logotipo para fondo oscuro',
        'favicon_media_id' => 'Favicon',
        'og_media_id' => 'Imagen para redes sociales (OG)',
    ];

    /**
     * @return list<array{type: string, label: string, href: string|null}>
     */
    public function usages(Media $media): array
    {
        $id = $media->id;
        $usages = [];

        foreach (ProductCategory::withTrashed()->where('cover_media_id', $id)->get(['id', 'name']) as $category) {
            $usages[] = ['type' => 'Portada de categoría', 'label' => $category->name, 'href' => '/admin/catalogo?pestana=categorias'];
        }

        foreach (Product::withTrashed()->where('cover_media_id', $id)->get(['id', 'name']) as $product) {
            $usages[] = ['type' => 'Portada de producto', 'label' => $product->name, 'href' => "/admin/catalogo/productos/{$product->id}/editar"];
        }

        foreach (ShowcaseProject::withTrashed()->where('cover_media_id', $id)->get(['id', 'title']) as $project) {
            $usages[] = ['type' => 'Portada de inspiración', 'label' => $project->title, 'href' => "/admin/inspiracion/{$project->id}/editar"];
        }

        foreach (Finish::query()->where('media_id', $id)->get(['id', 'name']) as $finish) {
            $usages[] = ['type' => 'Muestra de acabado', 'label' => $finish->name, 'href' => '/admin/catalogo?pestana=acabados'];
        }

        foreach (Testimonial::query()->where('media_id', $id)->get(['id', 'author_name']) as $testimonial) {
            $usages[] = ['type' => 'Foto de testimonio', 'label' => $testimonial->author_name, 'href' => '/admin/sitio?pestana=testimonios'];
        }

        foreach (MediaAttachment::query()->where('media_id', $id)->get() as $attachment) {
            $usages[] = $this->describeAttachment($attachment);
        }

        foreach (SiteSection::query()->get() as $section) {
            if (in_array($id, $section->mediaIds(), true)) {
                $usages[] = ['type' => 'Portada del sitio', 'label' => $section->schema()->label(), 'href' => '/admin/sitio/editor'];
            }
        }

        // Borradores del editor visual: aún no publicados, pero en uso.
        foreach (SitePageDraft::query()->get() as $draft) {
            foreach ($draft->snapshot['sections'] as $section) {
                if (in_array($id, LayoutValidator::mediaIds(is_array($section['layout'] ?? null) ? $section['layout'] : null), true)) {
                    $usages[] = ['type' => 'Borrador del editor visual', 'label' => 'Portada (sin publicar)', 'href' => '/admin/sitio/editor'];
                    break 2;
                }
            }
        }

        $brand = Settings::group('brand');
        foreach (self::BRAND_KEYS as $key => $label) {
            if (is_numeric($brand[$key] ?? null) && (int) $brand[$key] === $id) {
                $usages[] = ['type' => 'Identidad', 'label' => $label, 'href' => '/admin/sitio?pestana=identidad'];
            }
        }

        return $usages;
    }

    public function isUsed(Media $media): bool
    {
        return $this->usages($media) !== [];
    }

    /**
     * @return array{type: string, label: string, href: string|null}
     */
    private function describeAttachment(MediaAttachment $attachment): array
    {
        $class = Relation::getMorphedModel($attachment->attachable_type) ?? $attachment->attachable_type;

        return match ($class) {
            Product::class => [
                'type' => 'Galería de producto',
                'label' => (string) (Product::withTrashed()->whereKey($attachment->attachable_id)->value('name') ?? 'Producto'),
                'href' => "/admin/catalogo/productos/{$attachment->attachable_id}/editar",
            ],
            ShowcaseProject::class => [
                'type' => 'Galería de inspiración',
                'label' => (string) (ShowcaseProject::withTrashed()->whereKey($attachment->attachable_id)->value('title') ?? 'Proyecto'),
                'href' => "/admin/inspiracion/{$attachment->attachable_id}/editar",
            ],
            ProductCategory::class => [
                'type' => 'Galería de categoría',
                'label' => (string) (ProductCategory::withTrashed()->whereKey($attachment->attachable_id)->value('name') ?? 'Categoría'),
                'href' => '/admin/catalogo?pestana=categorias',
            ],
            default => [
                'type' => 'Archivo vinculado',
                'label' => class_basename($class).' #'.$attachment->attachable_id.' ('.$attachment->role.')',
                'href' => null,
            ],
        };
    }
}
