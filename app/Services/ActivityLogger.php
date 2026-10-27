<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Bitácora de auditoría: actor, acción, entidad, antes/después y metadata
 * segura. Las claves sensibles se enmascaran SIEMPRE antes de guardar
 * (contraseñas, tokens, llaves, datos de tarjeta).
 *
 * Convención de acciones: `modulo.verbo` en español (p. ej. `pago.confirmado`,
 * `cotizacion.aceptada`, `permisos.actualizados`).
 */
class ActivityLogger
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes', 'private_key', 'secret', 'api_key',
        'card_number', 'cvv', 'cvv2', 'holder_name', 'expiration_month', 'expiration_year',
        'source_id', 'device_session_id', 'config',
    ];

    /**
     * @param  array<string, mixed>  $changes  Libre; recomendado {before:{…}, after:{…}, …}
     */
    public function log(string $action, ?Model $subject = null, array $changes = [], string $result = 'success'): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ? self::sanitize($changes) : null,
            'result' => $result,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Registra los atributos modificados de un modelo (antes/después).
     * Llamar ANTES de save() o pasar $original explícito.
     *
     * @param  list<string>  $only
     */
    public function logModelChanges(string $action, Model $model, array $only = []): ?ActivityLog
    {
        $dirty = $model->getDirty();
        if ($only !== []) {
            $dirty = array_intersect_key($dirty, array_flip($only));
        }
        if ($dirty === []) {
            return null;
        }

        $before = array_intersect_key($model->getOriginal(), $dirty);

        return $this->log($action, $model, ['before' => $before, 'after' => $dirty]);
    }

    /**
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[oculto]';

                continue;
            }
            if (is_array($value)) {
                $data[$key] = self::sanitize($value);
            } elseif ($value instanceof \BackedEnum) {
                $data[$key] = $value->value;
            } elseif (is_string($value) && preg_match('/\b\d{13,19}\b/', $value)) {
                // Posible número de tarjeta en texto libre.
                $data[$key] = preg_replace('/\b\d{13,19}\b/', '[oculto]', $value);
            }
        }

        return $data;
    }
}
