<?php

namespace App\Models\Traits;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait CarRelations
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }
}
