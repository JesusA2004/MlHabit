<?php

namespace App\Providers;

use App\Payments\PaymentGateway;
use App\Payments\Testing\E2eSandboxGateway;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Ajustes exclusivos de las pruebas de navegador (Playwright, tests/e2e).
 *
 * Fuera de APP_ENV=e2e no hace NADA. En producción lanza una excepción si
 * por error se activara el entorno e2e. Con OPENPAY_E2E_FAKE=true sustituye
 * la pasarela real por App\Payments\Testing\E2eSandboxGateway (simulación
 * de las tarjetas de prueba del sandbox de Openpay, sin red).
 */
class E2eServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $fake = (bool) config('services.openpay.e2e_fake', false);

        // Salvaguarda: la pasarela simulada jamás debe activarse en producción.
        if ($this->app->isProduction() && $fake) {
            throw new RuntimeException('OPENPAY_E2E_FAKE no puede activarse en producción.');
        }

        if (! $this->app->environment('e2e')) {
            return;
        }

        if ($fake) {
            $this->app->singleton(PaymentGateway::class, E2eSandboxGateway::class);
        }
    }
}
