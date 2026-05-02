<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;

class DocumentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->user_id;
    }

    public function create(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->user_id;
    }

    public function view(User $user, Document $document): bool
    {
        return $user->id === $document->user_id;
    }

    public function update(User $user, Document $document): bool
    {
        return $user->id === $document->user_id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->id === $document->user_id;
    }
}
