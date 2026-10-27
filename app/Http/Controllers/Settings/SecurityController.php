<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Cuenta → Seguridad: contraseña (Fortify), verificación en dos pasos
 * (Fortify, con confirmación) y sesiones abiertas (driver `database`).
 */
class SecurityController extends Controller
{
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'twoFactorPending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'twoFactorRequired' => $user->isStaff() && (bool) Settings::get('security.require_2fa_for_staff', false),
            'sessions' => $this->sessions($request),
            'sessionsSupported' => config('session.driver') === 'database',
        ]);
    }

    public function destroyOtherSessions(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ], [
            'password.current_password' => 'La contraseña no es correcta.',
        ], ['password' => 'contraseña']);

        Auth::logoutOtherDevices((string) $request->input('password'));

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()?->getAuthIdentifier())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $activity->log('usuario.sesiones_cerradas', $request->user());

        return back()->with('success', 'Cerramos tus otras sesiones.');
    }

    /**
     * @return list<array{id: string, device: string, browser: string, platform: string, isMobile: bool, ipAddress: string|null, isCurrent: bool, lastActiveAt: string}>
     */
    private function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return array_values(DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()?->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(function ($session) use ($request) {
                $agent = self::parseAgent((string) $session->user_agent);

                return [
                    // Nunca se expone el id real de la sesión.
                    'id' => substr(hash('sha256', (string) $session->id), 0, 12),
                    'device' => $agent['isMobile'] ? 'Teléfono o tableta' : 'Computadora',
                    'browser' => $agent['browser'],
                    'platform' => $agent['platform'],
                    'isMobile' => $agent['isMobile'],
                    'ipAddress' => is_string($session->ip_address) ? $session->ip_address : null,
                    'isCurrent' => $session->id === $request->session()->getId(),
                    'lastActiveAt' => Carbon::createFromTimestamp((int) $session->last_activity)->toIso8601String(),
                ];
            })
            ->all());
    }

    /**
     * Detección mínima (sin dependencias) del navegador y sistema.
     *
     * @return array{browser: string, platform: string, isMobile: bool}
     */
    public static function parseAgent(string $agent): array
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navegador desconocido',
        };

        $platform = match (true) {
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Sistema desconocido',
        };

        return [
            'browser' => $browser,
            'platform' => $platform,
            'isMobile' => (bool) preg_match('/Mobile|Android|iPhone|iPad/i', $agent),
        ];
    }
}
