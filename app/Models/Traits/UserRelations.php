<?php

namespace App\Models\Traits;

use App\Models\Document;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait UserRelations
{
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
