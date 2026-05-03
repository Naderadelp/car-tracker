<?php

namespace App\Policies;

use App\Models\CarModel;
use App\Models\User;

class CarModelPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-car-model');
    }

    public function update(User $user, CarModel $carModel): bool
    {
        return $user->hasPermissionTo('edit-car-model');
    }

    public function delete(User $user, CarModel $carModel): bool
    {
        return $user->hasPermissionTo('destroy-car-model');
    }
}
