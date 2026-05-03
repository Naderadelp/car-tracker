<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('index-document');
    }

    public function create(User $user, Car $car): bool
    {
        return $user->id === $car->user_id
            || $user->hasPermissionTo('create-document');
    }

    public function view(User $user, Document $document): bool
    {
        return $user->id === $document->user_id
            || $user->hasPermissionTo('show-document');
    }

    public function secureDownload(User $user, Document $document): bool
    {
        return $user->id === $document->user_id
            || $user->hasPermissionTo('secure-download-document');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->id === $document->user_id
            || $user->hasPermissionTo('edit-document');
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->id === $document->user_id
            || $user->hasPermissionTo('destroy-document');
    }
}
