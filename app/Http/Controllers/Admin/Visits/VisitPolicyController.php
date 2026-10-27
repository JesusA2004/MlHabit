<?php

namespace App\Http\Controllers\Admin\Visits;

use App\Enums\VisitCreditType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Visits\StoreVisitPolicyRequest;
use App\Models\User;
use App\Models\VisitPolicy;
use App\Services\ActivityLogger;
use App\Services\Visits\VisitPresenter;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /admin/visitas/politica — versiones de la política de visita. Una versión
 * nunca se edita: se crea otra (inactiva) y se activa. Activar desactiva la
 * anterior en la misma transacción (solo una activa).
 */
class VisitPolicyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', VisitPolicy::class);

        $versions = VisitPolicy::query()->with('creator')->withCount('visits')->orderByDesc('version')->get();

        return Inertia::render('admin/visitas/Politica', [
            'versions' => $versions->map(fn (VisitPolicy $p) => [
                ...VisitPresenter::policy($p),
                'createdBy' => $p->creator?->name,
                'visitsCount' => (int) $p->getAttribute('visits_count'),
            ])->values(),
            'creditTypes' => VisitCreditType::options(),
            'can' => ['manage' => $request->user()?->can('create', VisitPolicy::class) ?? false],
        ]);
    }

    public function store(StoreVisitPolicyRequest $request, ActivityLogger $audit): RedirectResponse
    {
        Gate::authorize('create', VisitPolicy::class);

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $creditable = (bool) $data['is_creditable'];

        $policy = DB::transaction(function () use ($data, $creditable, $user) {
            // Bloqueo para numerar versiones sin colisiones.
            DB::table('visit_policies')->lockForUpdate()->max('version');

            return VisitPolicy::query()->create([
                'version' => VisitPolicy::nextVersion(),
                'fee_amount' => Money::of((string) $data['fee_amount'])->amount,
                'currency' => 'MXN',
                'is_creditable' => $creditable,
                'credit_type' => $creditable ? $data['credit_type'] : null,
                'credit_value' => $creditable ? Money::of((string) $data['credit_value'])->amount : null,
                'credit_validity_days' => $creditable ? (int) $data['credit_validity_days'] : null,
                'credit_conditions' => $data['credit_conditions'] ?? null,
                'duration_minutes' => (int) $data['duration_minutes'],
                'buffer_minutes' => (int) $data['buffer_minutes'],
                'hold_minutes' => (int) $data['hold_minutes'],
                'min_notice_hours' => (int) $data['min_notice_hours'],
                'booking_window_days' => (int) $data['booking_window_days'],
                'scope' => $data['scope'],
                'cancellation_policy' => $data['cancellation_policy'],
                'reschedule_policy' => $data['reschedule_policy'],
                'is_active' => false,
                'created_by' => $user->id,
            ]);
        });

        $audit->log('visita.politica_creada', $policy, ['version' => $policy->version, 'tarifa' => $policy->fee_amount]);

        return back()->with('success', "Versión {$policy->version} creada como borrador. Actívala cuando esté lista.");
    }

    public function activate(VisitPolicy $policy, ActivityLogger $audit): RedirectResponse
    {
        Gate::authorize('activate', $policy);

        $previous = DB::transaction(function () use ($policy) {
            $current = VisitPolicy::query()->where('is_active', true)->lockForUpdate()->get();

            VisitPolicy::query()->whereKeyNot($policy->id)->where('is_active', true)->update(['is_active' => false, 'updated_at' => now()]);
            VisitPolicy::query()->whereKey($policy->id)->update(['is_active' => true, 'activated_at' => now(), 'updated_at' => now()]);

            return $current->pluck('version')->all();
        });

        $audit->log('visita.politica_activada', $policy, ['version' => $policy->version, 'anteriores' => $previous]);

        return back()->with('success', "Versión {$policy->version} activa. Las visitas ya reservadas conservan su tarifa y condiciones.");
    }
}
