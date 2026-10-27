<?php

use App\Exceptions\ErrorPageRenderer;
use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\CaptureLeadAttribution;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureTwoFactorForStaff;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackPageView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['sidebar_state']);

        // Dominio canónico + https (solo producción; ver docs/SEO.md).
        $middleware->prepend(CanonicalHost::class);

        $middleware->alias([
            'staff' => EnsureStaff::class,
            'role' => EnsureRole::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackPageView::class,
            CaptureLeadAttribution::class,
            SecurityHeaders::class,
            EnsureTwoFactorForStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Páginas de error con marca (403, 404, 419, 429, 500 sin depuración, 503).
        $exceptions->respond(fn (Response $response, Throwable $e, Request $request) => ErrorPageRenderer::render($response, $e, $request));
    })->create();
