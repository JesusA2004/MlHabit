<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Http\Controllers\Portal\Support\PortalFeed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mis solicitudes": lo que la persona pidió en /solicitar (o le registró
 * su asesor), con su avance y el siguiente paso. Solo lectura: los cambios
 * pasan por los enlaces firmados del CRM (agendar/mover asesoría, visita).
 */
class RequestController extends Controller
{
    use ResolvesPortalCustomer;

    public function index(Request $request): Response
    {
        return Inertia::render('portal/Solicitudes', [
            'requests' => PortalFeed::requests($this->customerIds($request)),
            'stages' => PortalFeed::REQUEST_STAGES,
            'newRequestUrl' => '/solicitar',
        ]);
    }
}
