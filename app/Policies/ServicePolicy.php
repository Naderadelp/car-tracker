<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user, ?Car $car = null): bool
    {
        if ($car !== null) {
            return $user->id === $car->user_id
                || $user->hasPermissionTo('index-service');
        }

        return true;
    }

    public function view(User $user, Service $service): bool
    {
        return $service->user_id === null
            || $service->user_id === $user->id
            || $user->hasPermissionTo('show-service');
    }

    public function create(User $user, ?Car $car = null): bool
    {
        if ($car !== null) {
            return $user->id === $car->user_id;
        }

        return $user->hasPermissionTo('create-service');
    }

    public function update(User $user, Service $service): bool
    {
        if ($service->user_id !== null) {
            return $service->user_id === $user->id
                || $user->hasPermissionTo('edit-service');
        }

        return $user->hasPermissionTo('edit-service');
    }

    public function delete(User $user, Service $service): bool
    {
        if ($service->user_id !== null) {
            return $service->user_id === $user->id
                || $user->hasPermissionTo('destroy-service');
        }

        return $user->hasPermissionTo('destroy-service');
    }
}
