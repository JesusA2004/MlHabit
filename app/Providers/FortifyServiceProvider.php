<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasswordResetLinkFailedResponse;
use App\Models\User;
use App\Services\Web\PublicSite;
use App\Services\Web\Seo;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

/**
 * Autenticación de ML Habitat (Fortify, sin registro público):
 * - Solo cuentas activas inician sesión; el error es siempre genérico para
 *   no revelar si el correo existe o si la cuenta está desactivada.
 * - Tras iniciar sesión cada rol llega a su área (LoginResponse).
 * - Se registra `last_login_at` en cada inicio de sesión completo.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkFailedResponse::class);
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
    }

    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            // Timebox: misma duración exista o no la cuenta (sin enumeración).
            return (new Timebox)->call(function () use ($request): ?User {
                $email = Str::lower(trim((string) $request->input(Fortify::username())));
                $user = User::query()->where('email', $email)->first();

                if ($user === null
                    || ! Hash::check((string) $request->input('password'), $user->password)
                    || ! $user->is_active) {
                    return null;
                }

                return $user;
            }, 200_000);
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'seo' => $this->seo()->auth(
                'Iniciar sesión',
                'Entra a tu cuenta de '.PublicSite::name().' para dar seguimiento a tu proyecto: cotizaciones, citas, avances de fabricación y pagos.',
                '/login',
                index: true,
            ),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            // Canónica sin el token del enlace.
            'seo' => $this->seo()->auth('Restablecer contraseña', 'Crea una contraseña nueva para tu cuenta de '.PublicSite::name().'.', '/forgot-password'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
            'seo' => $this->seo()->auth('Recuperar contraseña', 'Recupera el acceso a tu cuenta de '.PublicSite::name().' con un enlace que te enviamos por correo.', '/forgot-password'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
            'seo' => $this->seo()->auth('Verificar correo', 'Confirma tu correo para activar tu cuenta de '.PublicSite::name().'.', '/email/verify'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge', [
            'seo' => $this->seo()->auth('Verificación en dos pasos', 'Confirma que eres tú para terminar de iniciar sesión en '.PublicSite::name().'.', '/two-factor-challenge'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword', [
            'seo' => $this->seo()->auth('Confirmar contraseña', 'Escribe tu contraseña para continuar en '.PublicSite::name().'.', '/user/confirm-password'),
        ]));
    }

    /** Metadatos SEO y vista previa de las pantallas de acceso. */
    private function seo(): Seo
    {
        return $this->app->make(Seo::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return [
                Limit::perMinute(5)->by($throttleKey),
                // Freno adicional por IP contra el relleno de credenciales.
                Limit::perMinute(30)->by('login-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->session()->get('login.id').'|'.$request->ip());
        });
    }
}
