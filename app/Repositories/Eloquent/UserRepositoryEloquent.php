<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepository;

class UserRepositoryEloquent extends EloquentRepository implements UserRepository
{
    protected array $allowedIncludes     = ['cars', 'documents'];
    protected array $allowedDefaultSorts = ['-id'];

    public function model(): string
    {
        return User::class;
    }
}
