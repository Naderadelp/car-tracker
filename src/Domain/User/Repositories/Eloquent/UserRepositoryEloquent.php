<?php

namespace Src\Domain\User\Repositories\Eloquent;

use Src\Domain\User\Entities\User;
use Src\Domain\User\Repositories\Contracts\UserRepository;
use Src\Infrastructure\AbstractRepositories\EloquentRepository;

class UserRepositoryEloquent extends EloquentRepository implements UserRepository
{
    public function model(): string
    {
        return User::class;
    }
}
