<?php

namespace App\Http\Controllers\Admin\Crm;

use App\Enums\AvailabilityExceptionType;
use App\Enums\BookingKind;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\CalendarBooking;
use App\Models\Consultation;
use App\Models\User;
use App\Services\Crm\AgendaService;
use App\Services\Scheduling\SlotFinder;
use App\Support\Crm\Options;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/agenda: disponibilidad semanal, excepciones y vista de reservas
 * de asesores (asesorías) y técnicos (visitas).
 */
class AgendaController extends Controller
{
    private const KINDS = ['consultation', 'visit'];

    public function __construct(private readonly AgendaService $agenda) {}

    public function index(Request $request, SlotFinder $slots): Response
    {
        Gate::authorize('schedule.view');

        $data = $request->validate([
            'tipo' => ['nullable', Rule::in(self::KINDS)],
            'persona' => ['nullable', 'integer'],
            'semana' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $kind = BookingKind::from($data['tipo'] ?? 'consultation');
        $people = $this->people($kind);
        /** @var User $user */
        $user = $request->user();
        $person = isset($data['persona'])
            ? $people->firstWhere('id', (int) $data['persona'])
            : ($people->firstWhere('id', $user->id) ?? $people->first());

        $weekStart = CarbonImmutable::parse($data['semana'] ?? 'today')->startOfWeek(CarbonImmutable::MONDAY);
        $weekEnd = $weekStart->addDays(6)->endOfDay();

        $props = [
            'kind' => $kind->value,
            'kinds' => [
                ['value' => 'consultation', 'label' => 'Asesorías (asesores)'],
                ['value' => 'visit', 'label' => 'Visitas técnicas (técnicos)'],
            ],
            'people' => $people->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->values(),
            'person' => $person ? ['id' => $person->id, 'name' => $person->name] : null,
            'week' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
                'today' => CarbonImmutable::today()->toDateString(),
            ],
            'rules' => [],
            'effective' => ['from' => null, 'until' => null],
            'exceptions' => [],
            'days' => [],
            'exceptionTypes' => Options::of(AvailabilityExceptionType::class),
            'can' => ['manage' => $user->hasPermission(Permission::ScheduleManage)],
        ];

        if ($person) {
            /** @var Collection<int, AvailabilityRule> $rules */
            $rules = AvailabilityRule::query()->where('user_id', $person->id)->where('kind', $kind->value)
                ->orderBy('weekday')->orderBy('starts_at')->get();
            $props['rules'] = $rules->map(fn (AvailabilityRule $r) => [
                'id' => $r->id,
                'weekday' => $r->weekday,
                'startsAt' => substr($r->starts_at, 0, 5),
                'endsAt' => substr($r->ends_at, 0, 5),
                'isActive' => $r->is_active,
            ])->values();
            $first = $rules->first();
            $props['effective'] = [
                'from' => $first?->effective_from?->toDateString(),
                'until' => $first?->effective_until?->toDateString(),
            ];

            $props['exceptions'] = AvailabilityException::query()
                ->where('user_id', $person->id)
                ->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', $kind->value))
                ->where('ends_at', '>=', CarbonImmutable::today()->subDays(30))
                ->orderBy('starts_at')
                ->limit(100)
                ->get()
                ->map(fn (AvailabilityException $e) => [
                    'id' => $e->id,
                    'type' => Options::one($e->type),
                    'kind' => $e->kind?->value,
                    'startsAt' => $e->starts_at->toIso8601String(),
                    'endsAt' => $e->ends_at->toIso8601String(),
                    'reason' => $e->reason,
                    'isPast' => $e->ends_at->isPast(),
                ])->values();

            $bookings = CalendarBooking::query()
                ->where('user_id', $person->id)
                ->occupying()
                ->where('starts_at', '<=', $weekEnd)
                ->where('ends_at', '>=', $weekStart)
                ->with('bookable')
                ->orderBy('starts_at')
                ->get();

            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->addDays($i);
                $days[] = [
                    'date' => $day->toDateString(),
                    'windows' => array_map(fn (array $w) => [
                        'start' => $w[0]->format('H:i'),
                        'end' => $w[1]->format('H:i'),
                    ], $slots->windows($person, $kind, $day)),
                    'bookings' => $bookings
                        ->filter(fn (CalendarBooking $b) => $b->starts_at->isSameDay($day))
                        ->map(fn (CalendarBooking $b) => $this->presentBooking($b))
                        ->values()
                        ->all(),
                ];
            }
            $props['days'] = $days;
        }

        return Inertia::render('admin/agenda/Index', $props);
    }

