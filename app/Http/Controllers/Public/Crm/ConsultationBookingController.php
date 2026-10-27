<?php

namespace App\Http\Controllers\Public\Crm;

use App\Enums\ConsultationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\BookConsultationRequest;
use App\Models\Consultation;
use App\Models\Lead;
use App\Services\Crm\ConsultationScheduler;
use App\Services\Scheduling\SlotUnavailableException;
use App\Services\Web\Seo;
use App\Support\Crm\CrmLinks;
use App\Support\Crm\Present;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Autoagenda de la asesoría remota (enlace firmado por prospecto) y
 * pantalla para reprogramar o cancelar (enlace firmado por asesoría).
 */
class ConsultationBookingController extends Controller
{
    public function __construct(private readonly ConsultationScheduler $scheduler) {}

    public function create(Lead $lead): Response|RedirectResponse
    {
        $upcoming = $lead->consultations()->active()->where('starts_at', '>', now())->orderBy('starts_at')->first();
        if ($upcoming) {
            return redirect()->to(CrmLinks::manageConsultation($upcoming));
        }

        $lead->loadMissing('customer');
        $settings = $this->scheduler->settings();
        [$from, $until] = $this->scheduler->window();

        return Inertia::render('public/asesoria/Create', [
            'seo' => app(Seo::class)->private('Agenda tu asesoría'),
            'lead' => ['folio' => $lead->folio, 'firstName' => $lead->customer->first_name],
            'settings' => $settings->toArray(),
            'window' => ['min' => $from->toDateString(), 'max' => $until->toDateString()],
            'availabilityUrl' => route('public.asesoria.disponibilidad'),
            'submitUrl' => URL::signedRoute('public.asesoria.store', ['lead' => $lead->uuid]),
        ]);
    }

    /** Fechas disponibles del mes (?mes=YYYY-MM) u horarios de un día (?fecha=YYYY-MM-DD). */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($data['fecha'])) {
            $slots = $this->scheduler->slotsForDate(CarbonImmutable::parse($data['fecha']));

            return response()->json(['slots' => array_map(fn ($s) => ['value' => $s['starts_at'], 'time' => $s['time']], $slots)]);
        }

        $month = CarbonImmutable::parse(($data['mes'] ?? now()->format('Y-m')).'-01');

        return response()->json(['dates' => $this->scheduler->availableDates($month->year, $month->month)]);
    }

    public function store(BookConsultationRequest $request, Lead $lead): RedirectResponse
    {
        try {
            $consultation = $this->scheduler->book(
                $lead,
                $request->startsAt(),
                $request->channel() ?? throw ValidationException::withMessages(['channel' => 'Elige cómo prefieres tu asesoría.']),
                $request->validated('notes'),
            );
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        return redirect()->to(CrmLinks::manageConsultation($consultation))
            ->with('success', '¡Listo! Tu asesoría quedó agendada. Te enviamos los detalles por correo.');
    }

    public function show(Consultation $consultation): Response|RedirectResponse
    {
        // Si se reprogramó, se muestra la cita vigente.
        $latest = $this->latest($consultation);
        if ($latest->id !== $consultation->id) {
            return redirect()->to(CrmLinks::manageConsultation($latest));
        }

        $consultation->loadMissing(['lead.customer', 'advisor']);
        $settings = $this->scheduler->settings();
        [$from, $until] = $this->scheduler->window();
        $canChange = $consultation->status->isActive() && $consultation->starts_at->isFuture();

        return Inertia::render('public/asesoria/Show', [
            'seo' => app(Seo::class)->private('Tu asesoría'),
            'consultation' => [
                ...Present::consultation($consultation),
                // El cliente solo ve el nombre de pila de su asesor.
                'advisor' => ['name' => explode(' ', trim($consultation->advisor->name))[0]],
                'durationMinutes' => (int) $consultation->starts_at->diffInMinutes($consultation->ends_at),
            ],
            'lead' => ['folio' => $consultation->lead->folio, 'firstName' => $consultation->lead->customer->first_name],
            'canChange' => $canChange,
            'settings' => $settings->toArray(),
            'window' => ['min' => $from->toDateString(), 'max' => $until->toDateString()],
            'availabilityUrl' => route('public.asesoria.disponibilidad'),
            'rescheduleUrl' => URL::signedRoute('public.asesoria.reprogramar', ['consultation' => $consultation->uuid]),
            'cancelUrl' => URL::signedRoute('public.asesoria.cancelar', ['consultation' => $consultation->uuid]),
            'bookAgainUrl' => $consultation->status === ConsultationStatus::Cancelled && $settings->enabled ? CrmLinks::bookConsultation($consultation->lead) : null,
        ]);
    }

    public function reschedule(BookConsultationRequest $request, Consultation $consultation): RedirectResponse
    {
        if (! $consultation->status->isActive() || ! $consultation->starts_at->isFuture()) {
            throw ValidationException::withMessages(['starts_at' => 'Esta asesoría ya no se puede reprogramar desde aquí. Escríbenos y te ayudamos.']);
        }

        try {
            $new = $this->scheduler->reschedule($consultation, $request->startsAt(), null, false, $request->channel());
        } catch (SlotUnavailableException $e) {
            throw ValidationException::withMessages(['starts_at' => $e->getMessage()]);
        }

        return redirect()->to(CrmLinks::manageConsultation($new))
            ->with('success', 'Reprogramamos tu asesoría. Te enviamos la nueva fecha por correo.');
    }

    public function cancel(Request $request, Consultation $consultation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        if ($consultation->status->isActive() && ! $consultation->starts_at->isFuture()) {
            throw ValidationException::withMessages(['reason' => 'La asesoría ya comenzó; no es posible cancelarla desde aquí.']);
        }

        $this->scheduler->cancel($consultation, $data['reason'] ?? 'Cancelada por el cliente');

        return redirect()->to(CrmLinks::manageConsultation($consultation))
            ->with('success', 'Cancelamos tu asesoría. Cuando quieras, puedes agendar otra.');
    }

    private function latest(Consultation $consultation): Consultation
    {
        $current = $consultation;
        for ($i = 0; $i < 20 && $current->status === ConsultationStatus::Rescheduled; $i++) {
            $next = $current->rescheduledTo()->first();
            if (! $next) {
                break;
            }
            $current = $next;
        }

        return $current;
    }
}
