<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Edición: `content.manage`. Publicar (hacer visible en el sitio) además requiere
 * `content.publish`.
 */
class TestimonialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function view(User $user, Testimonial $testimonial): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function update(User $user, Testimonial $testimonial): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function publish(User $user): bool
    {
        return $user->hasPermission('content.manage') && $user->hasPermission('content.publish');
    }
}
