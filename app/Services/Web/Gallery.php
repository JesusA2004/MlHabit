<?php

namespace App\Services\Web;

use App\Models\Media;
use App\Models\Product;
use App\Models\ShowcaseProject;
use Illuminate\Database\Eloquent\Model;

/**
 * Galerías de catálogo e inspiración (media_attachments, rol `gallery`).
 * El origen (render / foto real) se guarda en el propio archivo para que
 * la etiqueta sea la misma en cualquier lugar donde se use.
 */
class Gallery
{
    public const ROLE = 'gallery';

    /**
     * @param  list<array{id: int|string, origin?: string|null, caption?: string|null}>  $items
     */
    public function sync(Product|ShowcaseProject $owner, array $items, ?int $userId): void
    {
        $ids = [];
        foreach ($items as $item) {
            $ids[] = (int) $item['id'];
        }

        $owner->syncAttachments(self::ROLE, $ids, $userId);

        foreach ($items as $item) {
            $id = (int) $item['id'];
            $origin = $item['origin'] ?? null;
            if (in_array($origin, ['render', 'real_photo'], true)) {
                Media::query()->whereKey($id)->update(['origin' => $origin]);
            }
            $owner->attachments()->where('role', self::ROLE)->where('media_id', $id)
                ->update(['caption' => isset($item['caption']) && trim((string) $item['caption']) !== '' ? trim((string) $item['caption']) : null]);
        }
    }

    /**
     * Galería para el formulario del panel.
     *
     * @return list<array<string, mixed>>
     */
    public function forEditor(Model $owner): array
    {
        if (! $owner instanceof Product && ! $owner instanceof ShowcaseProject) {
            return [];
        }

        $media = $owner->mediaWithRole(self::ROLE)->with('variants')->get();

        return array_values($media->map(function (Media $m) {
            $caption = $m->getRelationValue('pivot')?->getAttribute('caption');

            return [...(array) MediaPresenter::ref($m), 'caption' => is_string($caption) ? $caption : null];
        })->all());
    }
}
