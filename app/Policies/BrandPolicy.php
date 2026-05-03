<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('index-brand');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->hasPermissionTo('show-brand');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-brand');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->hasPermissionTo('edit-brand');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->hasPermissionTo('destroy-brand');
    }
}
