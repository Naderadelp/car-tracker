<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\Issue;
use App\Models\User;

class IssuePolicy
{
    public function viewAny(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('index-issue');
    }

    public function create(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('create-issue');
    }

    public function view(User $user, Issue $issue): bool
    {
        return $user->id === $issue->user_id
            || $user->hasPermissionTo('show-issue');
    }

    /**
     * Deliberately separate from view(), mirroring DocumentPolicy: reading a
     * fault's metadata and pulling its photo off the private disk are different
     * privileges (constitution Principle V).
     */
    public function secureDownload(User $user, Issue $issue): bool
    {
        return $user->id === $issue->user_id
            || $user->hasPermissionTo('secure-download-issue');
    }

    public function update(User $user, Issue $issue): bool
    {
        return $user->id === $issue->user_id
            || $user->hasPermissionTo('edit-issue');
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $user->id === $issue->user_id
            || $user->hasPermissionTo('destroy-issue');
    }
}
