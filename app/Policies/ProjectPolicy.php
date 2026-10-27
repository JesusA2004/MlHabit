<?php

namespace App\Policies;

use App\Models\MediaAttachment;
use App\Models\Project;
use App\Models\User;

/**
 * Permiso + propiedad. El cliente solo ve SUS proyectos (IDOR). También la
 * usa PrivateMediaController para archivos privados del proyecto.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff()) {
            return $user->hasPermission('projects.view');
        }

        return $project->belongsToUser($user);
    }

    /**
     * Acceso fino a un archivo del proyecto: el cliente solo ve los marcados
     * como `client_document`; el resto es interno. (Para que
     * PrivateMediaAccess lo use cuando la Policy define `viewMedia`.)
     */
    public function viewMedia(User $user, Project $project, MediaAttachment $attachment): bool
    {
        if (! $this->view($user, $project)) {
            return false;
        }

        return $user->isStaff() || $attachment->role === 'client_document';
    }

    public function create(User $user): bool
    {
        return $user->isStaff() && $user->hasPermission('projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isStaff() && $user->hasPermission('projects.manage');
    }
}
