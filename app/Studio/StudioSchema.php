<?php

namespace App\Studio;

use RuntimeException;

/**
 * Acceso de solo lectura a resources/js/studio/schema.json: la misma
 * definición que usa el editor (TypeScript) es la que valida el backend,
 * así un tipo de bloque o una propiedad no puede existir en un lado y no
 * en el otro.
 */
class StudioSchema
{
    /** @var array<string, mixed>|null */
    private static ?array $schema = null;

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$schema === null) {
            $raw = file_get_contents(resource_path('js/studio/schema.json'));
            if ($raw === false) {
                throw new RuntimeException('No se pudo leer el esquema de Studio.');
            }
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            self::$schema = $decoded;
        }

        return self::$schema;
    }

    /**
     * Claves de un objeto JSON del esquema (json_decode convierte claves
     * numéricas en enteros; en el esquema todas son identificadores).
     *
     * @param  array<int|string, mixed>  $map
     * @return list<string>
     */
    public static function keys(array $map): array
    {
        return array_map(fn (int|string $key): string => (string) $key, array_keys($map));
    }

    public static function version(): int
    {
        return (int) self::all()['schemaVersion'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function section(string $type): ?array
    {
        return self::all()['sections'][$type] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function element(string $type): ?array
    {
        return self::all()['elements'][$type] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function elementTypes(): array
    {
        return self::keys(self::all()['elements']);
    }

    /**
     * @return list<string>
     */
    public static function slotAccepts(string $sectionType, string $slot): array
    {
        $accepts = self::section($sectionType)['slots'][$slot]['accepts'] ?? [];

        return $accepts === '*elements' ? self::elementTypes() : array_values($accepts);
    }

    /**
     * @return array<string, mixed>
     */
    public static function styleProps(): array
    {
        return self::all()['styleProps'];
    }

    /**
     * Claves de estilo permitidas para un tipo según sus grupos.
     *
     * @param  list<string>  $groups
     * @return array<string, array<string, mixed>>
     */
    public static function stylePropsForGroups(array $groups): array
    {
        return array_filter(self::styleProps(), fn (array $def) => in_array($def['group'], $groups, true));
    }

    /**
     * @return list<string>
     */
    public static function breakpoints(): array
    {
        return self::keys(self::all()['breakpoints']);
    }

    /**
     * @return list<string>
     */
    public static function colorTokens(): array
    {
        return self::keys(self::all()['colorTokens']);
    }

    /**
     * @return list<string>
     */
    public static function fonts(): array
    {
        return self::keys(self::all()['fonts']);
    }
}
