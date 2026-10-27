<?php

namespace App\Http\Controllers\Public\Crm;

use App\Http\Controllers\Controller;
use App\Services\CoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /cobertura/verificar?cp=12345 — cobertura OPERATIVA en vivo para el
 * formulario de solicitud. Solo usa CoverageService.
 */
class CoverageCheckController extends Controller
{
    public function __invoke(Request $request, CoverageService $coverage): JsonResponse
    {
        $cp = preg_replace('/\D/', '', (string) $request->query('cp', ''));

        if ($cp === null || strlen($cp) !== 5) {
            return response()->json([
                'valid' => false,
                'covered' => false,
                'zone' => null,
                'visitsEnabled' => false,
                'message' => 'Escribe un código postal de 5 dígitos.',
            ], 422);
        }

        $zone = $coverage->zoneForPostalCode($cp);
        $visits = $zone !== null && $coverage->acceptsVisits($cp);

        return response()->json([
            'valid' => true,
            'covered' => $zone !== null,
            'zone' => $zone ? ['name' => $zone->name, 'municipality' => $zone->municipality, 'state' => $zone->state] : null,
            'visitsEnabled' => $visits,
            'message' => match (true) {
                $visits => '¡Buenas noticias! Tenemos cobertura en tu zona y podemos visitarte para tomar medidas.',
                $zone !== null => 'Atendemos tu zona. Un asesor te confirmará la fecha de visita.',
                default => 'Aún no tenemos cobertura confirmada para tu código postal, pero envía tu solicitud: revisamos cada caso y te contactamos.',
            },
        ])->header('Cache-Control', 'private, max-age=60');
    }
}
