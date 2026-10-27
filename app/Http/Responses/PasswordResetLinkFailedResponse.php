<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * "¿Olvidaste tu contraseña?" no revela si un correo tiene cuenta: si el
 * correo no existe se responde igual que cuando el enlace se envió. Solo el
 * límite de reintentos (throttled) se muestra como error.
 */
class PasswordResetLinkFailedResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($this->status === Password::INVALID_USER) {
            return $request->wantsJson()
                ? new JsonResponse(['message' => trans(Password::RESET_LINK_SENT)], 200)
                : back()->with('status', trans(Password::RESET_LINK_SENT));
        }

        throw ValidationException::withMessages([
            Fortify::email() => [trans($this->status)],
        ]);
    }
}
