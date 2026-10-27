<?php

namespace App\Http\Controllers\Public\Crm;

use App\Enums\EmploymentType;
use App\Enums\IncomeRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\SubmitPrequalificationRequest;
use App\Models\Lead;
use App\Services\Crm\PrequalificationService;
use App\Support\Crm\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /solicitud/{lead}/precalificacion (firmado): el cliente comparte su
 * información para revisar pago a plazos. No es una aprobación.
 */
class PrequalificationRequestController extends Controller
{
    public function create(Lead $lead, PrequalificationService $service): Response
    {
        $lead->loadMissing('customer');
        $current = $service->current($lead);

        return Inertia::render('public/precalificacion/Create', [
            'lead' => ['folio' => $lead->folio, 'firstName' => $lead->customer->first_name],
            'prequalification' => $current ? [
                'folio' => $current->folio,
                'status' => ['value' => $current->status->value, 'label' => $current->status->customerLabel(), 'tone' => $current->status->tone()],
                'acceptsInput' => $current->status->acceptsCustomerInput(),
                'infoRequestedNote' => $current->status->value === 'info_requested' ? $current->decision_notes : null,
                'documentsCount' => $current->attachments()->where('role', 'document')->count(),
                'submittedAt' => $current->submitted_at?->toIso8601String(),
            ] : null,
            'options' => [
                'incomeRanges' => Options::of(IncomeRange::class),
                'employmentTypes' => Options::of(EmploymentType::class),
                'terms' => array_map(fn (int $m) => ['value' => (string) $m, 'label' => "{$m} meses"], SubmitPrequalificationRequest::TERMS),
            ],
            'submitUrl' => URL::signedRoute('public.precalificacion.store', ['lead' => $lead->uuid]),
        ]);
    }

    public function store(SubmitPrequalificationRequest $request, Lead $lead, PrequalificationService $service): RedirectResponse
    {
        /** @var list<UploadedFile> $documents */
        $documents = array_values(array_filter((array) $request->file('documents', []), fn ($f) => $f instanceof UploadedFile));

        $service->submitFromCustomer($lead, $request->safe()->except(['documents', 'consent']), $documents);

        return back()->with('success', 'Recibimos tu información. Nuestro equipo la revisará y te contactará.');
    }
}
