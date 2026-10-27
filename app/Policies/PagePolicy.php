<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, Page $page): bool
    {
        return $user->canManagePosts();
    }
}