    public function updateRules(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('schedule.manage');
        $data = $request->validate([
            'kind' => ['required', Rule::in(self::KINDS)],
            'ranges' => ['present', 'array', 'max:70'],
            'ranges.*.weekday' => ['required', 'integer', 'between:0,6'],
            'ranges.*.starts_at' => ['required', 'date_format:H:i'],
            'ranges.*.ends_at' => ['required', 'date_format:H:i'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'ranges.*.starts_at.date_format' => 'Usa el formato HH:MM.',
            'ranges.*.ends_at.date_format' => 'Usa el formato HH:MM.',
        ]);

        $kind = BookingKind::from($data['kind']);
        $this->ensureEligible($user, $kind);

        $this->agenda->replaceRules($user, $kind, $data['ranges'], $data['effective_from'] ?? null, $data['effective_until'] ?? null);

        return back()->with('success', "Horario de {$user->name} guardado.");
    }

    public function storeException(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('schedule.manage');
        $data = $this->validateException($request);
        $this->ensureEligible($user, isset($data['kind']) ? BookingKind::from($data['kind']) : null);

        /** @var User $actor */
        $actor = $request->user();
        $conflicts = $this->agenda->saveException($user, $data, $actor);

        return back()->with($conflicts > 0 ? 'error' : 'success', $conflicts > 0
            ? "Excepción guardada. Ojo: hay {$conflicts} cita(s) ya reservadas en ese periodo; reprográmalas o cancélalas."
            : 'Excepción guardada.');
    }

    public function updateException(Request $request, AvailabilityException $exception): RedirectResponse
    {
        Gate::authorize('schedule.manage');
        $data = $this->validateException($request);

        /** @var User $actor */
        $actor = $request->user();
        $conflicts = $this->agenda->saveException($exception->user, $data, $actor, $exception);

        return back()->with($conflicts > 0 ? 'error' : 'success', $conflicts > 0
            ? "Excepción actualizada. Hay {$conflicts} cita(s) reservadas en ese periodo."
            : 'Excepción actualizada.');
    }

    public function destroyException(AvailabilityException $exception): RedirectResponse
    {
        Gate::authorize('schedule.manage');
        $this->agenda->deleteException($exception);

        return back()->with('success', 'Excepción eliminada.');
    }

    /**
     * @return array{type: string, kind?: string|null, starts_at: string, ends_at: string, reason?: string|null}
     */
    private function validateException(Request $request): array
    {
        /** @var array{type: string, kind?: string|null, starts_at: string, ends_at: string, reason?: string|null} $data */
        $data = $request->validate([
            'type' => ['required', Rule::enum(AvailabilityExceptionType::class)],
            'kind' => ['nullable', Rule::in(self::KINDS)],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'ends_at' => ['required', 'date_format:Y-m-d H:i'],
            'reason' => ['nullable', 'string', 'max:200'],
        ], [
            'starts_at.required' => 'Indica cuándo empieza.',
            'ends_at.required' => 'Indica cuándo termina.',
        ]);

        return $data;
    }

    private function ensureEligible(User $user, ?BookingKind $kind): void
    {
        $eligible = match ($kind) {
            BookingKind::Consultation => $user->is_active && $user->hasPermission(Permission::ConsultationsManage),
            BookingKind::Visit => $user->is_active && $user->role === UserRole::Technician,
            default => $user->is_active && ($user->role === UserRole::Technician || $user->hasPermission(Permission::ConsultationsManage)),
        };

        abort_unless($eligible, 422, 'Esta persona no atiende esa agenda.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function people(BookingKind $kind): \Illuminate\Support\Collection
    {
        $query = $kind === BookingKind::Visit
            ? User::query()->role(UserRole::Technician)->where('is_active', true)
            : User::query()->withPermission(Permission::ConsultationsManage);

        return $query->orderBy('name')->get(['id', 'name', 'role', 'is_active']);
    }

    /** @return array<string, mixed> */
    private function presentBooking(CalendarBooking $booking): array
    {
        $bookable = $booking->bookable;
        $title = $booking->kind->label();
        $subtitle = null;
        $url = null;

        if ($bookable instanceof Consultation) {
            $bookable->loadMissing('lead.customer');
            $title = $bookable->lead->customer->full_name;
            $subtitle = 'Asesoría · '.$bookable->channel->label();
            $url = route('admin.asesorias.show', $bookable);
        } elseif ($bookable !== null && isset($bookable->folio)) {
            $subtitle = $booking->kind->label().' · '.$bookable->folio;
            if ($booking->kind === BookingKind::Visit && Route::has('admin.visitas.show') && isset($bookable->uuid)) {
                $url = route('admin.visitas.show', $bookable->uuid);
            }
        }

        return [
            'id' => $booking->id,
            'start' => $booking->starts_at->format('H:i'),
            'end' => $booking->ends_at->format('H:i'),
            'startsAt' => $booking->starts_at->toIso8601String(),
            'endsAt' => $booking->ends_at->toIso8601String(),
            'held' => $booking->status->value === 'held',
            'title' => $title,
            'subtitle' => $subtitle,
            'url' => $url,
        ];
    }
}
