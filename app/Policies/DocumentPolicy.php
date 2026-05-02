<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;

class DocumentPolicy
{
    public function viewAny(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->user_id
            || $user->hasPermissionTo('index-document');
    }

    public function create(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->user_id
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
