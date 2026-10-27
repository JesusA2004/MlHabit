<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tras iniciar sesión (con o sin verificación en dos pasos) cada rol llega a
 * su área: cliente → /portal, técnico → /tecnico, equipo → /admin. Si había
 * una URL "intended", solo se respeta cuando pertenece al área del rol (un
 * cliente nunca termina en una 403 de /admin).
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 200);
        }

        /** @var User $user */
        $user = $request->user();

        return redirect()->to(self::targetFor($user, $request));
    }

    public static function targetFor(User $user, Request $request): string
    {
        $home = $user->role->homePath();
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return $home;
        }

        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');

        $areas = ['/admin', '/portal', '/tecnico'];
        foreach ($areas as $area) {
            if ($path === $area || str_starts_with($path, $area.'/')) {
                return $area === $home ? $intended : $home;
            }
        }

        // Rutas comunes (cuenta, notificaciones, archivos privados…).
        return $intended;
    }
}
