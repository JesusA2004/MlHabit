<?php

namespace App\Services\Web;

use App\Cms\Field;
use App\Models\SitePage;
use App\Support\Settings;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menú del sitio (Sitio web → Menú): enlaces de la cabecera en orden, con
 * un nivel de submenú, y el botón destacado. Se guarda en
 * Settings('navigation'); sin guardar se usa defaults(). El resultado se
 * cachea con el resto del sitio (PublicSite) y se invalida al guardar.
 *
 * Cada elemento apunta a una página fija del sitio (`builtin`), a una
 * página propia (`page`, solo si está publicada), a una URL (`url`: ruta
 * interna, https://, mailto: o tel:) o es solo un grupo con submenú
 * (`group`).
 */
final class Navigation
{
    public const TYPES = ['builtin', 'page', 'url', 'group'];

    public const MAX_ITEMS = 10;

    public const MAX_CHILDREN = 12;

    private const ID_REGEX = '/^[a-z0-9-]{2,40}$/';

    /**
     * Páginas fijas del sitio: [etiqueta sugerida, ruta]. Todas aparecen
     * siempre: Catálogo e Inspiración muestran su propio estado vacío
     * mientras no haya contenido publicado.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const BUILTINS = [
        'inicio' => ['Inicio', '/'],
        'catalogo' => ['Catálogo', '/catalogo'],
        'inspiracion' => ['Inspiración', '/inspiracion'],
        'como-funciona' => ['Cómo funciona', '/#como-funciona'],
        'preguntas' => ['Preguntas frecuentes', '/preguntas-frecuentes'],
        'asesoria' => ['Asesoría por videollamada', '/solicitar?servicio=asesoria'],
        'agendar-visita' => ['Visita a domicilio', '/agendar-visita'],
        'solicitar' => ['Cotiza tu proyecto', '/solicitar'],
    ];

    /**
     * Menú predeterminado: Inicio · Catálogo · Inspiración · Cómo funciona ·
     * Servicios ▾ (asesoría, visita, cotización) · Preguntas frecuentes, con
     * «Agenda tu asesoría» como botón destacado.
     *
     * @return array{items: list<array<string, mixed>>, cta: array<string, mixed>}
     */
    public static function defaults(): array
    {
        $item = fn (string $key, ?string $id = null) => [
            'id' => $id ?? $key,
            'label' => self::BUILTINS[$key][0],
            'type' => 'builtin',
            'target' => $key,
            'newTab' => false,
            'visible' => true,
            'children' => [],
        ];

        return [
            'items' => [
                $item('inicio'),
                $item('catalogo'),
                $item('inspiracion'),
                $item('como-funciona'),
                [
                    'id' => 'servicios',
                    'label' => 'Servicios',
                    'type' => 'group',
                    'target' => '',
                    'newTab' => false,
                    'visible' => true,
                    'children' => [
                        $item('asesoria', 'servicio-asesoria'),
                        $item('agendar-visita', 'servicio-visita'),
                        $item('solicitar', 'servicio-cotizacion'),
                    ],
                ],
                $item('preguntas'),
            ],
            'cta' => ['visible' => true, 'label' => 'Agenda tu asesoría', 'type' => 'builtin', 'target' => 'asesoria', 'newTab' => false],
        ];
    }

    /**
     * Menú guardado (o el predeterminado), tal como lo edita el panel.
     *
     * @return array{items: list<array<string, mixed>>, cta: array<string, mixed>, customized: bool}
     */
    public static function stored(): array
    {
        $stored = Settings::group('navigation');
        $defaults = self::defaults();
        $items = is_array($stored['items'] ?? null)
            ? array_values(array_filter($stored['items'], 'is_array'))
            : null;
        $cta = is_array($stored['cta'] ?? null) ? $stored['cta'] : null;

        return [
            'items' => $items ?? $defaults['items'],
            'cta' => $cta ?? $defaults['cta'],
            'customized' => $items !== null || $cta !== null,
        ];
    }

    /**
     * Valida y normaliza el menú enviado por el panel.
     *
     * @param  array<string, mixed>  $input
     * @return array{items: list<array<string, mixed>>, cta: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public static function validate(array $input): array
    {
        $errors = [];
        $pages = array_values(SitePage::query()->pluck('id')->map(fn ($id) => (string) $id)->all());

        $items = $input['items'] ?? [];
        if (! is_array($items) || ! array_is_list($items)) {
            throw ValidationException::withMessages(['items' => ['El menú no es válido.']]);
        }
        if (count($items) > self::MAX_ITEMS) {
            $errors['items'] = ['El menú admite hasta '.self::MAX_ITEMS.' enlaces principales; agrupa los demás en un submenú.'];
        }

        $ids = [];
        $out = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS) as $i => $item) {
            $clean = self::item($item, "items.$i", true, $pages, $errors, $ids);
            if ($clean !== null) {
                $out[] = $clean;
            }
        }

        $ctaInput = is_array($input['cta'] ?? null) ? $input['cta'] : [];
        $ctaVisible = ($ctaInput['visible'] ?? true) === true;
        $cta = ['visible' => $ctaVisible, 'label' => '', 'type' => 'builtin', 'target' => 'solicitar', 'newTab' => false];
        if ($ctaVisible || trim((string) ($ctaInput['label'] ?? '')) !== '') {
            $dummy = [];
            $clean = self::item([...$ctaInput, 'id' => 'cta', 'children' => []], 'cta', false, $pages, $errors, $dummy);
            if ($clean !== null) {
                if ($clean['type'] === 'group') {
                    $errors['cta.type'] = ['El botón destacado debe llevar a una página o enlace.'];
                }
                $cta = ['visible' => $ctaVisible, 'label' => $clean['label'], 'type' => $clean['type'], 'target' => $clean['target'], 'newTab' => $clean['newTab']];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['items' => $out, 'cta' => $cta];
    }

    /**
     * @param  list<string>  $pages
     * @param  array<string, list<string>>  $errors
     * @param  list<string>  $ids
     * @return array<string, mixed>|null
     */
    private static function item(mixed $item, string $p, bool $allowChildren, array $pages, array &$errors, array &$ids): ?array
    {
        if (! is_array($item)) {
            $errors[$p] = ['Enlace inválido.'];

            return null;
        }

        $id = is_string($item['id'] ?? null) && preg_match(self::ID_REGEX, $item['id']) === 1 ? $item['id'] : Str::lower(Str::random(10));
        if (in_array($id, $ids, true)) {
            $id = Str::lower(Str::random(10));
        }
        $ids[] = $id;

        $label = trim(preg_replace('/\s+/u', ' ', strip_tags((string) (is_scalar($item['label'] ?? null) ? $item['label'] : ''))) ?? '');
        if ($label === '') {
            $errors["$p.label"] = ['Escribe el texto del enlace.'];
        } elseif (mb_strlen($label) > 40) {
            $errors["$p.label"] = ['Máximo 40 caracteres: en la cabecera debe caber en una línea.'];
            $label = mb_substr($label, 0, 40);
        }

        $type = is_string($item['type'] ?? null) && in_array($item['type'], self::TYPES, true) ? $item['type'] : null;
        if ($type === null) {
            $errors["$p.type"] = ['Elige a dónde lleva el enlace.'];
            $type = 'url';
        }
        if ($type === 'group' && ! $allowChildren) {
            $errors["$p.type"] = ['Un submenú no puede tener otro submenú.'];
        }

        $target = is_scalar($item['target'] ?? null) ? trim((string) $item['target']) : '';
        switch ($type) {
            case 'builtin':
                if (! array_key_exists($target, self::BUILTINS)) {
                    $errors["$p.target"] = ['Elige una página del sitio.'];
                }
                break;
            case 'page':
                if (! in_array($target, $pages, true)) {
                    $errors["$p.target"] = ['Elige una de tus páginas.'];
                }
                break;
            case 'url':
                if ($target === '' || mb_strlen($target) > 300 || preg_match(Field::SAFE_LINK_REGEX, $target) !== 1) {
                    $errors["$p.target"] = ['Usa una ruta interna (p. ej. /cocinas) o un enlace https://, mailto: o tel:.'];
                }
                break;
            default:
                $target = '';
        }

        $children = [];
        $rawChildren = $item['children'] ?? [];
        if ($allowChildren && is_array($rawChildren) && $rawChildren !== []) {
            if (count($rawChildren) > self::MAX_CHILDREN) {
                $errors["$p.children"] = ['Un submenú admite hasta '.self::MAX_CHILDREN.' enlaces.'];
            }
            foreach (array_slice(array_values($rawChildren), 0, self::MAX_CHILDREN) as $j => $child) {
                $clean = self::item($child, "$p.children.$j", false, $pages, $errors, $ids);
                if ($clean !== null) {
                    $children[] = $clean;
                }
            }
        }
        if ($type === 'group' && $children === []) {
            $errors["$p.children"] = ['Un submenú necesita al menos un enlace.'];
        }

        return [
            'id' => $id,
            'label' => $label,
            'type' => $type,
            'target' => $target,
            'newTab' => ($item['newTab'] ?? false) === true,
            'visible' => ($item['visible'] ?? true) === true,
            'children' => $children,
        ];
    }

    /**
     * Menú listo para la cabecera pública: solo enlaces visibles y con
     * destino disponible.
     *
     * @return array{links: list<array<string, mixed>>, cta: array<string, mixed>|null}
     */
    public static function resolve(): array
    {
        $stored = self::stored();
        $pageIds = [];
        foreach ([...$stored['items'], ...array_merge(...array_map(fn ($i) => is_array($i['children'] ?? null) ? $i['children'] : [], $stored['items'])), $stored['cta']] as $item) {
            if (($item['type'] ?? null) === 'page' && is_numeric($item['target'] ?? null)) {
                $pageIds[] = (int) $item['target'];
            }
        }
        $pages = $pageIds !== []
            ? SitePage::query()->published()->whereIn('id', array_unique($pageIds))->pluck('slug', 'id')->all()
            : [];

        $href = function (array $item) use ($pages): ?string {
            $target = (string) ($item['target'] ?? '');

            return match ($item['type'] ?? null) {
                'builtin' => self::BUILTINS[$target][1] ?? null,
                'page' => isset($pages[(int) $target]) ? '/'.$pages[(int) $target] : null,
                'url' => $target !== '' && preg_match(Field::SAFE_LINK_REGEX, $target) === 1 ? $target : null,
                default => null,
            };
        };

        $link = function (array $item) use ($href): ?array {
            if (($item['visible'] ?? true) !== true) {
                return null;
            }
            $url = $href($item);
            if ($url === null) {
                return null;
            }

            return [
                'id' => (string) ($item['id'] ?? Str::random(8)),
                'label' => (string) ($item['label'] ?? ''),
                'href' => $url,
                'external' => self::isExternal($url),
                'newTab' => ($item['newTab'] ?? false) === true,
            ];
        };

        $links = [];
        foreach ($stored['items'] as $item) {
            if (($item['visible'] ?? true) !== true) {
                continue;
            }
            $children = array_values(array_filter(array_map($link, is_array($item['children'] ?? null) ? $item['children'] : [])));
            if (($item['type'] ?? null) === 'group') {
                if ($children === []) {
                    continue;
                }
                $links[] = ['id' => (string) $item['id'], 'label' => (string) $item['label'], 'href' => null, 'external' => false, 'newTab' => false, 'children' => $children];

                continue;
            }
            $self = $link($item);
            if ($self === null) {
                continue;
            }
            $links[] = [...$self, 'children' => $children];
        }

        $cta = null;
        if (($stored['cta']['visible'] ?? true) === true) {
            $resolved = $link([...$stored['cta'], 'id' => 'cta', 'visible' => true]);
            if ($resolved !== null) {
                $cta = ['label' => $resolved['label'], 'href' => $resolved['href'], 'external' => $resolved['external'], 'newTab' => $resolved['newTab']];
            }
        }

        return ['links' => $links, 'cta' => $cta];
    }

    public static function isExternal(string $href): bool
    {
        return ! str_starts_with($href, '/');
    }

    /**
     * Opciones del panel: páginas fijas del sitio.
     *
     * @return list<array{value: string, label: string, href: string}>
     */
    public static function builtinOptions(): array
    {
        $out = [];
        foreach (self::BUILTINS as $key => [$label, $href]) {
            $out[] = ['value' => $key, 'label' => $label, 'href' => $href];
        }

        return $out;
    }
}
