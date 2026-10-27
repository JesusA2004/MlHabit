<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe un área a uno o más roles: ->middleware('role:cliente') o
 * 'role:tecnico,super_admin'.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $allowed = array_map(fn (string $r) => UserRole::from($r), $roles);

        if ($user === null || ! $user->is_active || ! in_array($user->role, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
