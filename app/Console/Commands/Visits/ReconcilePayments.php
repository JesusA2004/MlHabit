<?php

namespace App\Console\Commands\Visits;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Payments\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Concilia con Openpay los pagos que quedaron en `processing`/`pending` con
 * intentos abiertos (timeouts, 3DS sin regreso, webhooks perdidos) y los que
 * tienen reembolsos por confirmar. Programar cada 5 minutos.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--minutes=5 : Antigüedad mínima del último cambio} {--limit=200}';

    protected $description = 'Concilia con Openpay los pagos en proceso o con reembolsos por confirmar';

    public function handle(PaymentService $payments): int
    {
        $before = now()->subMinutes(max(0, (int) $this->option('minutes')));

        $candidates = Payment::query()
            ->where('provider', PaymentProvider::Openpay->value)
            ->where('updated_at', '<=', $before)
            ->where(function (Builder $q) {
                $q->where('status', PaymentStatus::Processing->value)
                    ->orWhere(fn (Builder $p) => $p->where('status', PaymentStatus::Pending->value)->whereHas('attempts'))
                    ->orWhereHas('refunds', fn ($r) => $r->where('status', RefundStatus::Pending->value));
            })
            ->orderBy('updated_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $changed = 0;

        foreach ($candidates as $payment) {
            try {
                $before = $payment->status;
                $after = $payments->reconcile($payment)->status;
                if ($after !== $before) {
                    $changed++;
                }
            } catch (Throwable $e) {
                $this->warn("{$payment->folio}: no se pudo conciliar ({$e->getMessage()})");
            }
        }

        $this->info("Pagos revisados: {$candidates->count()} · con cambio de estado: {$changed}");

        return self::SUCCESS;
    }
}
