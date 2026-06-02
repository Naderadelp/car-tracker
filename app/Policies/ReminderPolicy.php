<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    public function viewAny(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('index-reminder');
    }

    public function create(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('create-reminder');
    }

    public function view(User $user, Reminder $reminder): bool
    {
        return $user->id === $reminder->car?->user_id
            || $user->hasPermissionTo('show-reminder');
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $user->id === $reminder->car?->user_id
            || $user->hasPermissionTo('edit-reminder');
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $user->id === $reminder->car?->user_id
            || $user->hasPermissionTo('destroy-reminder');
    }
}
