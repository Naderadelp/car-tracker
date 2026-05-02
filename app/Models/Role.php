<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $guard_name = 'api';

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $this->guard_name;
        parent::__construct($attributes);
    }
}
