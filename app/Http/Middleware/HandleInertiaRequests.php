<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Web\PublicSite;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($request->hasSession()) {
            foreach (['success', 'error'] as $type) {
                $message = $request->session()->get($type);
                if (is_string($message) && $message !== '') {
                    Inertia::flash('toast', ['type' => $type, 'message' => $message]);
                    break;
                }
            }
        }

        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                    // Área propia según el rol (cliente → /portal, técnico →
                    // /tecnico, equipo → /admin): la usa el acceso del sitio.
                    'homePath' => $user->role->homePath(),
                    'permissions' => $user->permissions(),
                    'emailVerified' => $user->email_verified_at !== null,
                    'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
                ] : null,
            ],
            // Datos del sitio público (navegación, contacto, pie) para TODAS
            // las páginas públicas, también las que no pasan por PublicPage
            // (solicitud, asesoría, precalificación, visita, comprobante):
            // así la cabecera y el pie son idénticos en todo el sitio. Las
            // páginas que envían su propio `site` lo reemplazan.
            ...($this->isPublicPage($request) ? ['site' => fn () => $this->publicSite()] : []),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** Rutas que se pintan con PublicLayout (sitio público y flujos del cliente sin sesión). */
    private function isPublicPage(Request $request): bool
    {
        return $request->routeIs('home', 'public.*', 'pagos.comprobante');
    }

    /**
     * @return array<string, mixed>
     */
    private function publicSite(): array
    {
        try {
            return app(PublicSite::class)->site();
        } catch (\Throwable) {
            return ['name' => PublicSite::name()];
        }
    }
}
