<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si la configuración `security.require_2fa_for_staff` está activa, el
 * personal interno debe tener confirmada la verificación en dos pasos para
 * entrar a /admin. Mientras no la active se le lleva a Cuenta → Seguridad.
 *
 * Se agrega al grupo `web` (bootstrap/app.php) y solo actúa sobre rutas
 * /admin, para no tocar los grupos de rutas de otros módulos.
 */
class EnsureTwoFactorForStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*')) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->isStaff() || $user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        if (! (bool) Settings::get('security.require_2fa_for_staff', false)) {
            return $next($request);
        }

        $message = 'Para entrar al panel necesitas activar la verificación en dos pasos.';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('security.edit')->with('error', $message);
    }
}
