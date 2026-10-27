<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registra una entrada legible en la bitácora de actividad del CMS
 * (sección /admin/actividad). Nunca debe recibir secretos (contraseñas,
 * tokens, credenciales de almacenamiento) dentro de $changes.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function log(string $action, ?Model $subject = null, array $changes = [], string $result = 'success'): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'result' => $result,
            'ip_address' => Request::ip(),
        ]);
    }
}
