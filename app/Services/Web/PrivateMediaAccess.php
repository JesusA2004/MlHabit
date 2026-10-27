<?php

namespace App\Services\Web;

use App\Enums\Permission;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Decide si un usuario puede ver un archivo privado:
 *  1. Tiene `sensitive.view`; o
 *  2. Puede ver (`view` en su Policy) CUALQUIER entidad a la que el
 *     archivo está vinculado (prospecto, proyecto, visita…); o
 *  3. Es quien lo subió, dentro de las primeras horas, mientras aún no
 *     se vincula a nada (para previsualizar antes de guardar).
 */
class PrivateMediaAccess
{
    public function allows(User $user, Media $media): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if (! $media->isPrivate()) {
            return true;
        }

        if ($user->hasPermission(Permission::SensitiveDataView)) {
            return true;
        }

        $attachments = MediaAttachment::query()->where('media_id', $media->id)->get();

        foreach ($attachments as $attachment) {
            $attachable = $this->resolve($attachment);
            if ($attachable === null) {
                continue;
            }

            // Si la Policy define viewMedia, decide por archivo (p. ej. un
            // cliente solo ve los documentos marcados para él).
            $policy = Gate::getPolicyFor($attachable);
            $allowed = $policy !== null && method_exists($policy, 'viewMedia')
                ? Gate::forUser($user)->allows('viewMedia', [$attachable, $attachment])
                : Gate::forUser($user)->allows('view', $attachable);

            if ($allowed) {
                return true;
            }
        }

        return $attachments->isEmpty()
            && $media->uploaded_by !== null
            && $media->uploaded_by === $user->id
            && $media->created_at !== null
            && $media->created_at->greaterThan(now()->subHours((int) config('media.orphan_hours', 24)));
    }

    private function resolve(MediaAttachment $attachment): ?Model
    {
        try {
            $attachable = $attachment->attachable()->first();

            return $attachable instanceof Model ? $attachable : null;
        } catch (Throwable) {
            // Tipo de entidad que ya no existe: no concede acceso.
            return null;
        }
    }
}
