<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepository;

class RoleRepositoryEloquent extends EloquentRepository implements RoleRepository
{
    protected array $allowedIncludes = ['permissions'];

    protected array $allowedFilters = ['name'];

    protected array $allowedSorts = ['name'];

    public function model(): string
    {
        return Role::class;
    }
}
