<?php

namespace App\Models\Traits;

use App\Models\User;
use App\Models\Vehicle;

trait DocumentRelations
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
