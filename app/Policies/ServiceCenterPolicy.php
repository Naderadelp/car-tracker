<?php

namespace App\Policies;

use App\Models\ServiceCenter;
use App\Models\User;

class ServiceCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceCenter $serviceCenter): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-service-center');
    }

    public function update(User $user, ServiceCenter $serviceCenter): bool
    {
        return $user->hasPermissionTo('edit-service-center');
    }

    public function delete(User $user, ServiceCenter $serviceCenter): bool
    {
        return $user->hasPermissionTo('destroy-service-center');
    }
}
