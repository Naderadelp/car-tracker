<?php

namespace App\Models\Traits;

use App\Models\Car;
use App\Models\User;

trait DocumentRelations
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}
