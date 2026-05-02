<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $guard_name = 'api';

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $this->guard_name;
        parent::__construct($attributes);
    }
}
