<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\Cost;
use App\Models\User;

/**
 * FR-017 — a driver reads and writes only costs on their own car.
 *
 * No before() method: AppServiceProvider registers one global Gate::before()
 * for the admin bypass (constitution Principle V).
 */
class CostPolicy
{
    public function viewAny(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('index-cost');
    }

    public function create(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('create-cost');
    }

    public function view(User $user, Cost $cost): bool
    {
        return $user->id === $cost->user_id
            || $user->hasPermissionTo('show-cost');
    }

    public function update(User $user, Cost $cost): bool
    {
        return $user->id === $cost->user_id
            || $user->hasPermissionTo('edit-cost');
    }

    public function delete(User $user, Cost $cost): bool
    {
        return $user->id === $cost->user_id
            || $user->hasPermissionTo('destroy-cost');
    }
}
