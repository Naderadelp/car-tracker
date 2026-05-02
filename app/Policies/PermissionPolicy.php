<?php

namespace App\Policies;

use App\Models\User;

class PermissionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('index-permission');
    }
}
