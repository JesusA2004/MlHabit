<?php

namespace App\Services\Crm;

use App\Enums\UserRole;
use App\Models\CoveragePostalCode;
use App\Models\CoverageZone;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Administración de la cobertura OPERATIVA (zonas, códigos postales y
 * técnicos). Un CP pertenece a una sola zona (UNIQUE global).
 */
class CoverageZoneService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Separa un texto pegado (comas, espacios, saltos de línea, Excel) en
     * códigos de 5 dígitos válidos y entradas inválidas.
     *
     * @return array{valid: list<string>, invalid: list<string>}
     */
    public static function parse(string $raw): array
    {
        $tokens = preg_split('/[\s,;|]+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $valid = [];
        $invalid = [];

        foreach ($tokens as $token) {
            if (! ctype_digit($token)) {
                $invalid[] = $token;

                continue;
            }
            // Excel suele quitar el cero inicial (01234 → 1234).
            $code = strlen($token) === 4 ? '0'.$token : $token;
            if (strlen($code) === 5) {
                $valid[$code] = $code;
            } else {
                $invalid[] = $token;
            }
        }

        return ['valid' => array_values($valid), 'invalid' => array_values(array_unique($invalid))];
    }

    /**
     * Agrega códigos a una zona. Los que ya pertenecen a otra zona se
     * reportan como conflicto (no se mueven en silencio) salvo `$move`.
     *
     * @return array{added: list<string>, existing: list<string>, conflicts: list<array{postal_code: string, zone_id: int, zone: string}>, invalid: list<string>, moved: list<string>}
     */
    public function addPostalCodes(CoverageZone $zone, string $raw, bool $move = false): array
    {
        ['valid' => $codes, 'invalid' => $invalid] = self::parse($raw);

        if ($codes === [] && $invalid === []) {
            throw ValidationException::withMessages(['postal_codes' => 'Pega al menos un código postal de 5 dígitos.']);
        }

        return DB::transaction(function () use ($zone, $codes, $invalid, $move) {
            $current = CoveragePostalCode::query()->whereIn('postal_code', $codes)->with('zone:id,name')->lockForUpdate()->get()->keyBy('postal_code');

            $added = [];
            $existing = [];
            $conflicts = [];
            $moved = [];

            foreach ($codes as $code) {
                $row = $current->get($code);
                if ($row === null) {
                    CoveragePostalCode::query()->create(['coverage_zone_id' => $zone->id, 'postal_code' => $code]);
                    $added[] = $code;
                } elseif ($row->coverage_zone_id === $zone->id) {
                    $existing[] = $code;
                } elseif ($move) {
                    $row->update(['coverage_zone_id' => $zone->id]);
                    $moved[] = $code;
                } else {
                    $conflicts[] = ['postal_code' => $code, 'zone_id' => $row->coverage_zone_id, 'zone' => $row->zone->name];
                }
            }

            if ($added !== [] || $moved !== []) {
                $this->logger->log('cobertura.codigos_agregados', $zone, ['added' => $added, 'moved' => $moved]);
            }

            return compact('added', 'existing', 'conflicts', 'invalid', 'moved');
        });
    }

    /** @param list<string> $codes */
    public function removePostalCodes(CoverageZone $zone, array $codes): int
    {
        $deleted = CoveragePostalCode::query()->where('coverage_zone_id', $zone->id)->whereIn('postal_code', $codes)->delete();
        if ($deleted > 0) {
            $this->logger->log('cobertura.codigos_eliminados', $zone, ['removed' => $codes]);
        }

        return $deleted;
    }

    /** @param list<int> $userIds */
    public function syncTechnicians(CoverageZone $zone, array $userIds): void
    {
        $valid = User::query()->role(UserRole::Technician)->whereIn('id', $userIds)->pluck('id')->all();
        if (count($valid) !== count(array_unique($userIds))) {
            throw ValidationException::withMessages(['technician_ids' => 'Solo puedes asignar usuarios con rol técnico.']);
        }

        $before = $zone->technicians()->pluck('users.id')->all();
        $zone->technicians()->sync($valid);
        $this->logger->log('cobertura.tecnicos', $zone, ['before' => ['technicians' => $before], 'after' => ['technicians' => $valid]]);
    }
}
