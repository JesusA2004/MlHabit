<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Abre una invitación firmada (72 h). Si la cuenta sigue activa y nunca ha
 * iniciado sesión, genera un token de restablecimiento nuevo y muestra
 * "Activa tu cuenta"; el formulario envía a Fortify (POST /reset-password),
 * que guarda la contraseña y marca el correo como verificado.
 */
class InvitationController extends Controller
{
    public function __invoke(Request $request, User $user): Response|RedirectResponse
    {
        if (! $user->is_active) {
            return redirect()->route('login')->with('status', 'Esta invitación ya no es válida. Contacta a tu administrador.');
        }

        if ($user->last_login_at !== null) {
            return redirect()->route('login')->with('status', 'Tu cuenta ya está activa. Inicia sesión con tu correo.');
        }

        // Si alguien más tenía la sesión abierta en este navegador, se cierra.
        if (Auth::check() && Auth::id() !== $user->getKey()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $token = Password::broker(config('fortify.passwords'))->createToken($user);

        return Inertia::render('auth/AcceptInvitation', [
            'email' => $user->email,
            'token' => $token,
            'name' => $user->name,
            'action' => route('password.update', absolute: false),
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }
}
