<?php

namespace App\Cms\Sections;

use App\Cms\Field;

/**
 * Esquema construido en tiempo de ejecución: una parte de otro esquema
 * (datos de un bloque dinámico) o un esquema propio de un bloque.
 */
final class SubsetSchema extends SectionSchema
{
    /**
     * @param  list<Field>  $fields
     * @param  array<string, list<mixed>>  $extra
     * @param  array<string, string>  $messages
     */
    public function __construct(
        private readonly string $label,
        private readonly string $description,
        private readonly array $fields,
        private readonly array $extra = [],
        private readonly array $messages = [],
    ) {}

    public function label(): string
    {
        return $this->label;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    protected function extraRules(): array
    {
        return $this->extra;
    }

    protected function messages(): array
    {
        return $this->messages;
    }
}
