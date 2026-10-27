<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManagePosts();
    }

    public function update(User $user, Post $post): bool
    {
        return $user->canManagePosts();
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->canManagePosts();
    }

    public function feature(User $user, Post $post): bool
    {
        return $user->canManagePosts();
    }
}
