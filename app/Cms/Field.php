<?php

namespace App\Cms;

use App\Enums\FaqTopic;
use App\Enums\MediaVisibility;
use App\Models\Media;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Campo tipado del contenido de una sección del CMS. Define a la vez la
 * validación (backend) y el descriptor que usa el editor del panel para
 * dibujar el control adecuado.
 */
final class Field
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const LINK = 'link';

    public const MEDIA = 'media';

    public const NUMBER = 'number';

    public const TOGGLE = 'toggle';

    public const LIST = 'list';

    public const TOPICS = 'topics';

    /** Enlaces seguros: ruta interna, https, mailto o tel. */
    public const SAFE_LINK_REGEX = '/^(\/(?!\/)[^\s]*|https:\/\/[^\s]+|mailto:[^\s]+|tel:[^\s]+)$/i';

    /**
     * @param  list<Field>  $itemFields
     */
    private function __construct(
        public readonly string $key,
        public readonly string $kind,
        public readonly string $label,
        public readonly mixed $default = null,
        public readonly bool $required = false,
        public readonly int $max = 255,
        public readonly int $min = 0,
        public readonly ?string $help = null,
        public readonly array $itemFields = [],
    ) {}

    public static function text(string $key, string $label, string $default = '', bool $required = false, int $max = 160, ?string $help = null): self
    {
        return new self($key, self::TEXT, $label, $default, $required, $max, 0, $help);
    }

    public static function textarea(string $key, string $label, string $default = '', bool $required = false, int $max = 600, ?string $help = null): self
    {
        return new self($key, self::TEXTAREA, $label, $default, $required, $max, 0, $help);
    }

    public static function link(string $key, string $label, string $default = '', bool $required = false, ?string $help = null): self
    {
        return new self($key, self::LINK, $label, $default, $required, 300, 0, $help ?? 'Ruta interna (p. ej. /solicitar) o enlace https://');
    }

    public static function media(string $key, string $label, ?string $help = null): self
    {
        return new self($key, self::MEDIA, $label, null, false, 0, 0, $help);
    }

    public static function number(string $key, string $label, int $default, int $min, int $max, ?string $help = null): self
    {
        return new self($key, self::NUMBER, $label, $default, true, $max, $min, $help);
    }

    public static function toggle(string $key, string $label, bool $default, ?string $help = null): self
    {
        return new self($key, self::TOGGLE, $label, $default, false, 0, 0, $help);
    }

    public static function topics(string $key, string $label, ?string $help = null): self
    {
        return new self($key, self::TOPICS, $label, [], false, 0, 0, $help);
    }

    /**
     * @param  list<Field>  $itemFields
     * @param  list<array<string, mixed>>  $default
     */
    public static function list(string $key, string $label, array $itemFields, array $default = [], int $min = 0, int $max = 12, ?string $help = null): self
    {
        return new self($key, self::LIST, $label, $default, $min > 0, $max, $min, $help, $itemFields);
    }

    /**
     * Reglas de Laravel para este campo bajo el prefijo dado.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(string $prefix = ''): array
    {
        $name = $prefix.$this->key;
        $presence = $this->required ? 'required' : 'nullable';

        return match ($this->kind) {
            self::TEXT, self::TEXTAREA => [$name => [$presence, 'string', 'max:'.$this->max]],
            self::LINK => [$name => [$presence, 'string', 'max:300', 'regex:'.self::SAFE_LINK_REGEX]],
            self::MEDIA => [$name => ['nullable', 'integer', self::publicImageRule()]],
            self::NUMBER => [$name => ['required', 'integer', 'min:'.$this->min, 'max:'.$this->max]],
            self::TOGGLE => [$name => ['boolean']],
            self::TOPICS => [
                $name => ['array'],
                $name.'.*' => ['string', 'distinct', Rule::in(array_column(FaqTopic::cases(), 'value'))],
            ],
            self::LIST => array_merge(
                [$name => [$this->required ? 'required' : 'present', 'array', 'min:'.$this->min, 'max:'.$this->max]],
                ...array_map(fn (Field $item) => $item->rules($name.'.*.'), $this->itemFields),
            ),
            default => [],
        };
    }

    /** Valor normalizado (solo claves conocidas, tipos correctos). */
    public function normalize(mixed $value): mixed
    {
        return match ($this->kind) {
            self::TEXT, self::TEXTAREA, self::LINK => is_scalar($value) ? trim((string) $value) : (string) $this->default,
            self::MEDIA => is_numeric($value) && (int) $value > 0 ? (int) $value : null,
            self::NUMBER => is_numeric($value) ? max($this->min, min($this->max, (int) $value)) : $this->default,
            self::TOGGLE => is_bool($value) ? $value : (is_scalar($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : (bool) $this->default),
            self::TOPICS => is_array($value) ? array_values(array_unique(array_filter($value, fn ($t) => is_string($t) && FaqTopic::tryFrom($t) !== null))) : [],
            self::LIST => is_array($value)
                ? array_values(array_map(
                    fn (array $item) => $this->normalizeItem($item),
                    array_filter($value, 'is_array'),
                ))
                : (array) $this->default,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        $out = [];
        foreach ($this->itemFields as $field) {
            $out[$field->key] = $field->normalize($item[$field->key] ?? $field->default);
        }

        return $out;
    }

    /**
     * Descriptor para el editor del panel.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'kind' => $this->kind,
            'label' => $this->label,
            'help' => $this->help,
            'required' => $this->required,
            'max' => in_array($this->kind, [self::TEXT, self::TEXTAREA, self::NUMBER, self::LIST], true) ? $this->max : null,
            'min' => in_array($this->kind, [self::NUMBER, self::LIST], true) ? $this->min : null,
            'itemFields' => $this->itemFields !== [] ? array_map(fn (Field $f) => $f->toArray(), $this->itemFields) : null,
            'options' => $this->kind === self::TOPICS ? FaqTopic::options() : null,
        ], fn ($v) => $v !== null);
    }

    /** Solo imágenes públicas y listas de la biblioteca. */
    public static function publicImageRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $ok = Media::query()
                ->whereKey((int) $value)
                ->where('visibility', MediaVisibility::Public->value)
                ->where('type', 'image')
                ->exists();

            if (! $ok) {
                $fail('Elige una imagen pública de la biblioteca multimedia.');
            }
        };
    }
}
