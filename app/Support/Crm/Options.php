<?php

namespace App\Support\Crm;

use BackedEnum;

/**
 * Serializa enums del CRM para el frontend: `[{value, label, tone?}]`.
 */
final class Options
{
    /**
     * @param  class-string<BackedEnum>|list<BackedEnum>  $enum
     * @return list<array{value: string, label: string, tone?: string}>
     */
    public static function of(string|array $enum): array
    {
        $cases = is_array($enum) ? $enum : $enum::cases();

        $out = [];
        foreach ($cases as $case) {
            $out[] = self::one($case);
        }

        return $out;
    }

    /**
     * @return array{value: string, label: string, tone?: string}
     */
    public static function one(BackedEnum $case): array
    {
        $row = ['value' => (string) $case->value, 'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value];
        if (method_exists($case, 'tone')) {
            $row['tone'] = $case->tone();
        }

        return $row;
    }

    /**
     * @return array{value: string, label: string, tone?: string}|null
     */
    public static function maybe(?BackedEnum $case): ?array
    {
        return $case ? self::one($case) : null;
    }
}
