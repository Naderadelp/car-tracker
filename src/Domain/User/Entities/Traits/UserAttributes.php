<?php

namespace Src\Domain\User\Entities\Traits;

trait UserAttributes
{
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
}
