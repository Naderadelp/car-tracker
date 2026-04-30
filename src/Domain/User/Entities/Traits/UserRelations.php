<?php

namespace Src\Domain\User\Entities\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Domain\Vehicle\Entities\Vehicle;

trait UserRelations
{
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
