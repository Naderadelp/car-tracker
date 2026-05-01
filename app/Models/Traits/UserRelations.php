<?php

namespace App\Models\Traits;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait UserRelations
{
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
