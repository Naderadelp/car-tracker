<?php

namespace App\Models\Traits;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait CostRelations
{
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
