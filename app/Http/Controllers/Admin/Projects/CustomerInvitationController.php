<?php

namespace App\Http\Controllers\Admin\Projects;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Projects\PortalInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/** Invitar / reenviar invitación al portal (customers.manage). */
class CustomerInvitationController extends Controller
{
    public function store(Request $request, Customer $customer, PortalInvitationService $service): RedirectResponse
    {
        Gate::authorize('customers.manage');

        $key = 'portal-invite:'.$customer->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Ya enviamos varias invitaciones a este cliente. Intenta de nuevo en unos minutos.');
        }
        RateLimiter::hit($key, 600);

        $hadAccount = $customer->user_id !== null;
        $service->invite($customer, $request->user());

        return back()->with('success', $hadAccount
            ? "Reenviamos la invitación a {$customer->email}."
            : "Invitación enviada a {$customer->email}. El enlace vence en 72 horas.");
    }
}
