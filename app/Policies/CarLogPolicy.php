<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\CarLog;
use App\Models\User;

class CarLogPolicy
{
    public function viewAny(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('index-car-log');
    }

    public function create(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('create-car-log');
    }

    public function view(User $user, CarLog $log): bool
    {
        return $user->id === $log->car?->user_id
            || $user->hasPermissionTo('show-car-log');
    }

    public function update(User $user, CarLog $log): bool
    {
        return $user->id === $log->car?->user_id
            || $user->hasPermissionTo('edit-car-log');
    }

    public function delete(User $user, CarLog $log): bool
    {
        return $user->id === $log->car?->user_id
            || $user->hasPermissionTo('destroy-car-log');
    }
}
