<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Genera folios legibles y estables: MLH-{PREFIJO}-{AÑO}-{00001}.
 * El consecutivo se incrementa bajo SELECT … FOR UPDATE, por lo que dos
 * peticiones simultáneas nunca obtienen el mismo número.
 *
 * Prefijos: L prospecto, A asesoría, V visita, Q precalificación,
 * P proyecto, C cotización, K contrato, G pago, F fabricación,
 * I instalación, X incidencia, W garantía, R reclamo de garantía,
 * S pago a proveedor.
 */
final class Folio
{
    public static function next(string $prefix): string
    {
        $year = (int) now()->format('Y');

        $number = DB::transaction(function () use ($prefix, $year): int {
            DB::table('folio_sequences')->insertOrIgnore([
                'prefix' => $prefix,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('folio_sequences')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $next = (int) $row->last_number + 1;

            DB::table('folio_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });

        return sprintf('MLH-%s-%d-%05d', $prefix, $year, $number);
    }
}
