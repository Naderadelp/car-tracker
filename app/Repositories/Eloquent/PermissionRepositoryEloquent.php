<?php

namespace App\Repositories\Eloquent;

use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepository;

class PermissionRepositoryEloquent extends EloquentRepository implements PermissionRepository
{
    protected array $allowedFilters = ['name'];

    protected array $allowedSorts = ['name'];

    public function model(): string
    {
        return Permission::class;
    }
}
