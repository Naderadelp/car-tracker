<?php

namespace App\Models\Traits;

trait HasDefaultRoles
{
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isSuperUser(): bool
    {
        return $this->hasRole('super-user');
    }

    public function isUser(): bool
    {
        return $this->hasRole('user');
    }
}
