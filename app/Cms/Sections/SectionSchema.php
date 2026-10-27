<?php

namespace App\Cms\Sections;

use App\Cms\Field;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Esquema de contenido de un tipo de sección de la portada: campos,
 * valores por defecto (texto honesto y neutral) y validación. El contenido
 * se guarda como JSON en site_sections.content y siempre pasa por aquí.
 */
abstract class SectionSchema
{
    abstract public function label(): string;

    abstract public function description(): string;

    /** @return list<Field> */
    abstract public function fields(): array;

    /**
     * Reglas adicionales propias del tipo (p. ej. frases prohibidas).
     *
     * @return array<string, list<mixed>>
     */
    protected function extraRules(): array
    {
        return [];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        $out = [];
        foreach ($this->fields() as $field) {
            $out[$field->key] = $field->default;
        }

        return $out;
    }

    /** @return array<string, list<mixed>> */
    public function rules(string $prefix = ''): array
    {
        $rules = [];
        foreach ($this->fields() as $field) {
            $rules = [...$rules, ...$field->rules($prefix)];
        }

        foreach ($this->extraRules() as $key => $extra) {
            $rules[$prefix.$key] = [...($rules[$prefix.$key] ?? []), ...$extra];
        }

        return $rules;
    }

    /**
     * Mezcla con los valores por defecto y descarta claves desconocidas.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function normalize(array $content): array
    {
        $out = [];
        foreach ($this->fields() as $field) {
            $out[$field->key] = $field->normalize(array_key_exists($field->key, $content) ? $content[$field->key] : $field->default);
        }

        return $out;
    }

    /**
     * Valida y normaliza el contenido.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $content, string $errorPrefix = 'content.'): array
    {
        $validator = Validator::make($content, $this->rules(), $this->messages(), $this->attributeNames());

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $key => $messages) {
                $errors[$errorPrefix.$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        return $this->normalize($content);
    }

    /**
     * IDs de medios referenciados en el contenido (para uso/borrado seguro).
     *
     * @param  array<string, mixed>  $content
     * @return list<int>
     */
    public function mediaIds(array $content): array
    {
        $ids = [];
        foreach ($this->fields() as $field) {
            if ($field->kind === Field::MEDIA && is_numeric($content[$field->key] ?? null)) {
                $ids[] = (int) $content[$field->key];
            }
            if ($field->kind === Field::LIST && is_array($content[$field->key] ?? null)) {
                foreach ($content[$field->key] as $item) {
                    foreach ($field->itemFields as $itemField) {
                        if ($itemField->kind === Field::MEDIA && is_array($item) && is_numeric($item[$itemField->key] ?? null)) {
                            $ids[] = (int) $item[$itemField->key];
                        }
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Retira una referencia a un medio (tras un borrado forzado).
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function withoutMedia(array $content, int $mediaId): array
    {
        foreach ($this->fields() as $field) {
            if ($field->kind === Field::MEDIA && (int) ($content[$field->key] ?? 0) === $mediaId) {
                $content[$field->key] = null;
            }
            if ($field->kind === Field::LIST && is_array($content[$field->key] ?? null)) {
                foreach ($content[$field->key] as $i => $item) {
                    foreach ($field->itemFields as $itemField) {
                        if ($itemField->kind === Field::MEDIA && is_array($item) && (int) ($item[$itemField->key] ?? 0) === $mediaId) {
                            $content[$field->key][$i][$itemField->key] = null;
                        }
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Esquema reducido a algunas claves: datos de un bloque dinámico del
     * editor visual (conserva reglas extra y mensajes de esas claves).
     *
     * @param  list<string>  $keys
     */
    public function only(array $keys, ?string $label = null): SubsetSchema
    {
        $fields = array_values(array_filter($this->fields(), fn (Field $f) => in_array($f->key, $keys, true)));
        $extra = array_filter(
            $this->extraRules(),
            fn (string $rule) => in_array(explode('.', $rule)[0], $keys, true),
            ARRAY_FILTER_USE_KEY,
        );

        return new SubsetSchema($label ?? $this->label(), $this->description(), $fields, $extra, $this->messages());
    }

    /**
     * Descriptor para el editor del panel.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label(),
            'description' => $this->description(),
            'fields' => array_map(fn (Field $f) => $f->toArray(), $this->fields()),
        ];
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        $names = [];
        foreach ($this->fields() as $field) {
            $names[$field->key] = mb_strtolower($field->label);
            foreach ($field->itemFields as $item) {
                $names[$field->key.'.*.'.$item->key] = mb_strtolower($item->label);
            }
        }

        return $names;
    }
}
