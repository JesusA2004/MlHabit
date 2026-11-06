<?php

namespace App\Cms\Layout;

use App\Cms\Layout\Legacy\HomeV1Layouts;
use App\Enums\SiteSectionType;
use App\Models\SitePageDraft;
use App\Models\SitePageVersion;
use App\Models\SiteSection;
use App\Services\Web\PublicSite;
use App\Services\Web\SiteBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lleva la portada rediseñada (v3, `HomeBlueprint`) a una instalación que
 * ya existe, sin perder nada:
 *
 * 1. Siempre guarda primero lo publicado como versión del historial
 *    («Portada anterior (antes del rediseño)»), y un borrador sin publicar
 *    como otra versión, para poder restaurarlos desde el editor.
 * 2. Si la portada publicada es la de fábrica (idéntica al lienzo v1
 *    normalizado) o el equipo nunca publicó cambios, publica la nueva y
 *    deja el borrador igual a lo publicado.
 * 3. Si el equipo ya la personalizó, NO la toca: guarda la nueva como
 *    versión («restáurala para usarla»).
 *
 * Solo trabaja con la página `home`: las páginas propias (`pagina-{id}`)
 * nunca se tocan. Es idempotente: si ya existe la versión de respaldo no
 * vuelve a hacer nada (salvo `force`).
 */
final class HomeRedesign
{
    public const PAGE = 'home';

    public const BACKUP_NOTE = 'Portada anterior (antes del rediseño)';

    public const DRAFT_NOTE = 'Borrador sin publicar (antes del rediseño)';

    public const PUBLISHED_NOTE = 'Rediseño de la portada (v3)';

    public const OFFERED_NOTE = 'Portada rediseñada (v3): restáurala para usarla';

    /** Resultado de `apply()`. */
    public const ALREADY = 'already';

    public const INSTALLED = 'installed';

    public const REPLACED = 'replaced';

    public const KEPT = 'kept';

    /**
     * @return self::ALREADY|self::INSTALLED|self::REPLACED|self::KEPT
     */
    public function apply(bool $force = false): string
    {
        $result = DB::transaction(function () use ($force): string {
            $versions = SitePageVersion::query()->where('page', self::PAGE);

            if (! $force && (clone $versions)->whereIn('note', [self::BACKUP_NOTE, self::PUBLISHED_NOTE, self::OFFERED_NOTE])->exists()) {
                return self::ALREADY;
            }

            /** @var Collection<int, SiteSection> $sections */
            $sections = SiteSection::query()->forPage(self::PAGE)->lockForUpdate()->get();
            $hadVersions = (clone $versions)->exists();
            $new = LayoutValidator::snapshot(['sections' => HomeBlueprint::sections()]);

            if ($sections->isEmpty() && ! $hadVersions) {
                $this->publish($new);

                return self::INSTALLED;
            }

            // 1. Respaldo de lo publicado (y del borrador, si difiere).
            $published = $this->snapshotOf($sections);
            $this->version($published, self::BACKUP_NOTE);

            $draft = SitePageDraft::query()->where('page', self::PAGE)->first();
            if ($draft !== null && $this->fingerprint($draft->snapshot) !== $this->fingerprint($published)) {
                $this->version($draft->snapshot, self::DRAFT_NOTE);
            }

            // 2. ¿Portada de fábrica o sin publicaciones del equipo?
            $untouched = ! $hadVersions && $sections->every(fn (SiteSection $s) => $s->updated_by === null);

            if ($force || $untouched || $this->isFactoryDefault($sections)) {
                $this->publish($new);

                return self::REPLACED;
            }

            // 3. Personalizada: se ofrece la nueva en el historial.
            $this->version($new, self::OFFERED_NOTE);

            return self::KEPT;
        });

        PublicSite::flush();

        return $result;
    }

