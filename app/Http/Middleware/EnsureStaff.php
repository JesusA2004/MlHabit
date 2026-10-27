<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Solo personal interno activo entra al panel /admin. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->is_active || ! $user->isStaff()) {
            abort(403, 'Esta área es solo para el equipo de ML Habitat.');
        }

        return $next($request);
    }
}
