<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('index-role');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('show-role');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-role');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('edit-role');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('destroy-role');
    }

    public function syncPermissions(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('assign-permission');
    }

    public function revokePermission(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('revoke-permission');
    }
}
