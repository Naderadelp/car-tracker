<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewRoles(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->hasPermissionTo('show-user');
    }

    public function assignRole(User $user, User $target): bool
    {
        return $user->hasPermissionTo('assign-role');
    }

    public function revokeRole(User $user, User $target): bool
    {
        return $user->hasPermissionTo('revoke-role');
    }
}
