<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('index-trip');
    }

    public function create(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('create-trip');
    }
}
