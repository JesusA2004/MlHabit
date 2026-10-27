<?php

namespace App\Services\Operations;

use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Altas, evaluaciones y calificación de proveedores. La calificación es
 * el promedio de todas las puntuaciones (calidad, puntualidad,
 * comunicación) con aritmética decimal exacta y redondeo half-up a 2
 * decimales (nunca float).
 */
class SupplierService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $zoneIds
     */
    public function save(?Supplier $supplier, array $data, array $zoneIds): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $zoneIds) {
            $supplier ??= new Supplier;
            $creating = ! $supplier->exists;

            $supplier->fill($data);
            if (! $creating) {
                $this->logger->logModelChanges('proveedor.actualizado', $supplier);
            }
            $supplier->save();
            $supplier->coverageZones()->sync($zoneIds);

            if ($creating) {
                $this->logger->log('proveedor.creado', $supplier, ['after' => ['name' => $supplier->name, 'type' => $supplier->type->value]]);
            }

            return $supplier;
        });
    }

    /**
     * @param  array{quality_score: int, timeliness_score: int, communication_score: int, comments?: string|null, project_id?: int|null}  $data
     */
    public function evaluate(Supplier $supplier, array $data, User $actor): SupplierEvaluation
    {
        return DB::transaction(function () use ($supplier, $data, $actor) {
            $evaluation = $supplier->evaluations()->create([
                ...$data,
                'evaluated_by' => $actor->id,
            ]);

            $this->recalculateRating($supplier);
            $this->logger->log('proveedor.evaluado', $supplier, ['evaluacion' => $evaluation->id, 'rating' => $supplier->rating]);

            return $evaluation;
        });
    }

    public function recalculateRating(Supplier $supplier): void
    {
        $row = DB::table('supplier_evaluations')
            ->where('supplier_id', $supplier->id)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(quality_score + timeliness_score + communication_score), 0) as total')
            ->first();

        $count = (int) ($row->n ?? 0);
        $supplier->rating = $count === 0 ? null : self::average((string) $row->total, $count * 3);
        $supplier->save();
    }

    /** Promedio exacto con redondeo half-up a 2 decimales. */
    public static function average(string $sum, int $divisor): string
    {
        $raw = bcdiv($sum, (string) $divisor, 6);

        return bcadd($raw, '0.005', 2);
    }
}
