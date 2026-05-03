<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\FillUp;
use App\Models\User;

class FillUpPolicy
{
    public function viewAny(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('index-fill-up');
    }

    public function create(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('create-fill-up');
    }

    public function delete(User $user, FillUp $fillUp): bool
    {
        return $user->id === $fillUp->car->user_id
            || $user->hasPermissionTo('destroy-fill-up');
    }
}
