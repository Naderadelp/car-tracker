<?php

namespace App\Models\Traits;

use App\Models\Car;
use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait UserRelations
{
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
