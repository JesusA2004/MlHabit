<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Models\Quote;
use App\Support\Settings;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vista imprimible (CSS de impresión del navegador → PDF). Accesible para
 * el equipo con permiso y para el cliente dueño (QuotePolicy::view).
 */
class QuotePrintController extends Controller
{
    public function __invoke(Quote $quote): Response
    {
        Gate::authorize('view', $quote);

        $quote->load(['items', 'project.customer', 'project.address', 'project.advisor:id,name,email,phone', 'designVersion:id,number,title']);
        $project = $quote->project;
        $staff = (bool) request()->user()?->isStaff();

        return Inertia::render('proyectos/CotizacionImprimible', [
            'quote' => P::quote($quote, forClient: ! $staff),
            'project' => [
                'folio' => $project->folio,
                'name' => $project->name,
                'customer' => $project->customer->full_name,
                'customer_email' => $project->customer->email,
                'customer_phone' => $project->customer->phone,
                'address' => $project->address?->one_line,
                'advisor' => $project->advisor ? ['name' => $project->advisor->name, 'email' => $project->advisor->email, 'phone' => $project->advisor->phone] : null,
            ],
            'company' => [
                'legal_name' => Settings::get('footer.legal_name'),
                'phone' => Settings::get('contact.phone'),
                'email' => Settings::get('contact.email'),
                'address' => Settings::get('contact.address'),
            ],
            'backUrl' => $staff ? route('admin.cotizaciones.edit', $quote) : route('portal.cotizaciones.show', $quote),
        ]);
    }
}
