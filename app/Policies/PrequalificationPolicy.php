<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Prequalification;
use App\Models\User;

/**
 * Precalificaciones: datos financieros sensibles. Ver el expediente exige
 * prequalification.view; ver o descargar documentos exige además
 * sensitive.view; dictaminar exige prequalification.review.
 *
 * `view` es la habilidad que usa archivos.show para adjuntos privados: por
 * eso aquí también exige sensitive.view (los documentos son el único
 * adjunto de esta entidad). El detalle en el panel usa `viewSummary`.
 */
class PrequalificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PrequalificationView);
    }

    public function viewSummary(User $user, Prequalification $prequalification): bool
    {
        return $user->hasPermission(Permission::PrequalificationView);
    }

    public function view(User $user, Prequalification $prequalification): bool
    {
        return $this->viewDocuments($user, $prequalification);
    }

    public function viewDocuments(User $user, Prequalification $prequalification): bool
    {
        return $user->hasPermission(Permission::PrequalificationView)
            && $user->hasPermission(Permission::SensitiveDataView);
    }

    public function review(User $user, Prequalification $prequalification): bool
    {
        return $user->hasPermission(Permission::PrequalificationReview);
    }
}
