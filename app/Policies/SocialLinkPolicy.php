<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SocialLink;
use App\Models\User;

class SocialLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, SocialLink $socialLink): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, SocialLink $socialLink): bool
    {
        return $user->role === UserRole::Admin;
    }
}
