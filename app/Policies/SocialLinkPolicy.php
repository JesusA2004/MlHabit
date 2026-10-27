<?php

namespace App\Policies;

use App\Models\SocialLink;
use App\Models\User;

class SocialLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function update(User $user, SocialLink $socialLink): bool
    {
        return $user->hasPermission('content.manage');
    }

    public function delete(User $user, SocialLink $socialLink): bool
    {
        return $user->hasPermission('content.manage');
    }
}
