<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;

/**
 * There was no CarPolicy before this feature, which is the reason no car write
 * endpoint ever existed: a car could be created at registration and then never
 * corrected. FR-001 through FR-004 depend on this file.
 *
 * No before() method — AppServiceProvider registers a single global
 * Gate::before() that bypasses everything for admins (constitution Principle V).
 */
class CarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('index-car');
    }

    public function view(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('show-car');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-car');
    }

    public function update(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('edit-car');
    }

    public function delete(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('destroy-car');
    }
}
