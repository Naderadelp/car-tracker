<?php

namespace App\Policies;

use App\Models\FuelPrice;
use App\Models\User;

class FuelPricePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-fuel-price');
    }

    public function update(User $user, FuelPrice $price): bool
    {
        return $user->hasPermissionTo('edit-fuel-price');
    }
}
