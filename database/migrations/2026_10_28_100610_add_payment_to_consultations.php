<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asesorías siempre con cobro (Openpay):
 *  - consultations: pago vigente (se conserva al reprogramar), apartado del
 *    horario mientras se paga, fecha de pago y motivo de atención (pago
 *    recibido tarde o con el horario ya tomado).
 *  - payments.consultation_id: vínculo directo para listar los pagos de
 *    asesoría de un cliente (además de payments.customer_id).
 *  - Precio: si estaba en 0 se fija un valor provisional de $500.00 MXN
 *    (editable en /admin/configuracion; nunca puede volver a 0).
 */
return new class extends Migration
{
    public const PLACEHOLDER_PRICE = '500.00';

    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('price')->constrained('payments')->nullOnDelete();
            $table->dateTime('hold_expires_at')->nullable()->after('payment_id');
            $table->timestamp('paid_at')->nullable()->after('hold_expires_at');
            $table->string('attention_reason', 300)->nullable()->after('paid_at');

            $table->index(['status', 'hold_expires_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('consultation_id')->nullable()->after('contract_id')->constrained('consultations')->nullOnDelete();
        });

        $row = DB::table('site_settings')->where('key', 'consultation')->first();
        $values = $row !== null ? (json_decode((string) $row->value, true) ?: []) : [];
        $price = $values['price'] ?? null;

        if (! is_numeric($price) || bccomp((string) $price, '0', 2) <= 0) {
            $values['price'] = self::PLACEHOLDER_PRICE;

            if ($row !== null) {
                DB::table('site_settings')->where('key', 'consultation')->update(['value' => json_encode($values), 'updated_at' => now()]);
            } else {
                DB::table('site_settings')->insert(['key' => 'consultation', 'value' => json_encode($values), 'created_at' => now(), 'updated_at' => now()]);
            }

            Cache::forget('site_settings.consultation');
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consultation_id');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropIndex(['status', 'hold_expires_at']);
            $table->dropConstrainedForeignId('payment_id');
            $table->dropColumn(['hold_expires_at', 'paid_at', 'attention_reason']);
        });
    }
};