    /**
     * ¿Lo publicado es exactamente la portada de fábrica v1 (mismo orden de
     * secciones, todas visibles y cada lienzo igual al que generaba v1 con
     * su propio contenido, ignorando ids de bloque)?
     *
     * @param  Collection<int, SiteSection>  $sections
     */
    public function isFactoryDefault(Collection $sections): bool
    {
        $expected = array_map(fn (SiteSectionType $t) => $t->value, SiteBuilder::RECOMMENDED_ORDER);
        $types = $sections->map(fn (SiteSection $s) => $s->type->value)->values()->all();

        if ($types !== $expected) {
            return false;
        }

        foreach ($sections as $section) {
            if (! $section->is_visible) {
                return false;
            }
            if ($section->layout === null) {
                continue; // Sin lienzo propio: nunca se editó en el editor visual.
            }
            $v1 = HomeV1Layouts::for($section->type, $section->content);
            if (self::normalize($section->layout) !== self::normalize($v1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Forma comparable de un lienzo: sin ids de bloque, sin el ajuste nuevo
     * `show_example` y sin el paso del recorrido (`journey`, que se agregó
     * al lienzo de fábrica después de algunas siembras), claves ordenadas y
     * números enteros normalizados (el JSON de la base convierte 1.0 en 1).
     */
    public static function normalize(mixed $value): mixed
    {
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (! is_array($value)) {
            return $value;
        }

        unset($value['show_example']);
        if (isset($value['background'], $value['padding'])) {
            unset($value['journey']);
        }
        if (isset($value['type'], $value['x'], $value['y'])) {
            unset($value['id']);
        }

        $out = array_map(self::normalize(...), $value);
        if (! array_is_list($out)) {
            ksort($out);
        }

        return $out;
    }

    /**
     * @param  Collection<int, SiteSection>  $sections
     * @return array{sections: list<array<string, mixed>>}
     */
    private function snapshotOf(Collection $sections): array
    {
        return ['sections' => array_values($sections->map(function (SiteSection $s) {
            $snapshot = $s->toSnapshot();
            $snapshot['key'] = $snapshot['key'] !== '' ? $snapshot['key'] : (string) Str::uuid();

            return $snapshot;
        })->all())];
    }

    /** @param array<string, mixed> $snapshot */
    private function version(array $snapshot, string $note): SitePageVersion
    {
        $number = (int) SitePageVersion::query()->where('page', self::PAGE)->lockForUpdate()->max('number') + 1;

        return SitePageVersion::query()->create([
            'page' => self::PAGE,
            'number' => $number,
            'snapshot' => $snapshot,
            'note' => $note,
            'published_by' => null,
            'published_at' => now(),
        ]);
    }

    /**
     * Reemplaza las secciones publicadas de la portada, registra la versión
     * y deja el borrador igual a lo publicado.
     *
     * @param  array{sections: list<array<string, mixed>>}  $snapshot
     */
    private function publish(array $snapshot): void
    {
        SiteSection::query()->where('page', self::PAGE)->delete();

        foreach ($snapshot['sections'] as $index => $section) {
            SiteSection::query()->create([
                'key' => $section['key'],
                'page' => self::PAGE,
                'type' => $section['type'],
                'sort' => ($index + 1) * 10,
                'is_visible' => $section['isVisible'],
                'content' => $section['content'],
                'layout' => $section['layout'],
                'layout_version' => $section['layout']['version'],
            ]);
        }

        $this->version($snapshot, self::PUBLISHED_NOTE);

        $draft = SitePageDraft::query()->where('page', self::PAGE)->first();
        if ($draft === null) {
            SitePageDraft::query()->create(['page' => self::PAGE, 'snapshot' => $snapshot, 'revision' => 1]);
        } else {
            $draft->forceFill(['snapshot' => $snapshot, 'revision' => $draft->revision + 1])->save();
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function fingerprint(array $snapshot): string
    {
        $sections = array_map(fn ($s) => is_array($s) ? self::normalize([
            'type' => $s['type'] ?? null,
            'isVisible' => $s['isVisible'] ?? null,
            'layout' => $s['layout'] ?? null,
        ]) : null, is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : []);

        return md5((string) json_encode($sections));
    }
}
