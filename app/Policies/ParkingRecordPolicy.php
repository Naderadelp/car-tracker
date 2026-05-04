<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\ParkingRecord;
use App\Models\User;

class ParkingRecordPolicy
{
    public function viewAny(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('index-parking-record');
    }

    public function create(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('create-parking-record');
    }

    public function delete(User $user, ParkingRecord $parkingRecord): bool
    {
        return $user->id === $parkingRecord->car->user_id
            || $user->hasPermissionTo('destroy-parking-record');
    }
}
