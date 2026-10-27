<?php

namespace App\Exceptions;

use App\Services\PublicSiteDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Páginas de error con la marca de ML Habitat (componente Inertia
 * `errors/Error` con props {status, message?, site?}).
 *
 * - 403, 404, 419, 429 y 503 siempre se muestran con marca (no revelan
 *   información de depuración).
 * - 500 conserva la página de depuración de Laravel con APP_DEBUG=true.
 * - Peticiones JSON conservan la respuesta JSON de Laravel.
 * - 419 en una visita Inertia regresa a la página anterior con un aviso
 *   en lugar de mostrar un error a pantalla completa.
 */
final class ErrorPageRenderer
{
    private const BRANDED = [403, 404, 419, 429, 500, 503];

    public static function render(Response $response, Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! in_array($status, self::BRANDED, true) || $request->expectsJson() || $request->is('api/*')) {
            return $response;
        }

        if ($status === 500 && config('app.debug')) {
            return $response;
        }

        if ($status === 419 && $request->header('X-Inertia') && $request->hasSession()) {
            return back()->with('error', 'Tu sesión expiró. Vuelve a intentarlo.');
        }

        try {
            $inertia = Inertia::render('errors/Error', [
                'status' => $status,
                'message' => self::message($status, $e),
                'site' => self::site(),
            ])->toResponse($request);
        } catch (Throwable) {
            // Si la vista Inertia falla (p. ej. sin assets compilados), se
            // conserva la respuesta original para no ocultar el error.
            return $response;
        }

        $inertia->setStatusCode($status);

        foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'] as $header) {
            if ($response->headers->has($header)) {
                $inertia->headers->set($header, (string) $response->headers->get($header));
            }
        }

        return $inertia;
    }

    /**
     * Solo los 403 con mensaje explícito (abort(403, '…')) muestran su texto;
     * el resto usa la copia por defecto del componente.
     */
    private static function message(int $status, Throwable $e): ?string
    {
        if ($status !== 403 || ! $e instanceof HttpExceptionInterface) {
            return null;
        }

        $message = trim($e->getMessage());

        return $message !== '' && $message !== 'This action is unauthorized.' && $message !== 'Forbidden'
            ? $message
            : null;
    }

    /**
     * Identidad del sitio (nombre, contacto) si el módulo web la expone.
     *
     * @return array<string, mixed>|null
     */
    private static function site(): ?array
    {
        try {
            return app(PublicSiteDataService::class)->base()['site'];
        } catch (Throwable) {
            return null;
        }
    }
}
