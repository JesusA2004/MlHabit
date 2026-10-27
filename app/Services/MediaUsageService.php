<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageEntry;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\StudioPage;
use App\Models\ThemeSetting;
use App\Studio\StudioReferences;

/**
 * Antes de que MediaController permita borrar un archivo, esta clase
 * averigua si algo lo está usando de verdad. Varias de esas referencias
 * (categorías, publicaciones, páginas) tienen clave foránea con
 * nullOnDelete/cascadeOnDelete: la base de datos "resolvería" el borrado
 * sola, pero en silencio — dejando una portada en blanco o una foto
 * desaparecida de una galería sin que nadie se entere. Esta clase existe
 * para que el administrador vea eso ANTES de confirmar, no después.
 */
class MediaUsageService
{
    public function __construct(private readonly StudioReferences $studioReferences) {}

    /**
     * @return array<int, array{type: string, label: string, href: string}>
     */
    public function usages(Media $media): array
    {
        $usages = [];

        foreach (Post::query()->where('cover_media_id', $media->id)->get(['id', 'title']) as $post) {
            $usages[] = ['type' => 'Portada de publicación', 'label' => $post->title, 'href' => "/admin/publicaciones/{$post->id}/edit"];
        }

        foreach (Post::query()->whereHas('media', fn ($q) => $q->where('media.id', $media->id))->get(['id', 'title']) as $post) {
            $usages[] = ['type' => 'Galería/contenido de publicación', 'label' => $post->title, 'href' => "/admin/publicaciones/{$post->id}/edit"];
        }

        foreach (Category::query()->where('cover_media_id', $media->id)->get(['id', 'name']) as $category) {
            $usages[] = ['type' => 'Portada de categoría', 'label' => $category->name, 'href' => '/admin/categorias'];
        }

        foreach (Page::query()->where('cover_media_id', $media->id)->get(['key', 'title']) as $page) {
            $usages[] = ['type' => 'Portada de página', 'label' => $page->title, 'href' => "/admin/paginas/{$page->key}"];
        }

        foreach (PageEntry::query()->where('cover_media_id', $media->id)->with('page')->get() as $entry) {
            $usages[] = ['type' => 'Entrada cronológica', 'label' => $entry->title, 'href' => "/admin/paginas/{$entry->page->key}"];
        }

        $hero = SiteSetting::get('hero', []);
        if ((int) ($hero['photo_media_id'] ?? 0) === $media->id) {
            $usages[] = ['type' => 'Inicio', 'label' => 'Fotografía de portada (hero)', 'href' => '/admin/inicio'];
        }

        $historia = SiteSetting::get('historia_preview', []);
        if ((int) ($historia['photo_media_id'] ?? 0) === $media->id) {
            $usages[] = ['type' => 'Inicio', 'label' => 'Fotografía de Mi historia', 'href' => '/admin/inicio'];
        }

        $logo = ThemeSetting::get('logo', []);
        foreach (['logo_media_id' => 'Logotipo', 'mark_media_id' => 'Isotipo', 'favicon_media_id' => 'Favicon'] as $key => $label) {
            if ((int) ($logo[$key] ?? 0) === $media->id) {
                $usages[] = ['type' => 'Apariencia', 'label' => $label, 'href' => '/admin/apariencia'];
            }
        }

        foreach (StudioPage::query()->with('publishedVersion')->get() as $page) {
            $published = $page->publishedVersion?->document;
            if ($published !== null && in_array($media->id, $this->studioReferences->mediaIds($published), true)) {
                $usages[] = ['type' => 'Studio (publicado)', 'label' => $page->title, 'href' => "/admin/studio/{$page->id}"];
            } elseif ($page->draft !== null && in_array($media->id, $this->studioReferences->mediaIds($page->draft), true)) {
                $usages[] = ['type' => 'Studio (borrador)', 'label' => $page->title, 'href' => "/admin/studio/{$page->id}"];
            }
        }

        return $usages;
    }

    /**
     * Solo se llama tras una eliminación forzada confirmada por el
     * administrador: limpia las referencias que viven en JSON (sin clave
     * foránea) para que no queden apuntando a un archivo que ya no existe.
     * Las columnas con FK (posts, categorías, páginas) la base de datos ya
     * las deja en null o elimina la fila pivote por su cuenta.
     */
    public function clearReferences(Media $media): void
    {
        $hero = SiteSetting::get('hero', []);
        if ((int) ($hero['photo_media_id'] ?? 0) === $media->id) {
            SiteSetting::set('hero', [...$hero, 'photo_media_id' => null]);
        }

        $historia = SiteSetting::get('historia_preview', []);
        if ((int) ($historia['photo_media_id'] ?? 0) === $media->id) {
            SiteSetting::set('historia_preview', [...$historia, 'photo_media_id' => null]);
        }

        $logo = ThemeSetting::get('logo', []);
        $changed = false;
        foreach (['logo_media_id', 'mark_media_id', 'favicon_media_id'] as $key) {
            if ((int) ($logo[$key] ?? 0) === $media->id) {
                $logo[$key] = null;
                $changed = true;
            }
        }
        if ($changed) {
            ThemeSetting::set('logo', $logo);
        }

        // Borradores de Studio: se retira la referencia (el editor mostrará
        // el hueco). Las versiones publicadas son historial inmutable; el
        // renderizador omite medios inexistentes para que el sitio no se rompa.
        foreach (StudioPage::query()->whereNotNull('draft')->get() as $page) {
            if (in_array($media->id, $this->studioReferences->mediaIds($page->draft ?? []), true)) {
                $page->update([
                    'draft' => $this->studioReferences->withoutMedia($page->draft ?? [], $media->id),
                    'draft_revision' => $page->draft_revision + 1,
                ]);
            }
        }
    }
}
